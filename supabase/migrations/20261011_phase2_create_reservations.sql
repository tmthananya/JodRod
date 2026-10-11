-- ============================================================================
-- JodRod Phase 2: Create Reservations & Owner Approval Workflow
-- Migration: 20261011_phase2_create_reservations.sql
-- 
-- Description:
-- Establishes the public.reservations table and atomic stored procedures
-- for mutual confirmation between vehicle drivers and parking space owners.
-- 
-- Key Requirements Enforced:
-- 1. UUID primary keys.
-- 2. user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE.
-- 3. car_id UUID NOT NULL referencing public.cars(id, user_id) via composite FK.
-- 4. parking_space_id UUID NOT NULL REFERENCES public.parking_spaces(id) ON DELETE CASCADE.
-- 5. Status values: 'pending', 'confirmed', 'rejected', 'cancelled', 'completed'.
-- 6. Partial unique index: At most ONE pending or confirmed reservation per car.
-- 7. Row Level Security: Narrowly granted. No direct client INSERT/UPDATE/DELETE policies
--    to ensure all mutations must go through the verified RPC workflow.
-- 8. Atomic RPCs with database-side capacity locking (SELECT ... FOR UPDATE):
--    - request_reservation: Driver requests parking; creates 'pending' record without altering capacity.
--    - accept_reservation: Owner accepts; decrements available_spaces by 1, updates status to 'full' if 0 left.
--    - reject_reservation: Owner declines; updates status to 'rejected', releases car without altering capacity.
--    - cancel_reservation: Driver or owner cancels; restores capacity by 1 if reservation was confirmed.
-- 9. Community spaces (owner_id IS NULL) are discoverable but cannot accept reservation requests.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. RESERVATIONS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.reservations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    car_id UUID NOT NULL,
    parking_space_id UUID NOT NULL REFERENCES public.parking_spaces(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'completed')),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    -- Composite Foreign Key: Guarantees car_id belongs to the same user_id in public.cars
    CONSTRAINT fk_reservation_car_ownership FOREIGN KEY (car_id, user_id) 
        REFERENCES public.cars(id, user_id) ON DELETE CASCADE
);

-- Ensure composite constraint exists if table already existed without it
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_reservation_car_ownership'
    ) THEN
        ALTER TABLE public.reservations 
            ADD CONSTRAINT fk_reservation_car_ownership 
            FOREIGN KEY (car_id, user_id) REFERENCES public.cars(id, user_id) ON DELETE CASCADE;
    END IF;
END $$;

-- Fast indexes for queries
CREATE INDEX IF NOT EXISTS idx_reservations_user_id ON public.reservations(user_id);
CREATE INDEX IF NOT EXISTS idx_reservations_space_id ON public.reservations(parking_space_id);
CREATE INDEX IF NOT EXISTS idx_reservations_car_id ON public.reservations(car_id);
CREATE INDEX IF NOT EXISTS idx_reservations_status ON public.reservations(status);

-- Database constraint: A car can have at most ONE active reservation ('pending' or 'confirmed')
DROP INDEX IF EXISTS public.uq_active_reservation_per_car;
CREATE UNIQUE INDEX uq_active_reservation_per_car 
ON public.reservations (car_id) 
WHERE status IN ('pending', 'confirmed');

-- ----------------------------------------------------------------------------
-- 2. ROW LEVEL SECURITY (RLS)
-- ----------------------------------------------------------------------------
ALTER TABLE public.reservations ENABLE ROW LEVEL SECURITY;

-- Cleanly drop existing policies to prevent conflicts
DO $$
DECLARE
    pol RECORD;
BEGIN
    FOR pol IN 
        SELECT policyname 
        FROM pg_policies 
        WHERE schemaname = 'public' AND tablename = 'reservations'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.reservations', pol.policyname);
    END LOOP;
END $$;

-- Policy 1: Driver can view their own reservations
CREATE POLICY "Drivers can view own reservations" 
    ON public.reservations FOR SELECT 
    TO authenticated 
    USING (auth.uid() = user_id);

-- Policy 2: Parking lot owner can view reservations for their lots
CREATE POLICY "Owners can view reservations for their lots" 
    ON public.reservations FOR SELECT 
    TO authenticated 
    USING (
        EXISTS (
            SELECT 1 FROM public.parking_spaces
            WHERE public.parking_spaces.id = public.reservations.parking_space_id
              AND public.parking_spaces.owner_id = auth.uid()
        )
    );

-- NOTE: No direct INSERT / UPDATE / DELETE policies are granted to client roles!
-- All state mutations MUST execute via the verified atomic RPC functions below.

-- ----------------------------------------------------------------------------
-- 3. ATOMIC STORED PROCEDURES (SECURITY DEFINER with Fixed Search Path)
-- ----------------------------------------------------------------------------

-- Function 1: Request Reservation (Driver creates 'pending' request; does NOT cut down spots yet)
CREATE OR REPLACE FUNCTION public.request_reservation(
    p_parking_space_id UUID,
    p_user_id UUID,
    p_car_id UUID,
    p_notes TEXT DEFAULT NULL
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_space RECORD;
    v_car RECORD;
    v_res RECORD;
BEGIN
    -- Verify caller matches user_id (if authenticated JWT is present)
    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You cannot request reservations for another account.');
    END IF;

    -- Verify vehicle belongs to driver
    SELECT * INTO v_car FROM public.cars WHERE id = p_car_id AND user_id = p_user_id;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Vehicle not found or does not belong to you.');
    END IF;

    -- Check if car already has an active reservation (pending or confirmed)
    IF EXISTS (
        SELECT 1 FROM public.reservations
        WHERE car_id = p_car_id AND status IN ('pending', 'confirmed')
    ) THEN
        RETURN jsonb_build_object('success', false, 'error', 'This vehicle already has an active reservation or pending request.');
    END IF;

    -- Verify target parking space
    SELECT * INTO v_space FROM public.parking_spaces WHERE id = p_parking_space_id;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Parking lot not found.');
    END IF;

    -- Community lots where owner_id IS NULL cannot accept reservation requests
    IF v_space.owner_id IS NULL THEN
        RETURN jsonb_build_object('success', false, 'error', 'Community spaces without an owner cannot accept reservation requests. They operate on a first-come, first-served basis.');
    END IF;

    -- Prevent owner from reserving spot at their own lot
    IF v_space.owner_id = p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'You cannot reserve a parking space at a lot you own.');
    END IF;

    -- Verify availability status and capacity
    IF v_space.status != 'available' OR v_space.available_spaces <= 0 THEN
        RETURN jsonb_build_object('success', false, 'error', 'This parking lot is currently full or closed.');
    END IF;

    -- Create reservation in 'pending' status without decreasing capacity
    INSERT INTO public.reservations (user_id, car_id, parking_space_id, status, notes)
    VALUES (p_user_id, p_car_id, p_parking_space_id, 'pending', p_notes)
    RETURNING * INTO v_res;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Reservation request submitted. Awaiting parking lot owner approval.',
        'reservation', row_to_json(v_res)
    );
END;
$$;


-- Function 2: Accept Reservation (Owner approves; atomically decrements available_spaces)
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
        status = v_new_status
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


-- Function 3: Reject Reservation (Owner declines; frees car without touching capacity)
CREATE OR REPLACE FUNCTION public.reject_reservation(
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
BEGIN
    SELECT * INTO v_res FROM public.reservations WHERE id = p_reservation_id FOR UPDATE;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Reservation not found.');
    END IF;

    IF v_res.status != 'pending' THEN
        RETURN jsonb_build_object('success', false, 'error', 'Only pending reservations can be rejected. Current status: ' || v_res.status);
    END IF;

    SELECT * INTO v_space FROM public.parking_spaces WHERE id = v_res.parking_space_id;
    IF NOT FOUND OR v_space.owner_id IS NULL OR v_space.owner_id != p_owner_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not own this parking lot.');
    END IF;

    IF auth.uid() IS NOT NULL AND auth.uid() != p_owner_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: Unauthorized session.');
    END IF;

    UPDATE public.reservations
    SET status = 'rejected', updated_at = now()
    WHERE id = p_reservation_id;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Reservation request rejected. Vehicle released.'
    );
END;
$$;


-- Function 4: Cancel Reservation (Driver or Owner cancels; restores spot only if was confirmed)
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
    SELECT * INTO v_res FROM public.reservations WHERE id = p_reservation_id FOR UPDATE;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Reservation not found.');
    END IF;

    SELECT * INTO v_space FROM public.parking_spaces WHERE id = v_res.parking_space_id FOR UPDATE;

    -- Only the driver who made the reservation or the parking space owner can cancel
    IF v_res.user_id != p_user_id AND (v_space.owner_id IS NULL OR v_space.owner_id != p_user_id) THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not have permission to cancel this reservation.');
    END IF;

    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: Unauthorized session.');
    END IF;

    IF v_res.status NOT IN ('pending', 'confirmed') THEN
        RETURN jsonb_build_object('success', false, 'error', 'This reservation is already ' || v_res.status || '.');
    END IF;

    -- If reservation was confirmed, restore 1 spot (respecting 'closed' status)
    IF v_res.status = 'confirmed' AND v_space.id IS NOT NULL THEN
        v_new_avail := LEAST(v_space.capacity, v_space.available_spaces + 1);
        v_new_status := CASE WHEN v_space.status = 'full' AND v_new_avail > 0 THEN 'available' ELSE v_space.status END;

        UPDATE public.parking_spaces
        SET available_spaces = v_new_avail,
            status = v_new_status
        WHERE id = v_space.id;
    END IF;

    UPDATE public.reservations
    SET status = 'cancelled', updated_at = now()
    WHERE id = p_reservation_id;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Reservation cancelled successfully.'
    );
END;
$$;

-- ----------------------------------------------------------------------------
-- 4. FUNCTION EXECUTION PRIVILEGES
-- ----------------------------------------------------------------------------
REVOKE EXECUTE ON FUNCTION public.request_reservation(UUID, UUID, UUID, TEXT) FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.request_reservation(UUID, UUID, UUID, TEXT) TO authenticated;

REVOKE EXECUTE ON FUNCTION public.accept_reservation(UUID, UUID) FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.accept_reservation(UUID, UUID) TO authenticated;

REVOKE EXECUTE ON FUNCTION public.reject_reservation(UUID, UUID) FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.reject_reservation(UUID, UUID) TO authenticated;

REVOKE EXECUTE ON FUNCTION public.cancel_reservation(UUID, UUID) FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.cancel_reservation(UUID, UUID) TO authenticated;
