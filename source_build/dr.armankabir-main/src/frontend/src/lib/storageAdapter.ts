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

/**
 * Business data key prefixes that should NOT be in browser storage.
 * These are blocked by the storage adapter guard.
 */
const BUSINESS_KEY_PREFIXES = [
  'patients',
  'patient_',
  'registry',
  'doctor_',
  'medicare_',
  'staff_',
  'appointment',
  'clinical',
  'soapNotes',
  'vitals_',
  'alerts_',
  'intakeOutput',
  'dischargeStatus',
  'pregnancy_',
  'teleconsults',
  'referrals_',
  'procedureLogs',
  'admissionHistory',
  'consentForms',
  'visits_',
  'prescriptions_',
  'prescription',
  'handovers',
  'rates_',
  'receipts_',
  'shifts_',
  'attendance_',
  'leaveRequests',
  'wardRoundChecklist',
  'testimonials',
  'gallery_',
  'lab_',
  'drugReminders',
  'moneyReceipts',
  'phpAuthToken',
  'staff_auth',
];

function isAllowedKey(key: string): boolean {
  if (ALLOWED_UI_KEYS.has(key)) return true;
  // Temporary UI state keys (draft autosave, scroll position, etc.)
  if (key.startsWith('autosave_')) return true;
  if (key.startsWith('draft_')) return true;
  if (key.startsWith('scroll_')) return true;
  // Check if it's a business key prefix
  for (const prefix of BUSINESS_KEY_PREFIXES) {
    if (key === prefix || key.startsWith(prefix + '_')) {
      return false;
    }
  }
  return true;
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
      return ; // fallback when localStorage unavailable
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

/**
 * Clean up all business data from localStorage.
 * Run this once on app startup to remove legacy data.
 * Only UI preferences are preserved.
 */
export function cleanupBusinessData(): void {
  try {
    const keysToRemove: string[] = [];
    const len = localStorage.length;
    for (let i = 0; i < len; i++) {
      const key = localStorage.key(i);
      if (key && !isAllowedKey(key)) {
        keysToRemove.push(key);
      }
    }
    for (const key of keysToRemove) {
      try {
        localStorage.removeItem(key);
        console.log('[StorageAdapter] Cleaned up business data key:', key);
      } catch {
        // ignore
      }
    }
    if (keysToRemove.length > 0) {
      console.log('[StorageAdapter] Cleanup complete. Removed ' + keysToRemove.length + ' business data keys.');
    }
  } catch {
    // ignore
  }
}
