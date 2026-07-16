/**
 * Prescription Service
 *
 * CRUD operations for prescriptions.
 * All data is persisted in MySQL via the PHP API.
 */

import { get, post, del } from '../lib/apiClient';
import type { Prescription, Medication } from '../types';

export interface CreatePrescriptionData {
  patientId: number;
  visitId?: number | null;
  prescriptionDate?: string;
  diagnosis?: string | null;
  medications: Medication[];
  notes?: string | null;
}

export interface UpdatePrescriptionData extends Partial<CreatePrescriptionData> {
  id: number;
  patientId: number;
}

export const prescriptionService = {
  /** Get all prescriptions for a patient */
  async getByPatient(patientId: number): Promise<Prescription[]> {
    const result = await get<{ items: Prescription[] }>('/prescriptions/list.php', { patient_id: patientId });
    return result.items ?? [];
  },

  /** Get a single prescription by ID */
  async getById(id: number): Promise<Prescription | null> {
    try {
      return await get<Prescription>('/prescriptions/get.php', { id: String(id) });
    } catch {
      return null;
    }
  },

  /** Create a new prescription */
  async create(data: CreatePrescriptionData): Promise<Prescription> {
    return post<Prescription>('/prescriptions/create.php', {
      patient_id: data.patientId,
      visit_id: data.visitId,
      prescription_date: data.prescriptionDate,
      diagnosis: data.diagnosis,
      medications: JSON.stringify(data.medications),
      notes: data.notes,
    });
  },

  /** Update an existing prescription */
  async update(data: UpdatePrescriptionData): Promise<Prescription> {
    return post<Prescription>('/prescriptions/update.php', {
      id: data.id,
      patient_id: data.patientId,
      visit_id: data.visitId,
      prescription_date: data.prescriptionDate,
      diagnosis: data.diagnosis,
      medications: JSON.stringify(data.medications),
      notes: data.notes,
    });
  },

  /** Delete a prescription */
  async delete(id: number, patientId: number): Promise<void> {
    await del('/prescriptions/delete.php', { id });
  },

  /** Search prescriptions */
  async search(query: string): Promise<Prescription[]> {
    const result = await get<{ items: Prescription[] }>('/prescriptions/list.php', { search: query });
    return result.items ?? [];
  },
};
