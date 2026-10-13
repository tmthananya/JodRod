-- ==========================================================
-- Migration: 20261013_fix_acceptance_and_owner_car_rls.sql
-- Description: 
-- 1. Adds 'updated_at' column to parking_spaces (or ensures procedures don't fail)
-- 2. Updates accept_reservation and cancel_reservation RPC functions to cleanly
--    handle capacity updates without column mismatch.
-- 3. Adds SELECT policy on public.cars allowing space owners to view vehicles
--    reserved in their parking lots.
-- ==========================================================

-- 1. Ensure parking_spaces has updated_at column to maintain schema parity
ALTER TABLE public.parking_spaces 
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

-- 2. Add RLS policy allowing parking space owners to view vehicles linked to reservations in their spaces
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies 
        WHERE schemaname = 'public' 
          AND tablename = 'cars' 
          AND policyname = 'Space owners can view cars reserved in their spaces'
    ) THEN
        CREATE POLICY "Space owners can view cars reserved in their spaces"
            ON public.cars FOR SELECT
            TO authenticated
            USING (
                EXISTS (
                    SELECT 1 FROM public.reservations
                    JOIN public.parking_spaces ON public.parking_spaces.id = public.reservations.parking_space_id
                    WHERE public.reservations.car_id = public.cars.id
                      AND public.parking_spaces.owner_id = auth.uid()
                )
            );
    END IF;
END $$;

-- 3. Replace accept_reservation function with atomic capacity locking and check
CREATE OR REPLACE FUNCTION public.accept_reservation(
    p_reservation_id UUID,
    p_owner_id UUID
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_res RECORD;
    v_space RECORD;
    v_new_avail INTEGER;
    v_new_status TEXT;
BEGIN
    -- Verify reservation exists and is currently pending
    SELECT * INTO v_res FROM public.reservations WHERE id = p_reservation_id FOR UPDATE;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Reservation not found.');
    END IF;

    IF v_res.status != 'pending' THEN
        RETURN jsonb_build_object('success', false, 'error', 'Only pending reservations can be accepted. Current status: ' || v_res.status);
    END IF;

    -- Row-level lock on parking space to prevent race conditions & overbooking
    SELECT * INTO v_space FROM public.parking_spaces WHERE id = v_res.parking_space_id FOR UPDATE;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Parking lot not found.');
    END IF;

    -- Enforce caller is the space owner
    IF v_space.owner_id IS NULL OR v_space.owner_id != p_owner_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not own this parking lot.');
    END IF;

    IF auth.uid() IS NOT NULL AND auth.uid() != p_owner_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: Unauthorized session.');
    END IF;

    -- Capacity check under lock
    IF v_space.available_spaces <= 0 OR v_space.status != 'available' THEN
        RETURN jsonb_build_object('success', false, 'error', 'Cannot accept reservation: Parking lot is full or closed.');
    END IF;

    -- Update reservation to 'confirmed'
    UPDATE public.reservations
    SET status = 'confirmed', updated_at = now()
    WHERE id = p_reservation_id;

    -- Decrement available capacity by 1
    v_new_avail := v_space.available_spaces - 1;
    v_new_status := CASE WHEN v_new_avail = 0 THEN 'full' ELSE 'available' END;

    UPDATE public.parking_spaces
    SET available_spaces = v_new_avail,
        status = v_new_status,
        updated_at = now()
    WHERE id = v_space.id;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Reservation accepted! Parking spot secured.',
        'reservation_id', p_reservation_id,
        'available_spaces', v_new_avail,
        'parking_space_status', v_new_status
    );
END;
$$;

-- 4. Replace cancel_reservation function with atomic capacity restoration
CREATE OR REPLACE FUNCTION public.cancel_reservation(
    p_reservation_id UUID,
    p_user_id UUID
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_res RECORD;
    v_space RECORD;
    v_new_avail INTEGER;
    v_new_status TEXT;
BEGIN
    -- Verify caller matches authenticated user if session active
    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You cannot cancel another user''s reservation.');
    END IF;

    SELECT * INTO v_res
    FROM public.reservations
    WHERE id = p_reservation_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Reservation record not found.');
    END IF;

    -- Fetch linked space to verify owner authorization
    SELECT * INTO v_space
    FROM public.parking_spaces
    WHERE id = v_res.parking_space_id
    FOR UPDATE;

    -- User must be either the driver who reserved OR the space owner
    IF v_res.user_id != p_user_id AND (v_space.owner_id IS NULL OR v_space.owner_id != p_user_id) THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not have permission to cancel this reservation.');
    END IF;

    -- Only pending or confirmed reservations can be cancelled
    IF v_res.status NOT IN ('pending', 'confirmed') THEN
        RETURN jsonb_build_object('success', false, 'error', 'This reservation is already ' || v_res.status || '.');
    END IF;

    -- If reservation was confirmed, restore 1 spot (respecting 'closed' status)
    IF v_res.status = 'confirmed' AND v_space.id IS NOT NULL THEN
        v_new_avail := LEAST(v_space.capacity, v_space.available_spaces + 1);
        v_new_status := CASE WHEN v_space.status = 'full' AND v_new_avail > 0 THEN 'available' ELSE v_space.status END;

        UPDATE public.parking_spaces
        SET available_spaces = v_new_avail,
            status = v_new_status,
            updated_at = now()
        WHERE id = v_space.id;
    END IF;

    UPDATE public.reservations
    SET status = 'cancelled', updated_at = now()
    WHERE id = p_reservation_id;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Reservation cancelled successfully. Spot restored if previously confirmed.',
        'reservation_id', p_reservation_id,
        'previous_status', v_res.status
    );
END;
$$;

GRANT EXECUTE ON FUNCTION public.accept_reservation TO authenticated;
GRANT EXECUTE ON FUNCTION public.cancel_reservation TO authenticated;
