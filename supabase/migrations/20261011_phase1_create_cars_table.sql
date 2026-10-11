-- ============================================================================
-- JodRod Phase 1: Create My Cars Table
-- Migration: 20261011_phase1_create_cars_table.sql
-- 
-- Description:
-- Establishes the public.cars table for authenticated users to manage
-- their own vehicles.
-- 
-- Key Requirements Enforced:
-- 1. id UUID PRIMARY KEY DEFAULT gen_random_uuid()
-- 2. user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE
-- 3. license_plate TEXT NOT NULL
-- 4. model TEXT (optional vehicle description)
-- 5. created_at TIMESTAMPTZ NOT NULL DEFAULT now()
-- 6. updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
-- 7. UNIQUE (user_id, license_plate) - prevents duplicate plates for same user
-- 8. UNIQUE (id, user_id) - supports future composite foreign key from reservations
-- 9. Row Level Security (RLS) - authenticated users only access their own cars
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.cars (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    license_plate TEXT NOT NULL,
    model TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT cars_user_plate_unique UNIQUE (user_id, license_plate),
    CONSTRAINT cars_id_user_id_unique UNIQUE (id, user_id)
);

-- Ensure composite constraint exists if table already existed without it
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'cars_id_user_id_unique'
    ) THEN
        ALTER TABLE public.cars ADD CONSTRAINT cars_id_user_id_unique UNIQUE (id, user_id);
    END IF;
END $$;

-- Fast index for querying cars by user_id
CREATE INDEX IF NOT EXISTS idx_cars_user_id ON public.cars(user_id);

-- Enable Row Level Security (RLS)
ALTER TABLE public.cars ENABLE ROW LEVEL SECURITY;

-- Cleanly drop existing policies on public.cars to prevent duplicate policy errors
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

-- RLS Policies: Authenticated users can ONLY access their own cars
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
