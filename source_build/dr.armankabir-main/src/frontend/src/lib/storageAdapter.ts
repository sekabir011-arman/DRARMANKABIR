/**
 * Storage Adapter — UI Preferences Only
 *
 * This module is the ONLY allowed interface to browser storage.
 * Business data must NEVER be stored here.
 *
 * ALLOWED: Theme, language, sidebar collapsed state, table layout prefs, non-sensitive UI settings.
 * FORBIDDEN: Patients, doctors, staff, appointments, prescriptions, clinical notes,
 *            payments, invoices, notifications, auth tokens, audit logs, registries.
 *
 * Migration complete. Business data now goes through services/ -> PHP API -> MySQL.
 * React Query handles caching and refresh.
 *
 * Usage:
 *   import { storage } from '../lib/storageAdapter';
 *   const lang = storage.getItem('patient_language');
 *   storage.setItem('sidebar_collapsed', 'true');
 */

// ─── Allowed UI Preference Keys ──────────────────────────────────────────────
// Only these keys are permitted in browser storage.
// Any other key indicates business data leakage and must be removed.

const ALLOWED_UI_KEYS = new Set([
  'patient_language',
  'sidebar_collapsed',
  'theme',
  'table_page_size',
  'table_compact_view',
  'classroom_arman',
  'classroom_samia',
  'chamber_arman',
  'chamber_samia',
  'profile_arman',
  'profile_samia',
  'prescriptionHeaders_chamber',
  'prescriptionHeaders_hospital',
  'app_current_user_email',
]);

function isAllowedKey(key: string): boolean {
  if (ALLOWED_UI_KEYS.has(key)) return true;
  // Temporary UI state keys (draft autosave, scroll position, etc.)
  if (key.startsWith('autosave_')) return true;
  if (key.startsWith('draft_')) return true;
  if (key.startsWith('scroll_')) return true;
  return false;
}

// ─── Core storage wrapper ───────────────────────────────────────────────────

export const storage = {
  getItem(key: string): string | null {
    if (!isAllowedKey(key)) {
      console.warn(`[StorageAdapter] Blocked read of non-UI key "${key}". Business data must come from PHP API.`);
      return null;
    }
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  },

  setItem(key: string, value: string): void {
    if (!isAllowedKey(key)) {
      console.warn(`[StorageAdapter] Blocked write of non-UI key "${key}". Business data must go to PHP API.`);
      return;
    }
    try {
      localStorage.setItem(key, value);
    } catch (err) {
      console.warn(`[StorageAdapter] Failed to write key "${key}":`, err);
    }
  },

  removeItem(key: string): void {
    if (!isAllowedKey(key)) {
      console.warn(`[StorageAdapter] Blocked remove of non-UI key "${key}". Business data must come from PHP API.`);
      return;
    }
    try {
      localStorage.removeItem(key);
    } catch {
      // ignore
    }
  },

  clear(): void {
    // Only clear allowed UI keys
    try {
      for (const key of ALLOWED_UI_KEYS) {
        try {
          localStorage.removeItem(key);
        } catch {
          // ignore
        }
      }
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
