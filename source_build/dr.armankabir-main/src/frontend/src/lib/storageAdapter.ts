/**
 * Storage Adapter — thin wrapper around localStorage
 *
 * This is the only allowed interface to browser storage.
 * Provides basic error handling and a single point of access.
 *
 * Usage:
 *   import { storage } from '../lib/storageAdapter';
 *   const lang = storage.getItem('patient_language');
 *   storage.setItem('sidebar_collapsed', 'true');
 */

// ─── Core storage wrapper ─────────────────────────────────────────────────────

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
