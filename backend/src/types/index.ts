export interface User {
  id: string;
  email: string;
  role: 'admin' | 'doctor' | 'user';
  createdAt: string;
  updatedAt: string;
}

export interface Patient {
  id: string;
  fullName: string;
  nameBn?: string;
  dateOfBirth?: string;
  gender: 'male' | 'female' | 'other';
  phone?: string;
  email?: string;
  address?: string;
  bloodGroup?: string;
  weight?: number;
  height?: number;
  allergies: string[];
  chronicConditions: string[];
  pastSurgicalHistory?: string;
  patientType: 'admitted' | 'outdoor';
  consultantEmail?: string;
  consultantName?: string;
  createdAt: string;
  updatedAt: string;
}

export interface VitalSigns {
  bloodPressure?: string;
  pulse?: string;
  temperature?: string;
  respiratoryRate?: string;
  oxygenSaturation?: string;
}

export interface Visit {
  id: string;
  patientId: string;
  visitDate: string;
  chiefComplaint: string;
  historyOfPresentIllness?: string;
  vitalSigns: VitalSigns;
  physicalExamination?: string;
  diagnosis?: string;
  notes?: string;
  visitType: 'admitted' | 'outdoor';
  createdAt: string;
  updatedAt: string;
}

export interface Medication {
  name: string;
  dose: string;
  frequency: string;
  duration: string;
  instructions: string;
}

export interface Prescription {
  id: string;
  patientId: string;
  visitId?: string;
  prescriptionDate: string;
  diagnosis?: string;
  medications: Medication[];
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

export interface UserProfile {
  id: string;
  userId: string;
  name: string;
  createdAt: string;
  updatedAt: string;
}

export interface AuthRequest extends Express.Request {
  user?: {
    id: string;
    email: string;
    role: string;
  };
}
