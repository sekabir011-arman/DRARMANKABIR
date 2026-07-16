/**
 * Appointment Service
 *
 * CRUD operations for patient appointments.
 * All data is persisted in MySQL via the PHP API.
 */

import { get, post, del } from '../lib/apiClient';

export interface CreateAppointmentData {
  patientId: number;
  appointmentDate?: string;
  doctorName?: string;
  reason?: string;
  status?: string;
}

export interface UpdateAppointmentData extends Partial<CreateAppointmentData> {
  id: number;
}

export const appointmentService = {
  /** Get all appointments */
  async getAll(params?: { limit?: number; date?: string; status?: string }): Promise<any[]> {
    const result = await get<{ items: any[] }>('/appointments/list.php', params as any);
    return result.items ?? [];
  },

  /** Get appointments for a specific patient */
  async getByPatient(patientId: number): Promise<any[]> {
    const result = await get<{ items: any[] }>('/appointments/list.php', { patient_id: patientId });
    return result.items ?? [];
  },

  /** Get a single appointment by ID */
  async getById(id: number): Promise<any | null> {
    try {
      return await get<any>('/appointments/get.php', { id: String(id) });
    } catch {
      return null;
    }
  },

  /** Create a new appointment */
  async create(data: CreateAppointmentData): Promise<any> {
    return post<any>('/appointments/create.php', {
      patient_id: data.patientId,
      appointment_date: data.appointmentDate,
      doctor_name: data.doctorName,
      reason: data.reason,
      status: data.status ?? 'scheduled',
    });
  },

  /** Update an existing appointment */
  async update(data: UpdateAppointmentData): Promise<any> {
    return post<any>('/appointments/update.php', {
      id: data.id,
      patient_id: data.patientId,
      appointment_date: data.appointmentDate,
      doctor_name: data.doctorName,
      reason: data.reason,
      status: data.status,
    });
  },

  /** Delete an appointment */
  async delete(id: number): Promise<void> {
    await del('/appointments/delete.php', { id });
  },

  /** Search appointments */
  async search(query: string): Promise<any[]> {
    const result = await get<{ items: any[] }>('/appointments/list.php', { search: query });
    return result.items ?? [];
  },
};
