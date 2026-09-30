-- ==========================================================
-- JodRod: Flood Parking Community
-- Supabase PostgreSQL Schema - Foundation Phase
-- ==========================================================

-- 1. Table: parking_spaces
-- Contains ONLY required columns: id, name, latitude, longitude, capacity, available_spaces, status
CREATE TABLE IF NOT EXISTS parking_spaces (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    latitude NUMERIC(9,6) NOT NULL,
    longitude NUMERIC(9,6) NOT NULL,
    capacity INTEGER NOT NULL CHECK (capacity >= 0),
    available_spaces INTEGER NOT NULL CHECK (available_spaces >= 0 AND available_spaces <= capacity),
    status TEXT NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'full', 'closed'))
);

-- 2. Enable Row Level Security (RLS)
ALTER TABLE parking_spaces ENABLE ROW LEVEL SECURITY;

-- 3. Row Level Security Policies for Prototype CRUD Operations
-- Allows safe public access for prototype testing via anon public key
CREATE POLICY "Allow public select on parking_spaces" 
    ON parking_spaces FOR SELECT 
    TO anon, authenticated 
    USING (true);

CREATE POLICY "Allow public insert on parking_spaces" 
    ON parking_spaces FOR INSERT 
    TO anon, authenticated 
    WITH CHECK (true);

CREATE POLICY "Allow public update on parking_spaces" 
    ON parking_spaces FOR UPDATE 
    TO anon, authenticated 
    USING (true)
    WITH CHECK (true);

CREATE POLICY "Allow public delete on parking_spaces" 
    ON parking_spaces FOR DELETE 
    TO anon, authenticated 
    USING (true);

-- ==========================================================
-- 2. Table: profiles
-- Contains ONLY required columns: id, name, avatar
-- ==========================================================
CREATE TABLE IF NOT EXISTS profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    avatar TEXT
);

-- Enable Row Level Security (RLS)
ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;

-- Row Level Security Policies for Prototype CRUD Operations
CREATE POLICY "Allow public select on profiles" 
    ON profiles FOR SELECT 
    TO anon, authenticated 
    USING (true);

CREATE POLICY "Allow public insert on profiles" 
    ON profiles FOR INSERT 
    TO anon, authenticated 
    WITH CHECK (true);

CREATE POLICY "Allow public update on profiles" 
    ON profiles FOR UPDATE 
    TO anon, authenticated 
    USING (true)
    WITH CHECK (true);

CREATE POLICY "Allow public delete on profiles" 
    ON profiles FOR DELETE 
    TO anon, authenticated 
    USING (true);
