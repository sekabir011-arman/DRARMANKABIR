/**
 * Visit Service
 *
 * CRUD operations for patient visits/encounters.
 * All data is persisted in MySQL via the PHP API.
 */

import { get, post, del } from '../lib/apiClient';
import type { Visit, VitalSigns } from '../types';
import { mapListFromApi, mapFromApi, type Mapping, toNumber, parseJsonObject } from '../lib/mappers';

// ── Visit API mapping: camelCase DTO ↔ snake_case API ───────────────────

const visitMapping: Mapping<Visit> = {
  id: 'id',
  patientId: 'patient_id',
  visitDate: 'visit_date',
  chiefComplaint: 'chief_complaint',
  historyOfPresentIllness: 'history_of_present_illness',
  vitalSigns: 'vital_signs',
  physicalExamination: 'physical_examination',
  diagnosis: 'diagnosis',
  notes: 'notes',
  visitType: 'visit_type',
  createdAt: 'created_at',
};

const visitTransforms = {
  id: (v: any) => toNumber(v) ?? ,
  patientId: (v: any) => toNumber(v) ?? ,
  vitalSigns: (v: any) => parseJsonObject(v) as VitalSigns | null,
};

// ── Request DTOs ─────────────────────────────────────────────────────────

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
    const result = await get<{ items: Record<string, any>[] }>('/visits/list.php', { patient_id: String(patientId) });
    return mapListFromApi<Visit>(result.items, visitMapping, visitTransforms);
  },

  /** Get a single visit by ID */
  async getById(id: number): Promise<Visit | null> {
    try {
      const result = await get<Record<string, any>>('/visits/get.php', { id: String(id) });
      return mapFromApi<Visit>(result, visitMapping, visitTransforms);
    } catch {
      return null;
    }
  },

  /** Create a new visit — PHP reads camelCase */
  async create(data: CreateVisitData): Promise<Visit> {
    const payload: Record<string, any> = {
      patientId: data.patientId,
      visitType: data.visitType ?? 'outpatient',
      visitDate: data.visitDate,
      chiefComplaint: data.chiefComplaint,
      historyOfPresentIllness: data.historyOfPresentIllness,
      vitalSigns: data.vitalSigns,
      physicalExamination: data.physicalExamination,
      diagnosis: data.diagnosis,
      notes: data.notes,
    };
    const result = await post<Record<string, any>>('/visits/create.php', payload);
    return mapFromApi<Visit>(result, visitMapping, visitTransforms)!;
  },

  /** Update an existing visit — PHP reads camelCase */
  async update(data: UpdateVisitData): Promise<Visit> {
    const payload: Record<string, any> = { id: data.id };
    if (data.patientId !== undefined) payload.patientId = data.patientId;
    if (data.visitDate !== undefined) payload.visitDate = data.visitDate;
    if (data.visitType !== undefined) payload.visitType = data.visitType;
    if (data.chiefComplaint !== undefined) payload.chiefComplaint = data.chiefComplaint;
    if (data.historyOfPresentIllness !== undefined) payload.historyOfPresentIllness = data.historyOfPresentIllness;
    if (data.vitalSigns !== undefined) payload.vitalSigns = data.vitalSigns;
    if (data.physicalExamination !== undefined) payload.physicalExamination = data.physicalExamination;
    if (data.diagnosis !== undefined) payload.diagnosis = data.diagnosis;
    if (data.notes !== undefined) payload.notes = data.notes;
    const result = await post<Record<string, any>>('/visits/update.php', payload);
    return mapFromApi<Visit>(result, visitMapping, visitTransforms)!;
  },

  /** Delete a visit */
  async delete(id: number, patientId: number): Promise<void> {
    await del('/visits/delete.php', { id });
  },

  /** Search visits */
  async search(query: string): Promise<Visit[]> {
    const result = await get<{ items: Record<string, any>[] }>('/visits/list.php', { search: query });
    return mapListFromApi<Visit>(result.items, visitMapping, visitTransforms);
  },
};
