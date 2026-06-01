-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  email VARCHAR(255) UNIQUE NOT NULL,
  role VARCHAR(50) DEFAULT 'user' CHECK (role IN ('admin', 'doctor', 'user')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patients table
CREATE TABLE IF NOT EXISTS patients (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  full_name VARCHAR(255) NOT NULL,
  name_bn VARCHAR(255),
  date_of_birth DATE,
  gender VARCHAR(50) CHECK (gender IN ('male', 'female', 'other')),
  phone VARCHAR(20),
  email VARCHAR(255),
  address TEXT,
  blood_group VARCHAR(10),
  weight NUMERIC(5,2),
  height NUMERIC(5,2),
  allergies TEXT[] DEFAULT ARRAY[]::TEXT[],
  chronic_conditions TEXT[] DEFAULT ARRAY[]::TEXT[],
  past_surgical_history TEXT,
  patient_type VARCHAR(50) CHECK (patient_type IN ('admitted', 'outdoor')),
  consultant_email VARCHAR(255),
  consultant_name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Visits table
CREATE TABLE IF NOT EXISTS visits (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  patient_id UUID NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
  visit_date TIMESTAMP NOT NULL,
  chief_complaint VARCHAR(255) NOT NULL,
  history_of_present_illness TEXT,
  vital_signs JSONB DEFAULT '{}'::JSONB,
  physical_examination TEXT,
  diagnosis TEXT,
  notes TEXT,
  visit_type VARCHAR(50) CHECK (visit_type IN ('admitted', 'outdoor')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Prescriptions table
CREATE TABLE IF NOT EXISTS prescriptions (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  patient_id UUID NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
  visit_id UUID REFERENCES visits(id) ON DELETE SET NULL,
  prescription_date TIMESTAMP NOT NULL,
  diagnosis TEXT,
  medications JSONB DEFAULT '[]'::JSONB,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Profiles table
CREATE TABLE IF NOT EXISTS user_profiles (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_patients_consultant_email ON patients(consultant_email);
CREATE INDEX IF NOT EXISTS idx_visits_patient_id ON visits(patient_id);
CREATE INDEX IF NOT EXISTS idx_visits_visit_date ON visits(visit_date);
CREATE INDEX IF NOT EXISTS idx_prescriptions_patient_id ON prescriptions(patient_id);
CREATE INDEX IF NOT EXISTS idx_prescriptions_visit_id ON prescriptions(visit_id);
CREATE INDEX IF NOT EXISTS idx_user_profiles_user_id ON user_profiles(user_id);
