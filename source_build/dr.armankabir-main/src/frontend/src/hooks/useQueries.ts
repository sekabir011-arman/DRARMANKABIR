/**
 * React Query hooks — PHP/MySQL Backend
 *
 * All CRUD operations now target the PHP/MySQL API via the service layer.
 * No localStorage, sessionStorage, or IndexedDB is used for business data.
 * No canister actors or sync queue code.
 * IDs are number (MySQL INT/BIGINT).
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  patientService,
  visitService,
  prescriptionService,
  appointmentService,
  clinicalNotesService,
  admissionService,
  vitalService,
  userService,
} from '../services';
import type {
  AdmissionHistory,
  BedRecord,
  ClinicalAlert,
  ClinicalNote,
  ClinicalOrder,
  DrugReminder,
  Encounter,
  Observation,
  Patient,
  Prescription,
  UserProfile,
  Visit,
  VitalSigns,
} from '../types';

// ─── Patients ───────────────────────────────────────────────────────────────

export function useGetAllPatients() {
  return useQuery<Patient[]>({
    queryKey: ['patients'],
    queryFn: () => patientService.getAll(100),
  });
}

export function useGetPatient(id: number | null) {
  return useQuery<Patient | null>({
    queryKey: ['patient', id?.toString()],
    queryFn: () => patientService.getById(id!),
    enabled: !!id,
  });
}

export function useCreatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof patientService.create>[]) =>
      patientService.create(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['patients'] }),
  });
}

export function useUpdatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof patientService.update>[]) =>
      patientService.update(data),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['patients'] });
      qc.invalidateQueries({ queryKey: ['patient', vars.id.toString()] });
    },
  });
}

export function useDeletePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => patientService.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['patients'] }),
  });
}

// ─── Visits ─────────────────────────────────────────────────────────────────

export function useGetVisitsByPatient(patientId: number | null) {
  return useQuery<Visit[]>({
    queryKey: ['visits', patientId?.toString()],
    queryFn: () => visitService.getByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof visitService.create>[]) =>
      visitService.create(data),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['visits', vars.patientId.toString()] }),
  });
}

export function useDeleteVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, patientId }: { id: number; patientId: number }) =>
      visitService.delete(id, patientId),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['visits', vars.patientId.toString()] }),
  });
}

export function useUpdateVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof visitService.update>[]) =>
      visitService.update(data),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['visits', vars.patientId.toString()] }),
  });
}

// ─── Prescriptions ─────────────────────────────────────────────────────────

export function useGetPrescriptionsByPatient(patientId: number | null) {
  return useQuery<Prescription[]>({
    queryKey: ['prescriptions', patientId?.toString()],
    queryFn: () => prescriptionService.getByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreatePrescription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof prescriptionService.create>[]) =>
      prescriptionService.create(data),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['prescriptions', vars.patientId.toString()] }),
  });
}

export function useDeletePrescription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, patientId }: { id: number; patientId: number }) =>
      prescriptionService.delete(id, patientId),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['prescriptions', vars.patientId.toString()] }),
  });
}

export function useUpdatePrescription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof prescriptionService.update>[]) =>
      prescriptionService.update(data),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['prescriptions', vars.patientId.toString()] }),
  });
}

// ─── User profile ───────────────────────────────────────────────────────────

export function useGetCallerUserProfile() {
  return useQuery<UserProfile | null>({
    queryKey: ['userProfile'],
    queryFn: () => userService.getProfile(),
  });
}

export function useGetCallerUserRole() {
  return useQuery<string>({
    queryKey: ['userRole'],
    queryFn: () => userService.getRole(),
  });
}

export function useSaveCallerUserProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (profile: UserProfile) => userService.updateProfile(profile),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['userProfile'] }),
  });
}

// ─── Clinical Data Hooks ───────────────────────────────────────────────────

export function useGetEncountersByPatient(patientId: number | null) {
  return useQuery<Encounter[]>({
    queryKey: ['encounters', patientId?.toString()],
    queryFn: () => clinicalNotesService.getEncountersByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateEncounter() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { patientId: number; encounterData: Record<string, unknown> }) =>
      clinicalNotesService.createEncounter(data.patientId, data.encounterData),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['encounters', vars.patientId.toString()] }),
  });
}

export function useGetObservationsByPatient(patientId: number | null) {
  return useQuery<Observation[]>({
    queryKey: ['observations', patientId?.toString()],
    queryFn: () => clinicalNotesService.getObservationsByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateObservation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { patientId: number; observationData: Record<string, unknown> }) =>
      clinicalNotesService.createObservation(data.patientId, data.observationData),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['observations', vars.patientId.toString()] }),
  });
}

export function useGetClinicalNotesByPatient(patientId: number | null) {
  return useQuery<ClinicalNote[]>({
    queryKey: ['clinicalNotes', patientId?.toString()],
    queryFn: () => clinicalNotesService.getNotesByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateClinicalNote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { patientId: number; noteData: Record<string, unknown> }) =>
      clinicalNotesService.createNote(data.patientId, data.noteData),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['clinicalNotes', vars.patientId.toString()] }),
  });
}

export function useGetOrdersByPatient(patientId: number | null) {
  return useQuery<ClinicalOrder[]>({
    queryKey: ['orders', patientId?.toString()],
    queryFn: () => clinicalNotesService.getOrdersByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateOrder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { patientId: number; orderData: Record<string, unknown> }) =>
      clinicalNotesService.createOrder(data.patientId, data.orderData),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['orders', vars.patientId.toString()] }),
  });
}

// ─── Beds & Admissions ─────────────────────────────────────────────────────

export function useGetBedsByWard(ward: string | null) {
  return useQuery<BedRecord[]>({
    queryKey: ['beds', ward],
    queryFn: () => admissionService.getBedsByWard(ward!),
    enabled: !!ward,
  });
}

export function useCreateBed() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof admissionService.createBed>[]) =>
      admissionService.createBed(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['beds'] }),
  });
}

export function useGetAdmissionHistory(patientId: number | null) {
  return useQuery<AdmissionHistory[]>({
    queryKey: ['admissions', patientId?.toString()],
    queryFn: () => admissionService.getByPatient(patientId!),
    enabled: !!patientId,
  });
}

// ─── Drug Reminders & Alerts ───────────────────────────────────────────────

export function useGetDrugReminders() {
  return useQuery<DrugReminder[]>({
    queryKey: ['drugReminders'],
    queryFn: async () => {
      const { get } = await import('../lib/apiClient');
      return get<DrugReminder[]>('/drugs/reminders.php');
    },
  });
}

export function useGetClinicalAlerts() {
  return useQuery<ClinicalAlert[]>({
    queryKey: ['clinicalAlerts'],
    queryFn: async () => {
      const { get } = await import('../lib/apiClient');
      return get<ClinicalAlert[]>('/clinical/alerts-list.php');
    },
  });
}

// ─── Appointments ──────────────────────────────────────────────────────────

export function useGetAppointmentsByPatient(patientId: number | null) {
  return useQuery<any[]>({
    queryKey: ['appointments', patientId?.toString()],
    queryFn: () => appointmentService.getByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateAppointment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof appointmentService.create>[]) =>
      appointmentService.create(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['appointments'] }),
  });
}

// ─── Vitals ─────────────────────────────────────────────────────────────────

export function useGetVitalsByPatient(patientId: number | null) {
  return useQuery<any[]>({
    queryKey: ['vitals', patientId?.toString()],
    queryFn: () => vitalService.getByPatient(patientId!),
    enabled: !!patientId,
  });
}

export function useCreateVitals() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof vitalService.create>[]) =>
      vitalService.create(data),
    onSuccess: (_, vars) =>
      qc.invalidateQueries({ queryKey: ['vitals', vars.patientId.toString()] }),
  });
}

// ─── Sync status (no-op — always online with PHP) ─────────────────────────

export function useSyncStatus() {
  return useQuery({
    queryKey: ['syncStatus'],
    queryFn: async () => ({
      isOnline: navigator.onLine,
      pendingChanges: ,
      lastSyncAt: new Date(),
      canisterConnected: false,
    }),
  });
}

export function isNetworkOnline(): boolean {
  return navigator.onLine;
}
