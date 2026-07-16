/**
 * React Query hooks — PHP/MySQL Backend
 *
 * All CRUD operations now target the PHP/MySQL API.
 * localStorage is no longer used as the primary data store.
 * Canister actor and sync queue code have been removed.
 * IDs are now `number` (MySQL INT/BIGINT) instead of `bigint`.
 */

import { get, post, del as apiDelete } from '../lib/api';
import type { ApiError } from '../lib/api';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
  AdmissionHistory,
  BedRecord,
  ClinicalAlert,
  ClinicalNote,
  ClinicalOrder,
  DrugReminder,
  Encounter,
  Medication,
  Observation,
  Patient,
  Prescription,
  PrescriptionRecord,
  StaffRole,
  UserProfile,
  Visit,
  VitalSigns,
} from '../types';

// ─── Canister actor references removed ─────────────────────────────────────

export function setCanisterActor(_actor: unknown): void {
  // No-op — canister actor removed
}

export function getCanisterActor(): unknown | null {
  return null;
}

// ─── Legacy localStorage helpers (kept for type compatibility) ─────────────

export function saveToStorage<T>(_key: string, _data: T[]): void {
  // No-op — storage is server-side
}

export function loadFromStorage<T>(_key: string): T[] {
  return [];
}

export function loadFromAllDoctorKeys<T>(_prefix: string): T[] {
  return [];
}

export function getDoctorEmail(): string {
  try {
    return localStorage.getItem('app_current_user_email') || '';
  } catch {
    return '';
  }
}

export function setCanonicalUserEmail(email: string): void {
  try {
    localStorage.setItem('app_current_user_email', email);
  } catch {
    // ignore
  }
}

export function clearCanonicalUserEmail(): void {
  try {
    localStorage.removeItem('app_current_user_email');
  } catch {
    // ignore
  }
}

export function storageKey(prefix: string): string {
  return `${prefix}_${getDoctorEmail()}`;
}

export function getVisitFormData(_visitId: string | number | null): Record<string, any> | null {
  return null;
}

// ─── Register number ────────────────────────────────────────────────────────

export function generateRegisterNumber(): string {
  // PHP backend generates register numbers
  return '';
}

export function createPatientInStorage(_data: Record<string, unknown>): Patient {
  throw new Error('createPatientInStorage is deprecated. Use useCreatePatient hook instead.');
}

// ─── Patients ───────────────────────────────────────────────────────────────

export function useGetAllPatients() {
  return useQuery<Patient[]>({
    queryKey: ['patients'],
    queryFn: async () => {
      const result = await get<{ items: Patient[] }>('/patients/list.php', { limit: 1000 });
      return result.items ?? [];
    },
  });
}

export function useGetPatient(id: number | null) {
  return useQuery<Patient | null>({
    queryKey: ['patient', id?.toString()],
    queryFn: async () => {
      if (!id) return null;
      return await get<Patient>('/patients/get.php', { id: String(id) });
    },
    enabled: !!id,
  });
}

export function useCreatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
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
    }) => {
      const patient = await post<Patient>('/patients/create.php', {
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
      return patient;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['patients'] }),
  });
}

export function useUpdatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      id: number;
      fullName?: string;
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
    }) => {
      const patient = await post<Patient>('/patients/update.php', {
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
      return patient;
    },
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['patients'] });
      qc.invalidateQueries({ queryKey: ['patient', vars.id.toString()] });
    },
  });
}

export function useDeletePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await apiDelete('/patients/delete.php', { id });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['patients'] }),
  });
}

// ─── Visits ─────────────────────────────────────────────────────────────────

export function useGetVisitsByPatient(patientId: number | null) {
  return useQuery<Visit[]>({
    queryKey: ['visits', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      const result = await get<{ items: Visit[] }>('/visits/list.php', { patient_id: patientId });
      return result.items ?? [];
    },
    enabled: !!patientId,
  });
}

export function useCreateVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      patientId: number;
      visitDate?: string;
      chiefComplaint?: string;
      historyOfPresentIllness?: string | null;
      vitalSigns?: VitalSigns;
      physicalExamination?: string | null;
      diagnosis?: string | null;
      notes?: string | null;
      visitType?: string;
    }) => {
      const visit = await post<Visit>('/visits/create.php', {
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
      return visit;
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['visits', vars.patientId.toString()] }),
  });
}

export function useDeleteVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, patientId }: { id: number; patientId: number }) => {
      await apiDelete('/visits/delete.php', { id });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['visits', vars.patientId.toString()] }),
  });
}

export function useUpdateVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      id: number;
      patientId: number;
      visitDate?: string;
      chiefComplaint?: string;
      historyOfPresentIllness?: string | null;
      vitalSigns?: VitalSigns;
      physicalExamination?: string | null;
      diagnosis?: string | null;
      notes?: string | null;
      visitType?: string;
    }) => {
      const visit = await post<Visit>('/visits/update.php', {
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
      return visit;
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['visits', vars.patientId.toString()] }),
  });
}

// ─── Prescriptions ─────────────────────────────────────────────────────────

export function useGetPrescriptionsByPatient(patientId: number | null) {
  return useQuery<Prescription[]>({
    queryKey: ['prescriptions', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      const result = await get<{ items: Prescription[] }>('/prescriptions/list.php', { patient_id: patientId });
      return result.items ?? [];
    },
    enabled: !!patientId,
  });
}

export function useCreatePrescription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      patientId: number;
      visitId?: number | null;
      prescriptionDate?: string;
      diagnosis?: string | null;
      medications: Medication[];
      notes?: string | null;
    }) => {
      const prescription = await post<Prescription>('/prescriptions/create.php', {
        patient_id: data.patientId,
        visit_id: data.visitId,
        prescription_date: data.prescriptionDate,
        diagnosis: data.diagnosis,
        medications: JSON.stringify(data.medications),
        notes: data.notes,
      });
      return prescription;
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['prescriptions', vars.patientId.toString()] }),
  });
}

export function useDeletePrescription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, patientId }: { id: number; patientId: number }) => {
      await apiDelete('/prescriptions/delete.php', { id });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['prescriptions', vars.patientId.toString()] }),
  });
}

export function useUpdatePrescription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      id: number;
      patientId: number;
      visitId?: number | null;
      prescriptionDate?: string;
      diagnosis?: string | null;
      medications: Medication[];
      notes?: string | null;
    }) => {
      const prescription = await post<Prescription>('/prescriptions/update.php', {
        id: data.id,
        patient_id: data.patientId,
        visit_id: data.visitId,
        prescription_date: data.prescriptionDate,
        diagnosis: data.diagnosis,
        medications: JSON.stringify(data.medications),
        notes: data.notes,
      });
      return prescription;
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['prescriptions', vars.patientId.toString()] }),
  });
}

// ─── User profile ───────────────────────────────────────────────────────────

export function useGetCallerUserProfile() {
  return useQuery<UserProfile | null>({
    queryKey: ['userProfile'],
    queryFn: async () => {
      try {
        const user = await get<Record<string, any>>('/auth/verify.php');
        if (user) {
          return {
            name: user.full_name || '',
            email: user.email || '',
            role: user.role,
            specialization: user.specialization,
          } as UserProfile;
        }
      } catch {
        // Not authenticated
      }
      return null;
    },
  });
}

export function useGetCallerUserRole() {
  return useQuery<string>({
    queryKey: ['userRole'],
    queryFn: async () => {
      try {
        const user = await get<Record<string, any>>('/auth/verify.php');
        return user?.role || 'user';
      } catch {
        return 'user';
      }
    },
  });
}

export function useSaveCallerUserProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (profile: UserProfile) => {
      // Profile saving is handled by PHP backend
      return profile;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['userProfile'] }),
  });
}

// ─── Clinical Data Engine Hooks ─────────────────────────────────────────────
// Clinical entities now go directly to PHP API endpoints.
// The legacy getClinicalEntities/saveClinicalEntities are no-ops.

export function useGetEncountersByPatient(patientId: number | null) {
  return useQuery<Encounter[]>({
    queryKey: ['encounters', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      return await get<Encounter[]>('/clinical/encounters-list.php', { patient_id: patientId });
    },
    enabled: !!patientId,
  });
}

export function useCreateEncounter() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { patientId: number; encounterData: Record<string, unknown> }) => {
      return await post<Encounter>('/clinical/encounters-create.php', {
        patient_id: data.patientId,
        ...data.encounterData,
      });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['encounters', vars.patientId.toString()] }),
  });
}

export function useGetObservationsByPatient(patientId: number | null) {
  return useQuery<Observation[]>({
    queryKey: ['observations', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      return await get<Observation[]>('/clinical/observations-list.php', { patient_id: patientId });
    },
    enabled: !!patientId,
  });
}

export function useCreateObservation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { patientId: number; observationData: Record<string, unknown> }) => {
      return await post<Observation>('/clinical/observations-create.php', {
        patient_id: data.patientId,
        ...data.observationData,
      });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['observations', vars.patientId.toString()] }),
  });
}

export function useGetClinicalNotesByPatient(patientId: number | null) {
  return useQuery<ClinicalNote[]>({
    queryKey: ['clinicalNotes', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      return await get<ClinicalNote[]>('/clinical/notes-list.php', { patient_id: patientId });
    },
    enabled: !!patientId,
  });
}

export function useCreateClinicalNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { patientId: number; noteData: Record<string, unknown> }) => {
      return await post<ClinicalNote>('/clinical/notes-create.php', {
        patient_id: data.patientId,
        ...data.noteData,
      });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['clinicalNotes', vars.patientId.toString()] }),
  });
}

export function useGetOrdersByPatient(patientId: number | null) {
  return useQuery<ClinicalOrder[]>({
    queryKey: ['orders', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      return await get<ClinicalOrder[]>('/clinical/orders-list.php', { patient_id: patientId });
    },
    enabled: !!patientId,
  });
}

export function useCreateOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { patientId: number; orderData: Record<string, unknown> }) => {
      return await post<ClinicalOrder>('/clinical/orders-create.php', {
        patient_id: data.patientId,
        ...data.orderData,
      });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['orders', vars.patientId.toString()] }),
  });
}

export function useGetBedsByWard(ward: string | null) {
  return useQuery<BedRecord[]>({
    queryKey: ['beds', ward],
    queryFn: async () => {
      if (!ward) return [];
      return await get<BedRecord[]>('/beds/list.php', { ward });
    },
    enabled: !!ward,
  });
}

export function useCreateBed() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { ward: string; bedNumber: string; bedType?: string }) => {
      return await post<BedRecord>('/beds/create.php', data);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['beds'] }),
  });
}

export function useGetAdmissionHistory(patientId: number | null) {
  return useQuery<AdmissionHistory[]>({
    queryKey: ['admissions', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      return await get<AdmissionHistory[]>('/admissions/list.php', { patient_id: patientId });
    },
    enabled: !!patientId,
  });
}

export function useGetDrugReminders() {
  return useQuery<DrugReminder[]>({
    queryKey: ['drugReminders'],
    queryFn: async () => {
      return await get<DrugReminder[]>('/drugs/reminders.php');
    },
  });
}

export function useGetClinicalAlerts() {
  return useQuery<ClinicalAlert[]>({
    queryKey: ['clinicalAlerts'],
    queryFn: async () => {
      return await get<ClinicalAlert[]>('/clinical/alerts-list.php');
    },
  });
}

export function useGetAppointmentsByPatient(patientId: number | null) {
  return useQuery<any[]>({
    queryKey: ['appointments', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      const result = await get<{ items: any[] }>('/appointments/list.php', { patient_id: patientId });
      return result.items ?? [];
    },
    enabled: !!patientId,
  });
}

export function useCreateAppointment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      patientId: number;
      appointmentDate?: string;
      doctorName?: string;
      reason?: string;
      status?: string;
    }) => {
      return await post('/appointments/create.php', {
        patient_id: data.patientId,
        appointment_date: data.appointmentDate,
        doctor_name: data.doctorName,
        reason: data.reason,
        status: data.status ?? 'scheduled',
      });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['appointments'] }),
  });
}

// ─── Vitals ─────────────────────────────────────────────────────────────────

export function useGetVitalsByPatient(patientId: number | null) {
  return useQuery<any[]>({
    queryKey: ['vitals', patientId?.toString()],
    queryFn: async () => {
      if (!patientId) return [];
      const result = await get<{ items: any[] }>('/vitals/list.php', { patient_id: patientId });
      return result.items ?? [];
    },
    enabled: !!patientId,
  });
}

export function useCreateVitals() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: {
      patientId: number;
      vitalSigns: VitalSigns;
      recordedAt?: string;
    }) => {
      return await post('/vitals/create.php', {
        patient_id: data.patientId,
        ...data.vitalSigns,
        recorded_at: data.recordedAt,
      });
    },
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['vitals', vars.patientId.toString()] }),
  });
}

// ─── Sync status (no-op) ────────────────────────────────────────────────────

export function useSyncStatus() {
  return useQuery({
    queryKey: ['syncStatus'],
    queryFn: async () => ({
      isOnline: navigator.onLine,
      pendingChanges: 0,
      lastSyncAt: new Date(),
      canisterConnected: false,
    }),
  });
}

// ─── Legacy helper kept for backward compatibility ─────────────────────────

export function isNetworkOnline(): boolean {
  return navigator.onLine;
}
