-- ==========================================================
-- Migration: 20261011_create_reservations_system.sql
-- Description: Creates public.reservations table, RLS policies,
-- and atomic database-side functions for reservation creation and cancellation.
-- ==========================================================

-- 1. Create public.reservations table
CREATE TABLE IF NOT EXISTS public.reservations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    parking_space_id UUID NOT NULL REFERENCES public.parking_spaces(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed', 'cancelled', 'completed')),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Index for quick lookup by user and space
CREATE INDEX IF NOT EXISTS idx_reservations_user_id ON public.reservations(user_id);
CREATE INDEX IF NOT EXISTS idx_reservations_parking_space_id ON public.reservations(parking_space_id);

-- 2. Enable Row Level Security (RLS) on public.reservations
ALTER TABLE public.reservations ENABLE ROW LEVEL SECURITY;

-- Dynamically drop existing policies on reservations to ensure clean rerun
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

-- Policy 1: Users can view ONLY their own reservations
CREATE POLICY "Users can select own reservations" 
    ON public.reservations FOR SELECT 
    TO authenticated 
    USING (auth.uid() = user_id);

-- Policy 2: Users can insert their own reservations
CREATE POLICY "Users can insert own reservations" 
    ON public.reservations FOR INSERT 
    TO authenticated 
    WITH CHECK (auth.uid() = user_id);

-- Policy 3: Users can update their own reservations
CREATE POLICY "Users can update own reservations" 
    ON public.reservations FOR UPDATE 
    TO authenticated 
    USING (auth.uid() = user_id)
    WITH CHECK (auth.uid() = user_id);

-- Policy 4: Space owners can view reservations made for their parking spaces
CREATE POLICY "Space owners can view reservations for their spaces"
    ON public.reservations FOR SELECT
    TO authenticated
    USING (
        EXISTS (
            SELECT 1 FROM public.parking_spaces
            WHERE public.parking_spaces.id = public.reservations.parking_space_id
              AND public.parking_spaces.owner_id = auth.uid()
        )
    );

-- 3. Atomic Database Function: Create Reservation with Capacity Decrement
CREATE OR REPLACE FUNCTION public.create_reservation(
    p_parking_space_id UUID,
    p_user_id UUID,
    p_notes TEXT DEFAULT NULL
)
RETURNS JSONB AS $$
DECLARE
    v_space RECORD;
    v_res RECORD;
    v_new_avail INTEGER;
    v_new_status TEXT;
BEGIN
    -- 1. Row-level lock on parking space to prevent race conditions and over-booking
    SELECT * INTO v_space
    FROM public.parking_spaces
    WHERE id = p_parking_space_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Parking space not found with the specified ID.');
    END IF;

    -- 2. Verify availability and status
    IF v_space.status != 'available' OR v_space.available_spaces <= 0 THEN
        RETURN jsonb_build_object('success', false, 'error', 'This parking space is currently full or closed.');
    END IF;

    -- 3. Prevent duplicate active reservation for the same lot by the same user
    IF EXISTS (
        SELECT 1 FROM public.reservations
        WHERE user_id = p_user_id 
          AND parking_space_id = p_parking_space_id 
          AND status = 'confirmed'
    ) THEN
        RETURN jsonb_build_object('success', false, 'error', 'You already have an active reservation for this parking lot.');
    END IF;

    -- 4. Insert reservation record
    INSERT INTO public.reservations (user_id, parking_space_id, status, notes)
    VALUES (p_user_id, p_parking_space_id, 'confirmed', p_notes)
    RETURNING * INTO v_res;

    -- 5. Atomically decrement availability and update status if full
    v_new_avail := v_space.available_spaces - 1;
    v_new_status := CASE WHEN v_new_avail = 0 THEN 'full' ELSE 'available' END;

    UPDATE public.parking_spaces
    SET available_spaces = v_new_avail,
        status = v_new_status
    WHERE id = p_parking_space_id;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Parking spot reserved successfully!',
        'reservation', row_to_json(v_res),
        'parking_space', jsonb_build_object(
            'id', v_space.id,
            'name', v_space.name,
            'available_spaces', v_new_avail,
            'status', v_new_status
        )
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 4. Atomic Database Function: Cancel Reservation with Capacity Increment
CREATE OR REPLACE FUNCTION public.cancel_reservation(
    p_reservation_id UUID,
    p_user_id UUID
)
RETURNS JSONB AS $$
DECLARE
    v_res RECORD;
    v_space RECORD;
    v_new_avail INTEGER;
    v_new_status TEXT;
BEGIN
    -- 1. Find and lock reservation record
    SELECT * INTO v_res
    FROM public.reservations
    WHERE id = p_reservation_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Reservation record not found.');
    END IF;

    -- 2. Authorization check: Must be the user who reserved it
    IF v_res.user_id != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not have permission to cancel this reservation.');
    END IF;

    IF v_res.status != 'confirmed' THEN
        RETURN jsonb_build_object('success', false, 'error', 'This reservation is already ' || v_res.status || '.');
    END IF;

    -- 3. Update reservation status to cancelled
    UPDATE public.reservations
    SET status = 'cancelled',
        updated_at = now()
    WHERE id = p_reservation_id;

    -- 4. Row-level lock and increment space availability
    SELECT * INTO v_space
    FROM public.parking_spaces
    WHERE id = v_res.parking_space_id
    FOR UPDATE;

    IF FOUND THEN
        v_new_avail := LEAST(v_space.capacity, v_space.available_spaces + 1);
        v_new_status := CASE WHEN v_space.status = 'full' AND v_new_avail > 0 THEN 'available' ELSE v_space.status END;

        UPDATE public.parking_spaces
        SET available_spaces = v_new_avail,
            status = v_new_status
        WHERE id = v_res.parking_space_id;
    END IF;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Reservation cancelled successfully. Spot restored to available parking.',
        'reservation_id', p_reservation_id,
        'parking_space_id', v_res.parking_space_id
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
