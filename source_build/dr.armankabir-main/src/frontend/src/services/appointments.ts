/**
 * Appointment Service
 *
 * CRUD operations for patient appointments.
 * All data is persisted in MySQL via the PHP API.
 * 
 * Data flow:
 *   Frontend (camelCase) → Service (maps to camelCase) → PHP API (reads camelCase)
 *   PHP API (returns snake_case) → Service (maps to camelCase) → Frontend DTO
 */

import { get, post, del } from '../lib/apiClient';
import type { Appointment } from '../types';
import { mapListFromApi, mapFromApi, mapToApi, type Mapping, toNumber, toIsoString } from '../lib/mappers';

// ── Appointment API mapping ───────────────────────────────────────────────

const appointmentMapping: Mapping<Appointment> = {
  id: 'id',
  patientId: 'patient_id',
  patientName: 'patient_name',
  patientPhone: 'patient_phone',
  doctorId: 'doctor_id',
  doctorName: 'doctor_name',
  appointmentDate: 'appointment_date',
  appointmentTime: 'appointment_time',
  serialNumber: 'serial_number',
  type: 'type',
  status: 'status',
  chiefComplaint: 'chief_complaint',
  notes: 'notes',
  isPublicRequest: 'is_public_request',
  createdAt: 'created_at',
};

const appointmentTransforms = {
  id: (v: any) => toNumber(v) ?? ,
  patientId: (v: any) => toNumber(v),
  doctorId: (v: any) => toNumber(v),
  serialNumber: (v: any) => toNumber(v),
  isPublicRequest: (v: any) => v === 1 || v === true || v === '1',
};

// ── Request DTOs (camelCase) ─────────────────────────────────────────────

export interface CreateAppointmentData {
  patientId?: number;
  patientName?: string;
  patientPhone?: string;
  doctorId?: number;
  appointmentDate: string;
  appointmentTime?: string;
  type?: string;
  status?: string;
  chiefComplaint?: string;
  notes?: string;
  isPublicRequest?: boolean;
}

export interface UpdateAppointmentData extends Partial<CreateAppointmentData> {
  id: number;
}

export const appointmentService = {
  /** Get all appointments */
  async getAll(params?: { limit?: number; date?: string; status?: string }): Promise<Appointment[]> {
    const result = await get<{ items: Record<string, any>[] }>('/appointments/list.php', params as any);
    return mapListFromApi<Appointment>(result.items, appointmentMapping, appointmentTransforms);
  },

  /** Get appointments for a specific patient */
  async getByPatient(patientId: number): Promise<Appointment[]> {
    const result = await get<{ items: Record<string, any>[] }>('/appointments/list.php', { patient_id: patientId });
    return mapListFromApi<Appointment>(result.items, appointmentMapping, appointmentTransforms);
  },

  /** Get a single appointment by ID */
  async getById(id: number): Promise<Appointment | null> {
    try {
      const result = await get<Record<string, any>>('/appointments/get.php', { id: String(id) });
      return mapFromApi<Appointment>(result, appointmentMapping, appointmentTransforms);
    } catch {
      return null;
    }
  },

  /** Create a new appointment */
  async create(data: CreateAppointmentData): Promise<any> {
    // PHP API reads camelCase (e.g., $input['appointment_date'])
    const payload: Record<string, any> = {
      patient_id: data.patientId,
      patient_name: data.patientName,
      patient_phone: data.patientPhone,
      doctor_id: data.doctorId,
      appointment_date: data.appointmentDate,
      appointment_time: data.appointmentTime,
      type: data.type ?? 'regular',
      status: data.status ?? 'scheduled',
      chief_complaint: data.chiefComplaint,
      notes: data.notes,
      is_public_request: data.isPublicRequest ? 1 : ,
    };
    return post<any>('/appointments/create.php', payload);
  },

  /** Update an existing appointment */
  async update(data: UpdateAppointmentData): Promise<any> {
    const payload: Record<string, any> = { id: data.id };
    if (data.patientId !== undefined) payload.patient_id = data.patientId;
    if (data.patientName !== undefined) payload.patient_name = data.patientName;
    if (data.patientPhone !== undefined) payload.patient_phone = data.patientPhone;
    if (data.doctorId !== undefined) payload.doctor_id = data.doctorId;
    if (data.appointmentDate !== undefined) payload.appointment_date = data.appointmentDate;
    if (data.appointmentTime !== undefined) payload.appointment_time = data.appointmentTime;
    if (data.type !== undefined) payload.type = data.type;
    if (data.status !== undefined) payload.status = data.status;
    if (data.chiefComplaint !== undefined) payload.chief_complaint = data.chiefComplaint;
    if (data.notes !== undefined) payload.notes = data.notes;
    if (data.isPublicRequest !== undefined) payload.is_public_request = data.isPublicRequest ? 1 : ;
    return post<any>('/appointments/update.php', payload);
  },

  /** Delete an appointment */
  async delete(id: number): Promise<void> {
    await del('/appointments/delete.php', { id });
  },

  /** Search appointments */
  async search(query: string): Promise<Appointment[]> {
    const result = await get<{ items: Record<string, any>[] }>('/appointments/list.php', { search: query });
    return mapListFromApi<Appointment>(result.items, appointmentMapping, appointmentTransforms);
  },
};
