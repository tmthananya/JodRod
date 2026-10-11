-- ==========================================================
-- JodRod: Flood Parking Community
-- Supabase PostgreSQL Schema - Foundation Phase
-- ==========================================================

-- 1. Table: parking_spaces
CREATE TABLE IF NOT EXISTS parking_spaces (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    name TEXT NOT NULL,
    latitude NUMERIC(9,6) NOT NULL,
    longitude NUMERIC(9,6) NOT NULL,
    capacity INTEGER NOT NULL CHECK (capacity >= 0),
    available_spaces INTEGER NOT NULL CHECK (available_spaces >= 0 AND available_spaces <= capacity),
    status TEXT NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'full', 'closed'))
);

-- Ensure owner_id column exists if table was created earlier
ALTER TABLE parking_spaces 
    ADD COLUMN IF NOT EXISTS owner_id UUID REFERENCES auth.users(id) ON DELETE SET NULL;

-- 2. Enable Row Level Security (RLS) on parking_spaces
ALTER TABLE parking_spaces ENABLE ROW LEVEL SECURITY;

-- Dynamically drop ALL existing policies on parking_spaces regardless of their names
DO $$
DECLARE
    pol RECORD;
BEGIN
    FOR pol IN 
        SELECT policyname 
        FROM pg_policies 
        WHERE schemaname = 'public' AND tablename = 'parking_spaces'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.parking_spaces', pol.policyname);
    END LOOP;
END $$;

-- Policy 1: Public SELECT (anyone can view parking spaces on the map)
CREATE POLICY "Allow public select on parking_spaces" 
    ON parking_spaces FOR SELECT 
    TO anon, authenticated 
    USING (true);

-- Policy 2: Authenticated INSERT (owner_id must match authenticated user)
CREATE POLICY "Allow authenticated insert own parking_spaces" 
    ON parking_spaces FOR INSERT 
    TO authenticated 
    WITH CHECK (auth.uid() = owner_id);

-- Policy 3: Authenticated UPDATE (only the space owner; community spaces with owner_id IS NULL are protected)
CREATE POLICY "Allow authenticated update own parking_spaces" 
    ON parking_spaces FOR UPDATE 
    TO authenticated 
    USING (owner_id IS NOT NULL AND auth.uid() = owner_id)
    WITH CHECK (auth.uid() = owner_id);

-- Policy 4: Authenticated DELETE (only the space owner; community spaces with owner_id IS NULL are protected)
CREATE POLICY "Allow authenticated delete own parking_spaces" 
    ON parking_spaces FOR DELETE 
    TO authenticated 
    USING (owner_id IS NOT NULL AND auth.uid() = owner_id);

-- ==========================================================
-- 2. Table: profiles
-- Contains: id, user_id (unique 1-to-1 with auth account), name, avatar
-- ==========================================================
CREATE TABLE IF NOT EXISTS profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    avatar TEXT
);

-- Ensure user_id column exists if table was created earlier
ALTER TABLE profiles 
    ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE;

-- Unique constraint ensuring 1 profile per authenticated account
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'profiles_user_id_unique'
    ) THEN
        ALTER TABLE profiles ADD CONSTRAINT profiles_user_id_unique UNIQUE (user_id);
    END IF;
END $$;

-- Enable Row Level Security (RLS) on profiles
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;

-- Dynamically drop ALL existing policies on profiles regardless of their names
DO $$
DECLARE
    pol RECORD;
BEGIN
    FOR pol IN 
        SELECT policyname 
        FROM pg_policies 
        WHERE schemaname = 'public' AND tablename = 'profiles'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.profiles', pol.policyname);
    END LOOP;
END $$;

-- Policy 1: Public SELECT (anyone can view community profiles)
CREATE POLICY "Allow public select on profiles" 
    ON profiles FOR SELECT 
    TO anon, authenticated 
    USING (true);

-- Policy 2: Authenticated INSERT (user_id must match authenticated user)
CREATE POLICY "Allow authenticated insert own profile" 
    ON profiles FOR INSERT 
    TO authenticated 
    WITH CHECK (auth.uid() = user_id);

-- Policy 3: Authenticated UPDATE (only the profile owner)
CREATE POLICY "Allow authenticated update own profile" 
    ON profiles FOR UPDATE 
    TO authenticated 
    USING (auth.uid() = user_id)
    WITH CHECK (auth.uid() = user_id);

-- Policy 4: Authenticated DELETE (only the profile owner)
CREATE POLICY "Allow authenticated delete own profile" 
    ON profiles FOR DELETE 
    TO authenticated 
    USING (auth.uid() = user_id);

-- ==========================================================
-- 3. Authentication: Auto-Confirm Trigger for Development
-- If email confirmation is enabled in your Supabase project,
-- running this script in Supabase SQL Editor allows new sign-ups
-- to log in immediately with email & password without SMTP verification.
-- (Alternatively: In Supabase Dashboard -> Authentication -> Providers -> Email -> Turn OFF "Confirm email")
-- ==========================================================
CREATE OR REPLACE FUNCTION public.handle_new_auth_user()
RETURNS TRIGGER AS $$
BEGIN
  NEW.email_confirmed_at = now();
  NEW.confirmed_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

DROP TRIGGER IF EXISTS on_auth_user_created_auto_confirm ON auth.users;
CREATE TRIGGER on_auth_user_created_auto_confirm
  BEFORE INSERT ON auth.users
  FOR EACH ROW
  EXECUTE FUNCTION public.handle_new_auth_user();

-- Auto-confirm any users currently awaiting confirmation
UPDATE auth.users 
SET email_confirmed_at = COALESCE(email_confirmed_at, now()), 
    confirmed_at = COALESCE(confirmed_at, now()) 
WHERE email_confirmed_at IS NULL;

-- ==========================================================
-- 4. Table: cars
-- Allows each authenticated user to own and manage multiple vehicles.
-- ==========================================================
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
ALTER TABLE public.cars ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE pol RECORD;
BEGIN
    FOR pol IN SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = 'cars'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.cars', pol.policyname);
    END LOOP;
END $$;

CREATE POLICY "Users can select own cars" ON public.cars FOR SELECT TO authenticated USING (auth.uid() = user_id);
CREATE POLICY "Users can insert own cars" ON public.cars FOR INSERT TO authenticated WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users can update own cars" ON public.cars FOR UPDATE TO authenticated USING (auth.uid() = user_id) WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users can delete own cars" ON public.cars FOR DELETE TO authenticated USING (auth.uid() = user_id);

-- ==========================================================
-- 5. Table: reservations
-- Links user, car, and parking space. Enforces that each car can have
-- at most one active ('confirmed') reservation at a time.
-- ==========================================================
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

ALTER TABLE public.reservations ADD COLUMN IF NOT EXISTS car_id UUID REFERENCES public.cars(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_reservations_user_id ON public.reservations(user_id);
CREATE INDEX IF NOT EXISTS idx_reservations_parking_space_id ON public.reservations(parking_space_id);
CREATE INDEX IF NOT EXISTS idx_reservations_car_id ON public.reservations(car_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_active_reservation_per_car 
ON public.reservations (car_id) 
WHERE status = 'confirmed' AND car_id IS NOT NULL;

ALTER TABLE public.reservations ENABLE ROW LEVEL SECURITY;

DO $$
DECLARE pol RECORD;
BEGIN
    FOR pol IN SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = 'reservations'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS %I ON public.reservations', pol.policyname);
    END LOOP;
END $$;

CREATE POLICY "Users can select own reservations" ON public.reservations FOR SELECT TO authenticated USING (auth.uid() = user_id);
CREATE POLICY "Users can insert own reservations" ON public.reservations FOR INSERT TO authenticated WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users can update own reservations" ON public.reservations FOR UPDATE TO authenticated USING (auth.uid() = user_id) WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Space owners can view reservations for their spaces" ON public.reservations FOR SELECT TO authenticated USING (
    EXISTS (
        SELECT 1 FROM public.parking_spaces
        WHERE public.parking_spaces.id = public.reservations.parking_space_id
          AND public.parking_spaces.owner_id = auth.uid()
    )
);

-- ==========================================================
-- 6. Atomic Stored Functions: create_reservation & cancel_reservation
-- ==========================================================
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
    IF auth.uid() IS NOT NULL AND auth.uid() != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You cannot reserve spots for another account.');
    END IF;

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

    SELECT * INTO v_space
    FROM public.parking_spaces
    WHERE id = p_parking_space_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RETURN jsonb_build_object('success', false, 'error', 'Parking space not found with the specified ID.');
    END IF;

    IF v_space.status != 'available' OR v_space.available_spaces <= 0 THEN
        RETURN jsonb_build_object('success', false, 'error', 'This parking space is currently full or closed.');
    END IF;

    IF p_car_id IS NULL AND EXISTS (
        SELECT 1 FROM public.reservations
        WHERE user_id = p_user_id AND parking_space_id = p_parking_space_id AND status = 'confirmed'
    ) THEN
        RETURN jsonb_build_object('success', false, 'error', 'You already have an active reservation for this parking lot.');
    END IF;

    INSERT INTO public.reservations (user_id, car_id, parking_space_id, status, notes)
    VALUES (p_user_id, p_car_id, p_parking_space_id, 'confirmed', p_notes)
    RETURNING * INTO v_res;

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

    IF v_res.user_id != p_user_id THEN
        RETURN jsonb_build_object('success', false, 'error', 'Forbidden: You do not have permission to cancel this reservation.');
    END IF;

    IF v_res.status != 'confirmed' THEN
        RETURN jsonb_build_object('success', false, 'error', 'This reservation is already ' || v_res.status || '.');
    END IF;

    UPDATE public.reservations
    SET status = 'cancelled',
        updated_at = now()
    WHERE id = p_reservation_id;

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

