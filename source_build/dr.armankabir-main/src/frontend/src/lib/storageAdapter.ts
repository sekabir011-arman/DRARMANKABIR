/**
 * Storage Adapter — Central Access Layer for Client-Side Persistence
 *
 * This adapter wraps localStorage with typed methods and a migration path.
 * - Business data should be stored server-side via the PHP API.
 * - UI preferences (collapsed sections, theme, layout) can remain client-side.
 *
 * Migration pattern:
 *   1. Import this module instead of accessing localStorage directly.
 *   2. When a server API endpoint becomes available, change the adapter method
 *      (no changes needed in components).
 */

// ── Storage Keys (centralised for auditability) ──────────────────────────

export const STORAGE_KEYS = {
  // Business data (should migrate to API)
  DOCTORS_REGISTRY: 'medicare_doctors_registry',
  CURRENT_DOCTOR: 'medicare_current_doctor',
  STAFF_AUTH: 'staff_auth',
  DRUG_REMINDERS: 'medicare_drug_reminders',
  APPOINTMENT_REQUESTS: 'public_appointment_requests',

  // UI preferences (OK to keep client-side)
  SIDEBAR_STATE: 'sidebar_state',
  THEME: 'app_theme',
  COLLAPSED_SECTIONS: 'collapsed_sections',
  PAGE_SIZE: 'page_size',
  RECENT_PATIENTS: 'recent_patients',
} as const;

// ── Generic helpers ──────────────────────────────────────────────────────

export function getItem<T = string>(key: string, fallback?: T): T | undefined {
  try {
    const raw = localStorage.getItem(key);
    if (raw === null) return fallback;
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}

export function setItem<T>(key: string, value: T): void {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch (err) {
    console.warn(`[StorageAdapter] Failed to write key "${key}":`, err);
  }
}

export function removeItem(key: string): void {
  try {
    localStorage.removeItem(key);
  } catch {
    // ignore
  }
}

export function clear(): void {
  try {
    localStorage.clear();
  } catch {
    // ignore
  }
}

// ── Typed accessors for known keys ───────────────────────────────────────

// --- Business data (should migrate to API) ---

export function getDoctorsRegistry<T = unknown[]>(): T[] {
  return getItem<T[]>(STORAGE_KEYS.DOCTORS_REGISTRY, []) ?? [];
}

export function setDoctorsRegistry(data: unknown[]): void {
  setItem(STORAGE_KEYS.DOCTORS_REGISTRY, data);
}

export function getCurrentDoctor<T = Record<string, unknown>>(): T | null {
  return getItem<T>(STORAGE_KEYS.CURRENT_DOCTOR) ?? null;
}

export function setCurrentDoctor(data: Record<string, unknown> | null): void {
  if (data === null) {
    removeItem(STORAGE_KEYS.CURRENT_DOCTOR);
  } else {
    setItem(STORAGE_KEYS.CURRENT_DOCTOR, data);
  }
}

export function getStaffAuth<T = Record<string, unknown>>(): T | null {
  return getItem<T>(STORAGE_KEYS.STAFF_AUTH) ?? null;
}

export function setStaffAuth(data: Record<string, unknown> | null): void {
  if (data === null) {
    removeItem(STORAGE_KEYS.STAFF_AUTH);
  } else {
    setItem(STORAGE_KEYS.STAFF_AUTH, data);
  }
}

// --- UI preferences (OK to keep client-side) ---

export function getTheme(): 'light' | 'dark' | null {
  return getItem<'light' | 'dark'>(STORAGE_KEYS.THEME) ?? null;
}

export function setTheme(theme: 'light' | 'dark'): void {
  setItem(STORAGE_KEYS.THEME, theme);
}

export function isSidebarCollapsed(): boolean {
  return getItem<boolean>(STORAGE_KEYS.SIDEBAR_STATE, false) ?? false;
}

export function setSidebarCollapsed(collapsed: boolean): void {
  setItem(STORAGE_KEYS.SIDEBAR_STATE, collapsed);
}
