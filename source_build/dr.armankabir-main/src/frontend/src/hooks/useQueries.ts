/**
 * React Query hooks — Service Layer
 *
 * All hooks delegate to service modules.
 * No direct fetch(), localStorage, or browser storage calls.
 * All data comes from MySQL via the PHP API.
 */

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
  UserProfile,
  Visit,
  VitalSigns,
} from '../types';

import { patientService } from '../services/patients';
import { visitService } from '../services/visits';
import { prescriptionService } from '../services/prescriptions';
import { userService } from '../services/users';
import { clinicalNotesService } from '../services/clinicalNotes';
import { appointmentService } from '../services/appointments';
import { vitalService } from '../services/vitals';
import { admissionService } from '../services/admissions';

// ─── Patients ───────────────────────────────────────────────────────────────

export function useGetAllPatients() {
  return useQuery<Patient[]>({
    queryKey: ['patients'],
    queryFn: () => patientService.getAll(1000),
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
    mutationFn: patientService.create,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['patients'] }),
  });
}

export function useUpdatePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: patientService.update,
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['patients'] });
      qc.invalidateQueries({ queryKey: ['patient', vars.id.toString()] });
    },
  });
}

export function useDeletePatient() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: patientService.delete,
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
    mutationFn: visitService.create,
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
    mutationFn: visitService.update,
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
    mutationFn: prescriptionService.create,
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
    mutationFn: prescriptionService.update,
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
    mutationFn: (profile: UserProfile) => {
      userService.updateProfile(profile);
      return profile;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['userProfile'] }),
  });
}

// ─── Clinical Data Engine Hooks ─────────────────────────────────────────────

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
    mutationFn: admissionService.createBed,
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

export function useGetDrugReminders() {
  return useQuery<DrugReminder[]>({
    queryKey: ['drugReminders'],
    queryFn: () => admissionService.getBedsByWard('').then(() => []), // placeholder
  });
}

export function useGetClinicalAlerts() {
  return useQuery<ClinicalAlert[]>({
    queryKey: ['clinicalAlerts'],
    queryFn: async () => {
      // Placeholder: clinical alerts could come from a dedicated endpoint
      return [];
    },
  });
}

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
    mutationFn: appointmentService.create,
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
    mutationFn: vitalService.create,
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

export function isNetworkOnline(): boolean {
  return navigator.onLine;
}

// ─── Legacy stubs removed ──────────────────────────────────────────────────
// All localStorage, canister actor, and sync queue code has been removed.
// These exported names are kept as no-ops for backward compatibility only.

export function setCanisterActor(_actor: unknown): void {
  // No-op — canister actor removed
}

export function getCanisterActor(): unknown | null {
  return null;
}

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
  return '';
}

export function setCanonicalUserEmail(_email: string): void {
  // No-op — handled by PHP session
}

export function clearCanonicalUserEmail(): void {
  // No-op
}

export function storageKey(_prefix: string): string {
  return '';
}

export function getVisitFormData(_visitId: string | number | null): Record<string, any> | null {
  return null;
}

export function generateRegisterNumber(): string {
  return '';
}

export function createPatientInStorage(_data: Record<string, unknown>): Patient {
  throw new Error('createPatientInStorage is deprecated. Use useCreatePatient hook instead.');
}
