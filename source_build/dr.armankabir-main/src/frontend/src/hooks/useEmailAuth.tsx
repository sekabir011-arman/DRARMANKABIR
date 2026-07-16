/**
 * Email Auth — PHP/MySQL Backend
 *
 * All authentication now goes through the PHP API.
 * localStorage registry/session keys are no longer used.
 */

import type React from 'react';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { get, post, setAuthToken, clearAuthToken, ApiError } from '../lib/api';
import { setCanonicalUserEmail, clearCanonicalUserEmail } from './useQueries';
import type { StaffRole } from '../types';

// ─── Types matching PHP API responses ────────────────────────────────────

export interface DoctorAccount {
  id: number;
  email: string;
  full_name: string;
  name_bn?: string;
  role: StaffRole;
  specialization?: string;
  phone?: string;
  photo_url?: string;
  signature_url?: string;
  bmdc_registration?: string;
  is_active?: boolean;
  last_login_at?: string;
  created_at?: string;
  registration_status?: 'pending' | 'approved' | 'rejected';
}

export interface PatientAccount {
  id: number;
  patient_id: number;
  phone: string;
  full_name: string;
  name_bn?: string;
  gender?: string;
  date_of_birth?: string;
  register_number?: string;
  photo_url?: string;
  status: 'pending' | 'approved' | 'rejected';
}

export interface AuditLogEntry {
  id: number;
  timestamp: string;
  user_role: string;
  user_name: string;
  action: string;
  target: string;
}

const VALID_ROLES: StaffRole[] = [
  'admin',
  'consultant_doctor',
  'assistant_professor',
  'associate_professor',
  'professor',
  'medical_officer',
  'assistant_registrar',
  'registrar',
  'intern_doctor',
  'nurse',
  'reception',
  'staff',
  'patient',
  'doctor',
];

export function isConsultantLevel(role: StaffRole): boolean {
  return ['consultant_doctor', 'assistant_professor', 'associate_professor', 'professor'].includes(role);
}

// ── Legacy localStorage loaders (kept for type compatibility) ────────────

export function loadRegistry(): DoctorAccount[] {
  return [];
}

export function saveRegistry(_registry: DoctorAccount[]): void {
  // Handled by PHP API
}

export function loadPatientRegistry(): PatientAccount[] {
  return [];
}

export function savePatientRegistry(_registry: PatientAccount[]): void {
  // Handled by PHP API
}

export function appendAuditLog(_entry: Omit<AuditLogEntry, 'id'>): void {
  // Handled by PHP API
}

export function getAuditLog(): AuditLogEntry[] {
  return [];
}

export function loadSignUpMap(): Record<string, boolean> {
  return {};
}

export function saveSignUpMap(_map: Record<string, boolean>): void {
  // Handled by PHP API
}

export function setSignUpEnabled(_registerNumber: string, _enabled: boolean): void {
  // Handled by PHP API
}

export function isSignUpEnabled(_registerNumber: string): boolean {
  return false;
}

function normalizeRegNo(rn: string): string {
  const parts = rn.trim().split('/');
  if (parts.length === 2) {
    const num = Number.parseInt(parts[0].trim(), 10);
    return `${Number.isNaN(num) ? parts[0].trim() : num}/${parts[1].trim()}`;
  }
  return rn.trim().toLowerCase();
}

// ─── Context types ──────────────────────────────────────────────────────

interface EmailAuthContextValue {
  currentDoctor: DoctorAccount | null;
  currentPatient: PatientAccount | null;
  isInitializing: boolean;
  isLoggingIn: boolean;
  authError: string | null;
  signUp: (data: {
    email: string;
    password: string;
    full_name: string;
    name_bn?: string;
    role?: StaffRole;
    specialization?: string;
    phone?: string;
    bmdc_registration?: string;
  }) => Promise<void>;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  updateProfile: (data: Partial<DoctorAccount>) => Promise<void>;
  getPendingAccounts: () => Promise<DoctorAccount[]>;
  approveAccount: (userId: number, role?: string) => Promise<void>;
  approveAccountWithRole: (userId: number, role: StaffRole) => Promise<void>;
  rejectAccount: (userId: number) => Promise<void>;
  reassignRole: (userId: number, role: StaffRole) => Promise<void>;
  // Patient auth
  patientSignUp: (data: { registerNumber: string; phone: string; password: string }) => Promise<void>;
  patientSignIn: (phone: string, password: string) => Promise<void>;
  patientSignOut: () => void;
  getPendingPatients: () => Promise<any[]>;
  approvePatient: (patientLoginId: number) => Promise<void>;
  rejectPatient: (patientLoginId: number) => Promise<void>;
  updatePatientCredentials: (registerNumber: string, newPhone?: string, newPassword?: string) => Promise<void>;
}

const EmailAuthContext = createContext<EmailAuthContextValue | null>(null);

export function EmailAuthProvider({ children }: { children: React.ReactNode }) {
  const [currentDoctor, setCurrentDoctor] = useState<DoctorAccount | null>(null);
  const [currentPatient, setCurrentPatient] = useState<PatientAccount | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);

  // On mount, try to restore session via verify endpoint
  useEffect(() => {
    const restoreSession = async () => {
      try {
        const token = localStorage.getItem('phpAuthToken');
        if (token) {
          const result = await get<{ user: DoctorAccount }>('/auth/verify.php');
          if (result?.user) {
            setCurrentDoctor(result.user);
            setCanonicalUserEmail(result.user.email);
          }
        }
      } catch {
        // Token expired or invalid - clear it
        clearAuthToken();
        clearCanonicalUserEmail();
      } finally {
        setIsInitializing(false);
      }
    };
    restoreSession();
  }, []);

  const signUp = useCallback(async (data: {
    email: string;
    password: string;
    full_name: string;
    name_bn?: string;
    role?: StaffRole;
    specialization?: string;
    phone?: string;
    bmdc_registration?: string;
  }) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      await post('/auth/register.php', {
        email: data.email,
        password: data.password,
        full_name: data.full_name,
        name_bn: data.name_bn || '',
        role: data.role || 'doctor',
        specialization: data.specialization || '',
        phone: data.phone || '',
        bmdc_registration: data.bmdc_registration || '',
      });
      throw new Error('Account created! Please wait for admin approval before logging in.');
    } catch (e: unknown) {
      const msg = e instanceof ApiError ? e.message : e instanceof Error ? e.message : 'Sign up failed.';
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      const result = await post<{ token: string; user: DoctorAccount }>('/auth/login.php', {
        email,
        password,
      });
      setAuthToken(result.token);
      setCanonicalUserEmail(result.user.email);
      setCurrentDoctor(result.user);
    } catch (e: unknown) {
      const msg = e instanceof ApiError ? e.message : e instanceof Error ? e.message : 'Sign in failed.';
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const signOut = useCallback(async () => {
    try {
      await post('/auth/logout.php');
    } catch {
      // Best-effort logout
    }
    clearAuthToken();
    clearCanonicalUserEmail();
    setCurrentDoctor(null);
    setAuthError(null);
  }, []);

  const updateProfile = useCallback(async (_data: Partial<DoctorAccount>) => {
    // Profile update goes through PHP API
    // For now, we can re-fetch the user profile
    try {
      const result = await get<{ user: DoctorAccount }>('/auth/verify.php');
      if (result?.user) {
        setCurrentDoctor(result.user);
      }
    } catch {
      // ignore
    }
  }, []);

  const getPendingAccounts = useCallback(async (): Promise<DoctorAccount[]> => {
    try {
      const result = await get<{ users: DoctorAccount[] }>('/auth/pending.php');
      return result.users ?? [];
    } catch {
      return [];
    }
  }, []);

  const approveAccount = useCallback(async (userId: number, role?: string) => {
    await post('/auth/approve.php', { user_id: userId, role: role || '' });
  }, []);

  const approveAccountWithRole = useCallback(async (userId: number, role: StaffRole) => {
    await approveAccount(userId, role);
  }, [approveAccount]);

  const rejectAccount = useCallback(async (userId: number) => {
    await post('/auth/reject.php', { user_id: userId });
  }, []);

  const reassignRole = useCallback(async (userId: number, role: StaffRole) => {
    await post('/auth/reassign_role.php', { user_id: userId, role });
  }, []);

  // ── Patient auth ───────────────────────────────────────────────────────

  const patientSignUp = useCallback(async (data: { registerNumber: string; phone: string; password: string }) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      await post('/auth/patients/register.php', {
        register_number: data.registerNumber,
        phone: data.phone,
        password: data.password,
      });
      throw new Error('Account created! Please wait for doctor approval before logging in.');
    } catch (e: unknown) {
      const msg = e instanceof ApiError ? e.message : e instanceof Error ? e.message : 'Sign up failed.';
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const patientSignIn = useCallback(async (phone: string, password: string) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      const result = await post<{ token: string; patient: PatientAccount }>('/auth/patients/login.php', {
        phone,
        password,
      });
      setAuthToken(result.token);
      setCurrentPatient(result.patient);
    } catch (e: unknown) {
      const msg = e instanceof ApiError ? e.message : e instanceof Error ? e.message : 'Login failed.';
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const patientSignOut = useCallback(() => {
    clearAuthToken();
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const getPendingPatients = useCallback(async (): Promise<any[]> => {
    try {
      const result = await get<{ users: any[] }>('/auth/pending.php?type=patient');
      return result.users ?? [];
    } catch {
      return [];
    }
  }, []);

  const approvePatient = useCallback(async (patientLoginId: number) => {
    await post('/auth/patients/approve.php', { patient_login_id: patientLoginId });
  }, []);

  const rejectPatient = useCallback(async (_patientLoginId: number) => {
    // TODO: Add reject endpoint for patients if needed
    throw new Error('Patient rejection not yet implemented in API');
  }, []);

  const updatePatientCredentials = useCallback(async (_registerNumber: string, _newPhone?: string, _newPassword?: string) => {
    // TODO: Add update credentials endpoint if needed
    throw new Error('Update patient credentials not yet implemented in API');
  }, []);

  return (
    <EmailAuthContext.Provider
      value={{
        currentDoctor,
        currentPatient,
        isInitializing,
        isLoggingIn,
        authError,
        signUp,
        signIn,
        signOut,
        updateProfile,
        getPendingAccounts,
        approveAccount,
        approveAccountWithRole,
        rejectAccount,
        reassignRole,
        patientSignUp,
        patientSignIn,
        patientSignOut,
        getPendingPatients,
        approvePatient,
        rejectPatient,
        updatePatientCredentials,
      }}
    >
      {children}
    </EmailAuthContext.Provider>
  );
}

export function useEmailAuth(): EmailAuthContextValue {
  const ctx = useContext(EmailAuthContext);
  if (!ctx) throw new Error('useEmailAuth must be used inside EmailAuthProvider');
  return ctx;
}

// ── Inactivity Timer ──────────────────────────────────────────────────────

const INACTIVITY_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
const INACTIVITY_WARNING_MS = 13 * 60 * 1000; // 13 minutes (2 min before logout)
const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'] as const;

export interface InactivityTimerState {
  showWarning: boolean;
  secondsRemaining: number;
  resetTimer: () => void;
}

export function useInactivityTimer(onLogout: () => void): InactivityTimerState {
  const [showWarning, setShowWarning] = useState(false);
  const [secondsRemaining, setSecondsRemaining] = useState(120);

  const logoutTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const warningTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const lastActivityRef = useRef<number>(Date.now());

  const clearAllTimers = useCallback(() => {
    if (logoutTimerRef.current) clearTimeout(logoutTimerRef.current);
    if (warningTimerRef.current) clearTimeout(warningTimerRef.current);
    if (countdownRef.current) clearInterval(countdownRef.current);
    logoutTimerRef.current = null;
    warningTimerRef.current = null;
    countdownRef.current = null;
  }, []);

  const startTimers = useCallback(() => {
    clearAllTimers();
    setShowWarning(false);
    setSecondsRemaining(120);

    warningTimerRef.current = setTimeout(() => {
      setShowWarning(true);
      setSecondsRemaining(120);
      countdownRef.current = setInterval(() => {
        setSecondsRemaining((s) => {
          if (s <= 1) {
            if (countdownRef.current) clearInterval(countdownRef.current);
            return 0;
          }
          return s - 1;
        });
      }, 1000);
    }, INACTIVITY_WARNING_MS);

    logoutTimerRef.current = setTimeout(() => {
      clearAllTimers();
      setShowWarning(false);
      onLogout();
    }, INACTIVITY_TIMEOUT_MS);
  }, [clearAllTimers, onLogout]);

  const resetTimer = useCallback(() => {
    lastActivityRef.current = Date.now();
    startTimers();
  }, [startTimers]);

  useEffect(() => {
    startTimers();
    return clearAllTimers;
  }, [startTimers, clearAllTimers]);

  useEffect(() => {
    const handler = () => resetTimer();
    for (const ev of ACTIVITY_EVENTS) {
      window.addEventListener(ev, handler, { passive: true });
    }
    return () => {
      for (const ev of ACTIVITY_EVENTS) {
        window.removeEventListener(ev, handler);
      }
    };
  }, [resetTimer]);

  return { showWarning, secondsRemaining, resetTimer };
}
