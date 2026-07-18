/**
 * Storage Adapter — Central Access Layer for Client-Side Persistence
 *
 * This adapter replaces direct localStorage access in components.
 * It mirrors the localStorage API exactly so existing code works without changes.
 *
 * Migration path:
 *   When a PHP API endpoint becomes available for a data type, change the
 *   component to use the service/hook instead of this adapter. No other
 *   changes needed in the component.
 *
 * Usage:
 *   import { storage } from '../lib/storageAdapter';
 *   const data = storage.getItem('key');
 *   storage.setItem('key', 'value');
 */

// ── Core storage wrapper ───────────────────────────────────────────────────

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
      return 0;
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

// ── Typed helpers for common business data patterns ─────────────────────
// These provide structured access and a migration path to the PHP API.

export const storageHelpers = {
  /** Get a parsed JSON value */
  getJSON<T = unknown>(key: string, fallback: T): T {
    const raw = storage.getItem(key);
    if (raw === null) return fallback;
    try {
      return JSON.parse(raw) as T;
    } catch {
      return fallback;
    }
  },

  /** Set a value as JSON string */
  setJSON(key: string, value: unknown): void {
    storage.setItem(key, JSON.stringify(value));
  },

  /** Get an array (parsed from JSON) */
  getArray<T = unknown>(key: string): T[] {
    return this.getJSON<T[]>(key, []);
  },

  /** Get doctor registry */
  getDoctorsRegistry<T = Record<string, unknown>>(): T[] {
    return this.getArray<T>('medicare_doctors_registry');
  },

  /** Set doctor registry */
  setDoctorsRegistry(data: unknown[]): void {
    this.setJSON('medicare_doctors_registry', data);
  },

  /** Get current doctor */
  getCurrentDoctor<T = Record<string, unknown>>(): T | null {
    const raw = storage.getItem('medicare_current_doctor');
    if (!raw) return null;
    try {
      return JSON.parse(raw) as T;
    } catch {
      return null;
    }
  },

  /** Set current doctor */
  setCurrentDoctor(data: Record<string, unknown> | null): void {
    if (data === null) {
      storage.removeItem('medicare_current_doctor');
    } else {
      this.setJSON('medicare_current_doctor', data);
    }
  },
};
