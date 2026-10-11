-- ==========================================================
-- Migration: 20261011_supabase_only_reservations_and_cars.sql
-- Description: Establishes Supabase PostgreSQL as the ONLY source of truth.
-- 1. Creates public.cars table (linked to auth.users).
-- 2. Creates public.reservations table (linked to auth.users, public.cars, public.parking_spaces).
-- 3. Enforces that each car can have at most ONE active reservation ('confirmed').
-- 4. Configures Row Level Security (RLS) on public.cars and public.reservations.
-- 5. Implements atomic PostgreSQL functions with SECURITY DEFINER and fixed search_path.
-- ==========================================================

-- ----------------------------------------------------------
-- 1. Table: public.cars
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.cars (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    license_plate TEXT NOT NULL,
    model TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT cars_user_plate_unique UNIQUE (user_id, license_plate)
);

CREATE INDEX IF NOT EXISTS idx_cars_user_id ON public.cars(user_id);

-- Enable Row Level Security (RLS) on public.cars
ALTER TABLE public.cars ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE
    pol RECORD;
BEGIN
    FOR pol IN 
        SELECT policyname 
        FROM pg_policies 
        WHERE schemaname = 'public' AND tablename = 'cars'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.cars', pol.policyname);
    END LOOP;
END $$;

-- Policies for public.cars: Users manage strictly their own cars
CREATE POLICY "Users can select own cars" 
    ON public.cars FOR SELECT 
    TO authenticated 
    USING (auth.uid() = user_id);

CREATE POLICY "Users can insert own cars" 
    ON public.cars FOR INSERT 
    TO authenticated 
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update own cars" 
    ON public.cars FOR UPDATE 
    TO authenticated 
    USING (auth.uid() = user_id) 
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can delete own cars" 
    ON public.cars FOR DELETE 
    TO authenticated 
    USING (auth.uid() = user_id);

-- ----------------------------------------------------------
-- 2. Table: public.reservations
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.reservations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    car_id UUID REFERENCES public.cars(id) ON DELETE SET NULL,
    parking_space_id UUID NOT NULL REFERENCES public.parking_spaces(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed', 'cancelled', 'completed')),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Ensure car_id column exists if reservations table was created previously
ALTER TABLE public.reservations 
    ADD COLUMN IF NOT EXISTS car_id UUID REFERENCES public.cars(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_reservations_user_id ON public.reservations(user_id);
CREATE INDEX IF NOT EXISTS idx_reservations_parking_space_id ON public.reservations(parking_space_id);
CREATE INDEX IF NOT EXISTS idx_reservations_car_id ON public.reservations(car_id);

-- Database-enforced rule: Each car can have at most ONE active reservation at a time
CREATE UNIQUE INDEX IF NOT EXISTS uq_active_reservation_per_car 
ON public.reservations (car_id) 
WHERE status = 'confirmed' AND car_id IS NOT NULL;

-- Enable Row Level Security (RLS) on public.reservations
ALTER TABLE public.reservations ENABLE ROW LEVEL SECURITY;

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

-- ----------------------------------------------------------
-- 3. Atomic Database Function: Create Reservation with Capacity Decrement
-- Secured with SECURITY DEFINER and fixed search_path = public, pg_temp
-- ----------------------------------------------------------
CREATE OR REPLACE FUNCTION public.create_reservation(
    p_parking_space_id UUID,
    p_user_id UUID,
    p_car_id UUID DEFAULT NULL,
    p_notes TEXT DEFAULT NULL
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_space RECORD;
    v_res RECORD;
    v_car RECORD;
    v_new_avail INTEGER;
    v_new_status TEXT;
BEGIN
    -- 1. Authorization check: if authenticated session is present, prevent spoofing p_user_id
    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You cannot reserve spots for another account.');
    END IF;

    -- 2. Validate car ownership and car active reservation status if car_id is provided
    IF p_car_id IS NOT NULL THEN
        SELECT * INTO v_car FROM public.cars WHERE id = p_car_id AND user_id = p_user_id;
        IF NOT FOUND THEN
            RETURN jsonb_build_object('success', false, 'error', 'Specified vehicle not found or does not belong to you.');
        END IF;

        IF EXISTS (
            SELECT 1 FROM public.reservations
            WHERE car_id = p_car_id AND status = 'confirmed'
        ) THEN
            RETURN jsonb_build_object('success', false, 'error', 'This vehicle already has an active reservation.');
        END IF;
    END IF;

    -- 3. Row-level lock on parking space to prevent race conditions and over-booking
    SELECT * INTO v_space
    FROM public.parking_spaces
    WHERE id = p_parking_space_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Parking space not found with the specified ID.');
    END IF;

    -- 4. Verify capacity and status
    IF v_space.status != 'available' OR v_space.available_spaces <= 0 THEN
        RETURN jsonb_build_object('success', false, 'error', 'This parking space is currently full or closed.');
    END IF;

    -- 5. If no car_id is supplied, prevent duplicate active reservation by the same user for this lot
    IF p_car_id IS NULL AND EXISTS (
        SELECT 1 FROM public.reservations
        WHERE user_id = p_user_id 
          AND parking_space_id = p_parking_space_id 
          AND status = 'confirmed'
    ) THEN
        RETURN jsonb_build_object('success', false, 'error', 'You already have an active reservation for this parking lot.');
    END IF;

    -- 6. Insert reservation record
    INSERT INTO public.reservations (user_id, car_id, parking_space_id, status, notes)
    VALUES (p_user_id, p_car_id, p_parking_space_id, 'confirmed', p_notes)
    RETURNING * INTO v_res;

    -- 7. Atomically decrement availability and update status if full
    v_new_avail := v_space.available_spaces - 1;
    v_new_status := CASE WHEN v_new_avail = 0 THEN 'full' ELSE 'available' END;

    UPDATE public.parking_spaces
    SET available_spaces = v_new_avail,
        status = v_new_status
    WHERE id = p_parking_space_id;

    RETURN jsonb_build_object(
        'success', true,
        'message', 'Parking spot reserved successfully in Supabase!',
        'reservation', row_to_json(v_res),
        'parking_space', jsonb_build_object(
            'id', v_space.id,
            'name', v_space.name,
            'available_spaces', v_new_avail,
            'status', v_new_status
        )
    );
END;
$$;

-- ----------------------------------------------------------
-- 4. Atomic Database Function: Cancel Reservation with Capacity Increment
-- Secured with SECURITY DEFINER and fixed search_path = public, pg_temp
-- ----------------------------------------------------------
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
    -- 1. Authorization check
    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You cannot cancel another user''s reservation.');
    END IF;

    -- 2. Find and lock reservation record
    SELECT * INTO v_res
    FROM public.reservations
    WHERE id = p_reservation_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Reservation record not found.');
    END IF;

    -- 3. Verify reservation ownership
    IF v_res.user_id != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not have permission to cancel this reservation.');
    END IF;

    IF v_res.status != 'confirmed' THEN
        RETURN jsonb_build_object('success', false, 'error', 'This reservation is already ' || v_res.status || '.');
    END IF;

    -- 4. Update reservation status to cancelled
    UPDATE public.reservations
    SET status = 'cancelled',
        updated_at = now()
    WHERE id = p_reservation_id;

    -- 5. Row-level lock and increment space availability
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
        'message', 'Reservation cancelled successfully in Supabase. Spot restored to available parking.',
        'reservation_id', p_reservation_id,
        'parking_space_id', v_res.parking_space_id
    );
END;
$$;
