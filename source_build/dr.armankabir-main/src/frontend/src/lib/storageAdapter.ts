/**
 * Storage Adapter — UI Preferences Only
 *
 * This adapter is kept ONLY for non-sensitive UI preferences:
 *   - Theme
 *   - Language
 *   - Sidebar collapsed state
 *   - Table layout preferences
 *   - Prescription header preferences
 *   - Admin content (classroom, chamber, profile content)
 *
 * ALL business data (patients, doctors, appointments, prescriptions,
 * payments, clinical notes, etc.) is stored server-side in MySQL
 * via the PHP API. Use the service layer (services/*.ts) and
 * React Query hooks (hooks/useQueries.ts) instead.
 *
 * Authentication uses PHP sessions with HttpOnly cookies.
 * No tokens are stored in localStorage.
 *
 * Usage:
 *   import { storage } from '../lib/storageAdapter';
 *   const lang = storage.getItem('patient_language');
 */

// ── UI Preferences Only ──────────────────────────────────────────────────

const UI_PREFERENCE_KEYS = new Set([
  'patient_language',
  'theme',
  'sidebar_collapsed',
  'table_layout',
  'classroom_arman',
  'classroom_samia',
  'chamber_arman',
  'chamber_samia',
  'profile_arman',
  'profile_samia',
  'prescriptionHeaders_chamber',
  'prescriptionHeaders_hospital',
  'lab_system_name',
  'lab_api_endpoint',
  // Serial display per-user video URL (non-sensitive UI config)
  'serialDisplayVideoUrl_',
]);

function isUIPreference(key: string): boolean {
  if (UI_PREFERENCE_KEYS.has(key)) return true;
  // Allow keys with known prefixes for UI preferences
  if (key.startsWith('serialDisplayVideoUrl_')) return true;
  return false;
}

// ── Core storage wrapper ──────────────────────────────────────────────────

export const storage = {
  getItem(key: string): string | null {
    if (!isUIPreference(key)) {
      console.warn(
        `[StorageAdapter] Blocked read of non-UI key "${key}". ` +
          'Business data must be loaded via the PHP API service layer.',
      );
      return null;
    }
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  },

  setItem(key: string, value: string): void {
    if (!isUIPreference(key)) {
      console.warn(
        `[StorageAdapter] Blocked write of non-UI key "${key}". ` +
          'Business data must be saved via the PHP API service layer.',
      );
      return;
    }
    try {
      localStorage.setItem(key, value);
    } catch (err) {
      console.warn(`[StorageAdapter] Failed to write key "${key}":`, err);
    }
  },

  removeItem(key: string): void {
    if (!isUIPreference(key)) {
      console.warn(
        `[StorageAdapter] Blocked removal of non-UI key "${key}".`,
      );
      return;
    }
    try {
      localStorage.removeItem(key);
    } catch {
      // ignore
    }
  },

  clear(): void {
    try {
      localStorage.clear();
    } catch {
      // ignore
    }
  },

  get length(): number {
    try {
      return localStorage.length;
    } catch {
      return ;
    }
  },

  key(index: number): string | null {
    try {
      return localStorage.key(index);
    } catch {
      return null;
    }
  },
};

// ── UI Preference Helpers (kept for convenience) ─────────────────────────

export const uiPrefs = {
  /** Get language preference */
  getLanguage(): 'en' | 'bn' {
    return (storage.getItem('patient_language') as 'en' | 'bn') ?? 'en';
  },

  /** Set language preference */
  setLanguage(lang: 'en' | 'bn'): void {
    storage.setItem('patient_language', lang);
  },

  /** Get theme preference */
  getTheme(): 'light' | 'dark' {
    return (storage.getItem('theme') as 'light' | 'dark') ?? 'light';
  },

  /** Set theme preference */
  setTheme(theme: 'light' | 'dark'): void {
    storage.setItem('theme', theme);
  },

  /** Get sidebar collapsed state */
  isSidebarCollapsed(): boolean {
    return storage.getItem('sidebar_collapsed') === 'true';
  },

  /** Set sidebar collapsed state */
  setSidebarCollapsed(collapsed: boolean): void {
    storage.setItem('sidebar_collapsed', String(collapsed));
  },
};
