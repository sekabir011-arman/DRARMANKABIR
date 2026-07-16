// ─── Pure PHP + MySQL Storage Layer ──────────────────────────────────────────
// All data operations go directly to the PHP/MySQL API.
// localStorage is used only for UI preferences (theme, sidebar state, etc.)
// No canister references, no sync queues, no offline-first complexity.

// ─── API helper ──────────────────────────────────────────────────────────────

const API_BASE = '/api';

interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const url = `${API_BASE}/${endpoint.replace(/^\//, '')}`;
  const res = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string>),
    },
    ...options,
  });

  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(`API error ${res.status}: ${text}`);
  }

  const json = (await res.json()) as ApiResponse<T>;
  if (!json.success) {
    throw new Error(json.error || json.message || 'API request failed');
  }
  return json.data as T;
}

// ─── Generic CRUD helpers ────────────────────────────────────────────────────

export function apiGet<T>(endpoint: string): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'GET' });
}

export function apiPost<T>(endpoint: string, body: unknown): Promise<T> {
  return apiRequest<T>(endpoint, {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

export function apiPut<T>(endpoint: string, body: unknown): Promise<T> {
  return apiRequest<T>(endpoint, {
    method: 'PUT',
    body: JSON.stringify(body),
  });
}

export function apiDelete<T>(endpoint: string): Promise<T> {
  return apiRequest<T>(endpoint, { method: 'DELETE' });
}

// ─── Convenience wrappers for common entities ────────────────────────────────

export async function fetchPatients(): Promise<unknown[]> {
  return apiGet<unknown[]>('patients/get.php');
}

export async function fetchPatient(id: string): Promise<unknown | null> {
  try {
    return await apiGet<unknown>(`patients/get.php?id=${encodeURIComponent(id)}`);
  } catch {
    return null;
  }
}

export async function createPatient(data: unknown): Promise<unknown> {
  return apiPost<unknown>('patients/create.php', data);
}

export async function updatePatient(id: string, data: unknown): Promise<unknown> {
  return apiPut<unknown>(`patients/update.php?id=${encodeURIComponent(id)}`, data);
}

export async function deletePatient(id: string): Promise<void> {
  await apiDelete<void>(`patients/delete.php?id=${encodeURIComponent(id)}`);
}

export async function fetchVisits(patientId: string): Promise<unknown[]> {
  return apiGet<unknown[]>(`visits/get.php?patient_id=${encodeURIComponent(patientId)}`);
}

export async function createVisit(data: unknown): Promise<unknown> {
  return apiPost<unknown>('visits/create.php', data);
}

export async function updateVisit(id: string, data: unknown): Promise<unknown> {
  return apiPut<unknown>(`visits/update.php?id=${encodeURIComponent(id)}`, data);
}

export async function deleteVisit(id: string): Promise<void> {
  await apiDelete<void>(`visits/delete.php?id=${encodeURIComponent(id)}`);
}

export async function fetchPrescriptions(patientId: string): Promise<unknown[]> {
  return apiGet<unknown[]>(`prescriptions/get.php?patient_id=${encodeURIComponent(patientId)}`);
}

export async function createPrescription(data: unknown): Promise<unknown> {
  return apiPost<unknown>('prescriptions/create.php', data);
}

export async function updatePrescription(id: string, data: unknown): Promise<unknown> {
  return apiPut<unknown>(`prescriptions/update.php?id=${encodeURIComponent(id)}`, data);
}

export async function deletePrescription(id: string): Promise<void> {
  await apiDelete<void>(`prescriptions/delete.php?id=${encodeURIComponent(id)}`);
}

// ─── Frontpage / Site Config ─────────────────────────────────────────────────

export async function fetchFrontPageContent(): Promise<unknown | null> {
  try {
    return await apiGet<unknown>('frontpage/get.php');
  } catch {
    return null;
  }
}

export async function saveFrontPageContent(data: unknown): Promise<unknown> {
  return apiPost<unknown>('frontpage/save.php', data);
}

// ─── Doctor content overrides ────────────────────────────────────────────────

export async function fetchDoctorContentOverrides(): Promise<unknown | null> {
  try {
    return await apiGet<unknown>('doctor-content/get.php');
  } catch {
    return null;
  }
}

export async function saveDoctorContentOverrides(data: unknown): Promise<unknown> {
  return apiPost<unknown>('doctor-content/save.php', data);
}

// ─── Legacy API surface (empty stubs for compatibility) ──────────────────────
// These were previously localStorage/canister operations. Now they are no-ops
// because all data flows through PHP API directly.

export type SyncQueueItemType = string;
export interface SyncQueueItem { id: string; timestamp: number; type?: string; data: unknown; retryCount: number; }
export interface SyncStatus { isOnline: boolean; pendingChanges: number; lastSyncAt?: Date; canisterConnected: boolean; }
export interface SyncConflict { entityType: string; entityId: string; localVersion: unknown; serverVersion: unknown; detectedAt: number; }
export interface MigrationProgress { total: number; migrated: number; message: string; }

export function enqueueSync(_item: Partial<SyncQueueItem>): void { /* No-op: data goes directly to PHP API */ }
export function removeFromQueue(_type: string, _ids: Set<string>): void { /* No-op */ }
export function getConflicts(): SyncConflict[] { return []; }
export function getConflictsCount(): number { return 0; }
export function addConflict(_conflict: SyncConflict): void { /* No-op */ }
export function resolveConflict(_entityId: string, _choice: 'mine' | 'theirs'): void { /* No-op */ }
export function isMigrationDone(): boolean { return true; }
export function markMigrationDone(): void { /* No-op */ }
export function getPendingChangesCount(): number { return 0; }

// ─── Clinical Store (PHP API) ─────────────────────────────────────────────────

export function getClinicalStore(): Record<string, unknown[]> { return {}; }
export function getClinicalEntities(_entityType: string): unknown[] { return []; }
export function saveClinicalEntities(_entityType: string, _items: unknown[]): void { /* No-op */ }
export function saveClinicalEntitiesWithSync(_entityType: string, _items: unknown[], _actor: unknown): void { /* No-op */ }
export function nextClinicalId(_items: { id?: unknown }[]): bigint { return 1n; }
export function setLastSyncTs(_ts: bigint): void { /* No-op */ }
export function flushSyncQueue(_actor: unknown): Promise<{ success: number; failed: number }> { return Promise.resolve({ success: 0, failed: 0 }); }
export function pollAndUpdateFromCanister(_actor: unknown): Promise<{ updated: number; success: boolean }> { return Promise.resolve({ updated: 0, success: false }); }
export function doSyncCycle(_actor: unknown, _invalidateAll?: () => void): Promise<void> { return Promise.resolve(); }
export function runMigration(_actor: unknown, _onProgress?: (p: MigrationProgress) => void): Promise<{ migrated: number; skipped: number }> { return Promise.resolve({ migrated: 0, skipped: 0 }); }
export function recordSyncHeartbeat(_actor: unknown): Promise<void> { return Promise.resolve(); }
export function saveFrontPageContentWithSync(_actor: unknown): Promise<void> { return Promise.resolve(); }
export function bootstrapFromCanister(_actor: unknown): Promise<void> { return Promise.resolve(); }
export function refreshDoctorContentOverrides(_actor: unknown): Promise<void> { return Promise.resolve(); }
