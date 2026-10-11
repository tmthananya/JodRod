-- ==========================================================
-- Migration: 20261011_harden_ownership_policies.sql
-- Description: Harden Row Level Security (RLS) on public.parking_spaces and public.profiles.
-- Purges all legacy permissive policies dynamically and recreates strict, user-scoped owner policies.
-- ==========================================================

-- ----------------------------------------------------------
-- 1. Table: public.parking_spaces
-- ----------------------------------------------------------

-- Ensure Row Level Security is active
ALTER TABLE public.parking_spaces ENABLE ROW LEVEL SECURITY;

-- Dynamically drop ALL existing policies on parking_spaces regardless of policy names
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
    ON public.parking_spaces FOR SELECT 
    TO anon, authenticated 
    USING (true);

-- Policy 2: Authenticated INSERT (owner_id must match authenticated user)
CREATE POLICY "Allow authenticated insert own parking_spaces" 
    ON public.parking_spaces FOR INSERT 
    TO authenticated 
    WITH CHECK (auth.uid() = owner_id);

-- Policy 3: Authenticated UPDATE (only the space owner; community spaces with owner_id IS NULL are protected)
CREATE POLICY "Allow authenticated update own parking_spaces" 
    ON public.parking_spaces FOR UPDATE 
    TO authenticated 
    USING (owner_id IS NOT NULL AND auth.uid() = owner_id)
    WITH CHECK (auth.uid() = owner_id);

-- Policy 4: Authenticated DELETE (only the space owner; community spaces with owner_id IS NULL are protected)
CREATE POLICY "Allow authenticated delete own parking_spaces" 
    ON public.parking_spaces FOR DELETE 
    TO authenticated 
    USING (owner_id IS NOT NULL AND auth.uid() = owner_id);

-- ----------------------------------------------------------
-- 2. Table: public.profiles
-- ----------------------------------------------------------

-- Ensure Row Level Security is active
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

-- Dynamically drop ALL existing policies on profiles regardless of policy names
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

-- Policy 1: Public SELECT (anyone can view community member profiles)
CREATE POLICY "Allow public select on profiles" 
    ON public.profiles FOR SELECT 
    TO anon, authenticated 
    USING (true);

-- Policy 2: Authenticated INSERT (user_id must match authenticated user)
CREATE POLICY "Allow authenticated insert own profile" 
    ON public.profiles FOR INSERT 
    TO authenticated 
    WITH CHECK (auth.uid() = user_id);

-- Policy 3: Authenticated UPDATE (only the profile owner)
CREATE POLICY "Allow authenticated update own profile" 
    ON public.profiles FOR UPDATE 
    TO authenticated 
    USING (auth.uid() = user_id)
    WITH CHECK (auth.uid() = user_id);

-- Policy 4: Authenticated DELETE (only the profile owner)
CREATE POLICY "Allow authenticated delete own profile" 
    ON public.profiles FOR DELETE 
    TO authenticated 
    USING (auth.uid() = user_id);
