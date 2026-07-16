/**
 * Visit Service
 *
 * CRUD operations for patient visits/encounters.
 * All data is persisted in MySQL via the PHP API.
 */

import { get, post, del } from '../lib/apiClient';
import type { Visit, VitalSigns } from '../types';

export interface CreateVisitData {
  patientId: number;
  visitDate?: string;
  chiefComplaint?: string;
  historyOfPresentIllness?: string | null;
  vitalSigns?: VitalSigns;
  physicalExamination?: string | null;
  diagnosis?: string | null;
  notes?: string | null;
  visitType?: string;
}

export interface UpdateVisitData extends Partial<CreateVisitData> {
  id: number;
  patientId: number;
}

export const visitService = {
  /** Get all visits for a patient */
  async getByPatient(patientId: number): Promise<Visit[]> {
    const result = await get<{ items: Visit[] }>('/visits/list.php', { patient_id: patientId });
    return result.items ?? [];
  },

  /** Get a single visit by ID */
  async getById(id: number): Promise<Visit | null> {
    try {
      return await get<Visit>('/visits/get.php', { id: String(id) });
    } catch {
      return null;
    }
  },

  /** Create a new visit */
  async create(data: CreateVisitData): Promise<Visit> {
    return post<Visit>('/visits/create.php', {
      patient_id: data.patientId,
      visit_date: data.visitDate,
      chief_complaint: data.chiefComplaint,
      history_of_present_illness: data.historyOfPresentIllness,
      vital_signs: data.vitalSigns ? JSON.stringify(data.vitalSigns) : null,
      physical_examination: data.physicalExamination,
      diagnosis: data.diagnosis,
      notes: data.notes,
      visit_type: data.visitType ?? 'outdoor',
    });
  },

  /** Update an existing visit */
  async update(data: UpdateVisitData): Promise<Visit> {
    return post<Visit>('/visits/update.php', {
      id: data.id,
      patient_id: data.patientId,
      visit_date: data.visitDate,
      chief_complaint: data.chiefComplaint,
      history_of_present_illness: data.historyOfPresentIllness,
      vital_signs: data.vitalSigns ? JSON.stringify(data.vitalSigns) : null,
      physical_examination: data.physicalExamination,
      diagnosis: data.diagnosis,
      notes: data.notes,
      visit_type: data.visitType,
    });
  },

  /** Delete a visit */
  async delete(id: number, patientId: number): Promise<void> {
    await del('/visits/delete.php', { id });
  },

  /** Search visits */
  async search(query: string): Promise<Visit[]> {
    const result = await get<{ items: Visit[] }>('/visits/list.php', { search: query });
    return result.items ?? [];
  },
};
