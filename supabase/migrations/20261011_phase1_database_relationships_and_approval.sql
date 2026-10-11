-- ============================================================================
-- JodRod: Phase 1 Database Relationships, Ownership & Safe Migration
-- Migration: 20261011_phase1_database_relationships_and_approval.sql
-- 
-- Summary of Changes:
-- 1. Profiles: Preserves existing records; ensures unique 1:1 link to auth.users.
-- 2. Cars: Establishes public.cars table with composite unique constraint (id, user_id).
-- 3. Parking Spaces: Preserves legacy community lots (owner_id IS NULL) while
--    enforcing capacity bounds and owner-only mutation RLS for user lots.
-- 4. Reservations: Establishes public.reservations with:
--    - Mandatory car_id referencing public.cars.
--    - Database-level composite FK preventing User A from reserving User B's car.
--    - Partial unique index enforcing at most ONE active reservation per car.
--    - Owner approval workflow statuses ('pending', 'confirmed', 'rejected', 'cancelled', 'completed').
-- 5. Atomic Security-Definer Functions:
--    - request_reservation: Driver creates 'pending' booking (no spots cut yet).
--    - accept_reservation: Owner approves, cuts down 1 spot, flips to 'full' if 0 left.
--    - reject_reservation: Owner declines, frees car.
--    - cancel_reservation: Cancels and restores capacity if reservation was confirmed.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. PROFILES TABLE (1:1 with auth.users)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    avatar TEXT,
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Ensure user_id column exists
ALTER TABLE public.profiles ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE;

-- Enforce exactly 1 profile per authenticated user account (allowing legacy NULLs to exist safely)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'profiles_user_id_unique'
    ) THEN
        -- Only add unique constraint if duplicate non-null user_ids do not exist
        IF NOT EXISTS (
            SELECT user_id, COUNT(*) 
            FROM public.profiles 
            WHERE user_id IS NOT NULL 
            GROUP BY user_id 
            HAVING COUNT(*) > 1
        ) THEN
            ALTER TABLE public.profiles ADD CONSTRAINT profiles_user_id_unique UNIQUE (user_id);
        END IF;
    END IF;
END $$;

ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE pol RECORD;
BEGIN
    FOR pol IN SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = 'profiles'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.profiles', pol.policyname);
    END LOOP;
END $$;

CREATE POLICY "Public profiles are viewable by everyone" 
    ON public.profiles FOR SELECT TO anon, authenticated 
    USING (true);

CREATE POLICY "Users can insert own profile" 
    ON public.profiles FOR INSERT TO authenticated 
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update own profile" 
    ON public.profiles FOR UPDATE TO authenticated 
    USING (auth.uid() = user_id) 
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can delete own profile" 
    ON public.profiles FOR DELETE TO authenticated 
    USING (auth.uid() = user_id);


-- ----------------------------------------------------------------------------
-- 2. CARS TABLE (1 User owns many Cars)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.cars (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    license_plate TEXT NOT NULL,
    model TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT cars_user_plate_unique UNIQUE (user_id, license_plate),
    -- Composite unique constraint required for composite foreign key from reservations
    CONSTRAINT cars_id_user_id_unique UNIQUE (id, user_id)
);

-- If cars table already existed without cars_id_user_id_unique, ensure constraint exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'cars_id_user_id_unique'
    ) THEN
        ALTER TABLE public.cars ADD CONSTRAINT cars_id_user_id_unique UNIQUE (id, user_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_cars_user_id ON public.cars(user_id);
ALTER TABLE public.cars ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE pol RECORD;
BEGIN
    FOR pol IN SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = 'cars'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.cars', pol.policyname);
    END LOOP;
END $$;

CREATE POLICY "Users can select own cars" 
    ON public.cars FOR SELECT TO authenticated 
    USING (auth.uid() = user_id);

CREATE POLICY "Users can insert own cars" 
    ON public.cars FOR INSERT TO authenticated 
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update own cars" 
    ON public.cars FOR UPDATE TO authenticated 
    USING (auth.uid() = user_id) 
    WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can delete own cars" 
    ON public.cars FOR DELETE TO authenticated 
    USING (auth.uid() = user_id);


-- ----------------------------------------------------------------------------
-- 3. PARKING SPACES TABLE (1 User owns many Parking Lots; Legacy lots preserved)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.parking_spaces (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    name TEXT NOT NULL,
    latitude NUMERIC(9,6) NOT NULL,
    longitude NUMERIC(9,6) NOT NULL,
    capacity INTEGER NOT NULL CHECK (capacity >= 0),
    available_spaces INTEGER NOT NULL CHECK (available_spaces >= 0 AND available_spaces <= capacity),
    status TEXT NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'full', 'closed')),
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Add owner_id column if not present
ALTER TABLE public.parking_spaces ADD COLUMN IF NOT EXISTS owner_id UUID REFERENCES auth.users(id) ON DELETE SET NULL;

ALTER TABLE public.parking_spaces ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE pol RECORD;
BEGIN
    FOR pol IN SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = 'parking_spaces'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.parking_spaces', pol.policyname);
    END LOOP;
END $$;

-- Public can view all active and legacy community parking spaces
CREATE POLICY "Public can view parking spaces" 
    ON public.parking_spaces FOR SELECT TO anon, authenticated 
    USING (true);

-- Authenticated users can insert their own parking spaces
CREATE POLICY "Users can insert own parking spaces" 
    ON public.parking_spaces FOR INSERT TO authenticated 
    WITH CHECK (auth.uid() = owner_id);

-- Only lot owner can update; legacy community lots (owner_id IS NULL) are protected from alteration
CREATE POLICY "Owners can update own parking spaces" 
    ON public.parking_spaces FOR UPDATE TO authenticated 
    USING (owner_id IS NOT NULL AND auth.uid() = owner_id) 
    WITH CHECK (auth.uid() = owner_id);

-- Only lot owner can delete; legacy community lots (owner_id IS NULL) cannot be deleted
CREATE POLICY "Owners can delete own parking spaces" 
    ON public.parking_spaces FOR DELETE TO authenticated 
    USING (owner_id IS NOT NULL AND auth.uid() = owner_id);


-- ----------------------------------------------------------------------------
-- 4. RESERVATIONS TABLE (Capacity & Ownership Guaranteed by Database)
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
    -- Composite Foreign Key: Guarantees car_id belongs to the same user_id in public.cars!
    -- This makes it impossible for User A to reserve User B's car in PostgreSQL.
    CONSTRAINT fk_reservation_car_ownership FOREIGN KEY (car_id, user_id) 
        REFERENCES public.cars(id, user_id) ON DELETE CASCADE
);

-- Update status check constraint if table already existed
ALTER TABLE public.reservations DROP CONSTRAINT IF EXISTS reservations_status_check;
ALTER TABLE public.reservations ADD CONSTRAINT reservations_status_check 
    CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'completed'));

-- Add composite foreign key constraint if missing
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

CREATE INDEX IF NOT EXISTS idx_reservations_user_id ON public.reservations(user_id);
CREATE INDEX IF NOT EXISTS idx_reservations_space_id ON public.reservations(parking_space_id);
CREATE INDEX IF NOT EXISTS idx_reservations_car_id ON public.reservations(car_id);

-- Database constraint: A car can have at most ONE active reservation ('pending' or 'confirmed')
DROP INDEX IF EXISTS public.uq_active_reservation_per_car;
CREATE UNIQUE INDEX uq_active_reservation_per_car 
ON public.reservations (car_id) 
WHERE status IN ('pending', 'confirmed');

ALTER TABLE public.reservations ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE pol RECORD;
BEGIN
    FOR pol IN SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = 'reservations'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.reservations', pol.policyname);
    END LOOP;
END $$;

-- Driver can view their own reservations
CREATE POLICY "Drivers can view own reservations" 
    ON public.reservations FOR SELECT TO authenticated 
    USING (auth.uid() = user_id);

-- Parking lot owner can view reservations for their lots
CREATE POLICY "Owners can view reservations for their lots" 
    ON public.reservations FOR SELECT TO authenticated 
    USING (
        EXISTS (
            SELECT 1 FROM public.parking_spaces
            WHERE public.parking_spaces.id = public.reservations.parking_space_id
              AND public.parking_spaces.owner_id = auth.uid()
        )
    );

-- Drivers can request reservations for their own cars
CREATE POLICY "Drivers can insert own reservations" 
    ON public.reservations FOR INSERT TO authenticated 
    WITH CHECK (auth.uid() = user_id);

-- Drivers can cancel/update their own reservations
CREATE POLICY "Drivers can update own reservations" 
    ON public.reservations FOR UPDATE TO authenticated 
    USING (auth.uid() = user_id) 
    WITH CHECK (auth.uid() = user_id);

-- Parking lot owners can update reservations on their lots (to Accept or Reject)
CREATE POLICY "Owners can update reservations on their lots" 
    ON public.reservations FOR UPDATE TO authenticated 
    USING (
        EXISTS (
            SELECT 1 FROM public.parking_spaces
            WHERE public.parking_spaces.id = public.reservations.parking_space_id
              AND public.parking_spaces.owner_id = auth.uid()
        )
    )
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM public.parking_spaces
            WHERE public.parking_spaces.id = public.reservations.parking_space_id
              AND public.parking_spaces.owner_id = auth.uid()
        )
    );


-- ----------------------------------------------------------------------------
-- 5. ATOMIC STORED PROCEDURES (SECURITY DEFINER with Fixed Search Path)
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
    -- Verify caller matches user_id
    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You cannot request reservations for another account.');
    END IF;

    -- Verify car belongs to driver
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

    IF v_space.status != 'available' OR v_space.available_spaces <= 0 THEN
        RETURN jsonb_build_object('success', false, 'error', 'This parking lot is currently full or closed.');
    END IF;

    -- Create reservation in 'pending' status
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


-- Function 3: Reject Reservation (Owner declines; frees car without touching spots)
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


-- Function 4: Cancel Reservation (Driver or Owner cancels; restores spot if was confirmed)
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

    -- Only reserving driver or parking space owner can cancel
    IF v_res.user_id != p_user_id AND (v_space.owner_id IS NULL OR v_space.owner_id != p_user_id) THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not have permission to cancel this reservation.');
    END IF;

    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: Unauthorized session.');
    END IF;

    IF v_res.status NOT IN ('pending', 'confirmed') THEN
        RETURN jsonb_build_object('success', false, 'error', 'This reservation is already ' || v_res.status || '.');
    END IF;

    -- If reservation was confirmed, restore 1 spot
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
        'message', 'Reservation cancelled successfully.'
    );
END;
$$;
