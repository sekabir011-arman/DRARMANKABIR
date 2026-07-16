// ─── PHP+MySQL Storage Layer ─────────────────────────────────────────────────
// All data is persisted to MySQL via the PHP API.
// localStorage is NOT used for any persistent data.
// ICP canister / sync queues are COMPLETELY REMOVED.

// ─── API Client ──────────────────────────────────────────────────────────────

const API_BASE = '/api';

interface ApiResponse<T = unknown> {
  success: boolean;
  message?: string;
  data?: T;
  error?: string;
}

async function apiFetch<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const token = localStorage.getItem('phpAuthToken');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers,
  });

  const json: ApiResponse<T> = await res.json();

  if (!json.success) {
    throw new Error(json.message || json.error || 'API request failed');
  }

  return json.data as T;
}

// ─── Types ───────────────────────────────────────────────────────────────────

export type SyncQueueItemType =
  | "upsertPatient"
  | "upsertVisit"
  | "upsertPrescription"
  | "upsertAppointment"
  | "upsertQueueEntry"
  | "upsertObservation"
  | "upsertBed"
  | "upsertDailyProgressNote"
  | "upsertHandover"
  | "upsertMedicationAdministration"
  | "upsertFrontPageContent";

export interface SyncQueueItem {
  id: string;
  timestamp: number;
  type?: SyncQueueItemType;
  operation?: "create" | "update" | "delete";
  entityType?: string;
  entityId?: string;
  data: unknown;
  retryCount: number;
}

export interface SyncStatus {
  isOnline: boolean;
  pendingChanges: number;
  lastSyncAt?: Date;
  canisterConnected: boolean;
}

export interface SyncConflict {
  entityType: string;
  entityId: string;
  localVersion: unknown;
  serverVersion: unknown;
  localUpdatedAt?: number;
  serverUpdatedAt?: number;
  detectedAt: number;
}

export interface MigrationProgress {
  total: number;
  migrated: number;
  message: string;
}

// ─── Dummy / no-op exports (replaced by direct PHP API calls) ────────────────

export function getDeviceId(): string {
  return 'php-mysql-device';
}

export function isMigrationDone(): boolean {
  return true;
}

export function markMigrationDone(): void {
  // no-op: migration is complete
}

export function getConflicts(): SyncConflict[] {
  return [];
}

export function addConflict(_conflict: SyncConflict): void {
  // no-op: conflicts resolved server-side
}

export function getConflictsCount(): number {
  return 0;
}

export function resolveConflict(
  _entityId: string,
  _choice: "mine" | "theirs",
): void {
  // no-op: conflicts resolved server-side
}

export function getPendingChangesCount(): number {
  return 0;
}

export function getLastSyncAt(): Date | undefined {
  return undefined;
}

export function getSyncStatus(): SyncStatus {
  return {
    isOnline: navigator.onLine,
    pendingChanges: 0,
    lastSyncAt: undefined,
    canisterConnected: false,
  };
}

/** No-op: sync queues removed */
export function enqueueSync(_item: SyncQueueItem): void {
  // Sync queues removed — all data goes directly to PHP API
}

/** No-op: offline queues removed */
export function getContentOfflineQueue(): unknown[] {
  return [];
}

/** No-op: offline queues removed */
export function addToContentOfflineQueue(
  _payload: unknown,
  _updatedAt?: string,
): void {
  // Offline queues removed — all data goes directly to PHP API
}

/** No-op: offline queues removed */
export async function processContentOfflineQueue(): Promise<void> {
  // Offline queues removed
}

/** No-op: sync cycle removed */
export async function doSyncCycle(
  _actor: unknown,
  _invalidateAll?: () => void,
): Promise<void> {
  // Sync cycles removed — all data goes directly to PHP API
}

/** No-op: bootstrap removed */
export async function bootstrapFromCanister(
  _actor: unknown,
  _invalidateAll?: () => void,
): Promise<void> {
  // Bootstrap removed — data comes from MySQL
}

/** No-op: migration removed */
export async function runMigration(
  _actor: unknown,
  _onProgress?: (progress: MigrationProgress) => void,
): Promise<{ migrated: number; skipped: number }> {
  return { migrated: 0, skipped: 0 };
}

/** No-op: recorded sync removed */
export async function recordSyncHeartbeat(_actor: unknown): Promise<void> {
  // Sync heartbeats removed
}

// ─── Frontpage content: PHP API instead of canister ─────────────────────────

export async function saveFrontPageContentWithSync(_actor: unknown) {
  try {
    const resp = await fetch('/api/frontpage/get.php');
    if (resp.ok) {
      const json = await resp.json();
      if (json.success && json.data) {
        // Return the data for callers that expect it
        return json.data;
      }
    }
  } catch (e) {
    console.warn('[storage] Failed to load frontpage content:', e);
  }
  return null;
}

// ─── Clinical entity storage (now uses PHP API) ────────────────────────────

const CLINICAL_STORE_KEY = 'medicare_clinical_data';

export function getClinicalStore(): Record<string, unknown[]> {
  return {};
}

export function getClinicalEntities(entityType: string): unknown[] {
  return [];
}

export function saveClinicalEntities(
  entityType: string,
  _items: unknown[],
): void {
  // Clinical data goes directly to PHP API endpoints
}

export function saveClinicalEntitiesWithSync(
  entityType: string,
  _items: unknown[],
  _actor: unknown,
): void {
  // Clinical data goes directly to PHP API endpoints
}

export function nextClinicalId(_items: { id?: unknown }[]): bigint {
  return 1n;
}

export function loadSyncQueue(): SyncQueueItem[] {
  return [];
}

export function removeFromQueue(_type: string, _ids: Set<string>): void {
  // Queue removed
}

export function setLastSyncTs(_ts: bigint): void {
  // Sync timestamps removed
}
