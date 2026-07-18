/**
 * Storage Adapter — UI Preferences Only
 *
 * This adapter is now limited to non-sensitive UI preferences only.
 * All business data (patients, doctors, appointments, prescriptions,
 * payments, etc.) MUST be loaded/saved through the PHP API and MySQL.
 *
 * Authentication tokens MUST NOT be stored in browser storage.
 * Only PHP sessions and HttpOnly secure cookies are used for auth.
 *
 * ALLOWED KEYS (UI Preferences only):
 *   - patient_language
 *   - theme
 *   - sidebar_collapsed
 *   - table_layout_*
 *   - classroom_arman, classroom_samia
 *   - chamber_arman, chamber_samia
 *   - profile_arman, profile_samia
 *   - prescriptionHeaders_chamber, prescriptionHeaders_hospital
 *
 * Usage:
 *   import { storage } from '../lib/storageAdapter';
 *   const lang = storage.getItem('patient_language');
 */

// ── Core storage wrapper (UI preferences only) ─────────────────────────────

export const storage = {
  getItem(key: string): string | null {
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  },

  setItem(key: string, value: string): void {
    try {
      localStorage.setItem(key, value);
    } catch (err) {
      console.warn(`[StorageAdapter] Failed to write key "${key}":`, err);
    }
  },

  removeItem(key: string): void {
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

// ── UI Preference Helpers (the only data allowed in browser storage) ──────
// Business data helpers have been removed.
// Doctor registry, current doctor, and other business data must go through
// the PHP API via services/*.ts

export const storageHelpers = {
  /** Get a parsed JSON value (for UI prefs only) */
  getJSON<T = unknown>(key: string, fallback: T): T {
    const raw = storage.getItem(key);
    if (raw === null) return fallback;
    try {
      return JSON.parse(raw) as T;
    } catch {
      return fallback;
    }
  },

  /** Set a value as JSON string (for UI prefs only) */
  setJSON(key: string, value: unknown): void {
    storage.setItem(key, JSON.stringify(value));
  },
};
