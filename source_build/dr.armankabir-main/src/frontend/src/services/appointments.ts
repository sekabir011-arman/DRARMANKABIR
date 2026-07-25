/**
 * Appointment Service
 *
 * CRUD operations for patient appointments.
 * All data is persisted in MySQL via the PHP API.
 * 
 * Data flow:
 *   Frontend (camelCase) → Service (maps to PHP-compatible) → PHP API
 *   PHP API (returns snake_case) → Service (maps to camelCase) → Frontend
 */

import { get, post, del } from '../lib/apiClient';
import { mapListFromApi, mapFromApi, toNumber, type Mapping } from '../lib/mappers';

// ── Appointment interface (matching PHP response) ────────────────────────

export interface Appointment {
  id: number;
  patientId: number | null;
  patientName: string | null;
  patientPhone: string | null;
  doctorId: number | null;
  doctorName: string | null;
  appointmentDate: string;
  appointmentTime: string | null;
  serialNumber: number | null;
  type: string;
  status: string;
  chiefComplaint: string | null;
  notes: string | null;
  isPublicRequest: boolean;
  createdAt: string;
  updatedAt: string | null;
}

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
  updatedAt: 'updated_at',
};

const appointmentTransforms = {
  id: (v: any) => toNumber(v) ?? ,
  patientId: (v: any) => toNumber(v),
  doctorId: (v: any) => toNumber(v),
  serialNumber: (v: any) => toNumber(v),
  isPublicRequest: (v: any) => v === 1 || v === true || v === '1',
};

// ── Request DTOs ─────────────────────────────────────────────────────────

export interface CreateAppointmentData {
  patientId?: number | null;
  patientName?: string | null;
  patientPhone?: string | null;
  doctorId?: number | null;
  appointmentDate: string;
  appointmentTime?: string | null;
  type?: string;
  status?: string;
  chiefComplaint?: string | null;
  notes?: string | null;
  isPublicRequest?: boolean;
}

export interface UpdateAppointmentData extends Partial<CreateAppointmentData> {
  id: number;
}

export const appointmentService = {
  /** Get all appointments */
  async getAll(params?: {
    limit?: number;
    date?: string;
    status?: string;
    doctor_id?: number;
  }): Promise<Appointment[]> {
    const result = await get<{ items: Record<string, any>[] }>(
      '/appointments/list.php',
      params as Record<string, string | number>
    );
    return mapListFromApi<Appointment>(result.items ?? [], appointmentMapping, appointmentTransforms);
  },

  /** Get appointments for a specific patient */
  async getByPatient(patientId: number): Promise<Appointment[]> {
    const result = await get<{ items: Record<string, any>[] }>('/appointments/list.php', {
      patient_id: patientId,
    });
    return mapListFromApi<Appointment>(result.items ?? [], appointmentMapping, appointmentTransforms);
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
  async create(data: CreateAppointmentData): Promise<Appointment> {
    // PHP API reads: appointment_date, patient_id, patient_name, patient_phone,
    //   doctor_id, appointment_time, type, status, chief_complaint, notes, is_public_request
    const payload: Record<string, any> = {
      appointment_date: data.appointmentDate,
    };
    if (data.patientId != null) payload.patient_id = data.patientId;
    if (data.patientName != null) payload.patient_name = data.patientName;
    if (data.patientPhone != null) payload.patient_phone = data.patientPhone;
    if (data.doctorId != null) payload.doctor_id = data.doctorId;
    if (data.appointmentTime != null) payload.appointment_time = data.appointmentTime;
    payload.type = data.type ?? 'regular';
    payload.status = data.status ?? 'scheduled';
    if (data.chiefComplaint != null) payload.chief_complaint = data.chiefComplaint;
    if (data.notes != null) payload.notes = data.notes;
    if (data.isPublicRequest != null) payload.is_public_request = data.isPublicRequest ? 1 : ;

    const result = await post<Record<string, any>>('/appointments/create.php', payload);
    // create.php returns { id, serial_number }, fetch full appointment
    if (result?.id) {
      return (await this.getById(result.id))!;
    }
    return result as unknown as Appointment;
  },

  /** Update an existing appointment */
  async update(data: UpdateAppointmentData): Promise<void> {
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
    await post('/appointments/update.php', payload);
  },

  /** Delete an appointment */
  async delete(id: number): Promise<void> {
    await del('/appointments/delete.php', { id });
  },

  /** Search appointments */
  async search(query: string): Promise<Appointment[]> {
    const result = await get<{ items: Record<string, any>[] }>('/appointments/list.php', { search: query });
    return mapListFromApi<Appointment>(result.items ?? [], appointmentMapping, appointmentTransforms);
  },
};
