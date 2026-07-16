/**
 * Patient Service
 *
 * CRUD operations for patient records.
 * All data is persisted in MySQL via the PHP API.
 */

import { get, post, del } from '../lib/apiClient';
import type { Patient } from '../types';

export interface CreatePatientData {
  fullName: string;
  nameBn?: string | null;
  dateOfBirth?: string | null;
  gender?: string;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  bloodGroup?: string | null;
  weight?: number | null;
  height?: number | null;
  allergies?: string[];
  chronicConditions?: string[];
  pastSurgicalHistory?: string | null;
  patientType?: string;
  photo?: string | null;
}

export interface UpdatePatientData extends Partial<CreatePatientData> {
  id: number;
}

export const patientService = {
  /** Get all patients (with optional limit) */
  async getAll(limit = 1000): Promise<Patient[]> {
    const result = await get<{ items: Patient[] }>('/patients/list.php', { limit });
    return result.items ?? [];
  },

  /** Search patients by query */
  async search(query: string): Promise<Patient[]> {
    const result = await get<{ items: Patient[] }>('/patients/list.php', { search: query, limit: 50 });
    return result.items ?? [];
  },

  /** Get a single patient by ID */
  async getById(id: number): Promise<Patient | null> {
    try {
      return await get<Patient>('/patients/get.php', { id: String(id) });
    } catch {
      return null;
    }
  },

  /** Create a new patient */
  async create(data: CreatePatientData): Promise<Patient> {
    return post<Patient>('/patients/create.php', {
      full_name: data.fullName,
      name_bn: data.nameBn,
      date_of_birth: data.dateOfBirth,
      gender: data.gender ?? 'male',
      phone: data.phone,
      email: data.email,
      address: data.address,
      blood_group: data.bloodGroup,
      weight: data.weight,
      height: data.height,
      allergies: data.allergies ?? [],
      chronic_conditions: data.chronicConditions ?? [],
      past_surgical_history: data.pastSurgicalHistory,
      patient_type: data.patientType ?? 'outdoor',
      photo: data.photo,
    });
  },

  /** Update an existing patient */
  async update(data: UpdatePatientData): Promise<Patient> {
    return post<Patient>('/patients/update.php', {
      id: data.id,
      full_name: data.fullName,
      name_bn: data.nameBn,
      date_of_birth: data.dateOfBirth,
      gender: data.gender,
      phone: data.phone,
      email: data.email,
      address: data.address,
      blood_group: data.bloodGroup,
      weight: data.weight,
      height: data.height,
      allergies: data.allergies,
      chronic_conditions: data.chronicConditions,
      past_surgical_history: data.pastSurgicalHistory,
      patient_type: data.patientType,
      photo: data.photo,
    });
  },

  /** Delete a patient */
  async delete(id: number): Promise<void> {
    await del('/patients/delete.php', { id });
  },
};
