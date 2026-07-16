/**
 * Email Authentication — PHP/MySQL Backend
 *
 * All auth operations now go through the PHP API.
 * localStorage is no longer used for auth state.
 * Session tokens are stored in memory and persisted to localStorage
 * only for page refresh survival.
 */

import type React from 'react';
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import { post, get, setAuthToken, clearAuthToken } from '../lib/api';
import type { ApiError } from '../lib/api';
import type { StaffRole } from '../types';

export interface DoctorAccount {
  id: number;
  email: string;
  full_name: string;
  name_bn?: string;
  role: StaffRole;
  specialization?: string;
  phone?: string;
  designation?: string;
  degree?: string;
  photo_url?: string;
  signature_url?: string;
  bmdc_registration?: string;
  is_active?: boolean;
}

export interface PatientAccount {
  id: string;
  patient_id: number;
  phone: string;
  full_name: string;
  name_bn?: string;
  gender?: string;
  date_of_birth?: string;
  register_number?: string;
  photo_url?: string;
  status?: string;
}

// Valid staff roles (kept for backward compatibility)
export const VALID_ROLES: StaffRole[] = [
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
  'doctor',
];

export function isConsultantLevel(role: StaffRole): boolean {
  return [
    'consultant_doctor',
    'assistant_professor',
    'associate_professor',
    'professor',
  ].includes(role);
}

// ── Legacy helpers (kept for backward compatibility with components) ────────

export function loadRegistry(): DoctorAccount[] {
  return [];
}

export function saveRegistry(_registry: DoctorAccount[]): void {
  // Registry is server-side
}

export function loadPatientRegistry(): PatientAccount[] {
  return [];
}

export function savePatientRegistry(_registry: PatientAccount[]): void {
  // Registry is server-side
}

export function appendAuditLog(_entry: Record<string, unknown>): void {
  // Audit logging is server-side
}

export function getAuditLog(): Record<string, unknown>[] {
  return [];
}

export function loadSignUpMap(): Record<string, boolean> {
  return {};
}

export function saveSignUpMap(_map: Record<string, boolean>): void {
  // Server-side
}

export function setSignUpEnabled(_registerNumber: string, _enabled: boolean): void {
  // Server-side
}

export function isSignUpEnabled(_registerNumber: string): boolean {
  return false;
}

// ── Context type ───────────────────────────────────────────────────────────

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
    role?: string;
    specialization?: string;
    phone?: string;
    bmdc_registration?: string;
  }) => Promise<void>;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  updateProfile: (data: Partial<DoctorAccount>) => Promise<void>;
  getPendingAccounts: () => Promise<DoctorAccount[]>;
  approveAccount: (id: number, role?: string) => Promise<void>;
  approveAccountWithRole: (id: number, role: StaffRole) => Promise<void>;
  rejectAccount: (id: number) => Promise<void>;
  reassignRole: (id: number, role: StaffRole) => Promise<void>;
  // Patient auth
  patientSignUp: (data: {
    registerNumber: string;
    phone: string;
    password: string;
  }) => Promise<void>;
  patientSignIn: (phone: string, password: string) => Promise<void>;
  patientSignOut: () => Promise<void>;
  getPendingPatients: () => Promise<PatientAccount[]>;
  approvePatient: (id: number) => Promise<void>;
  rejectPatient: (id: number) => Promise<void>;
  updatePatientCredentials: (
    registerNumber: string,
    newPhone?: string,
    newPassword?: string,
  ) => Promise<void>;
}

const SESSION_STORAGE_KEY = 'phpAuthToken';

function loadSessionToken(): string | null {
  try {
    return localStorage.getItem(SESSION_STORAGE_KEY);
  } catch {
    return null;
  }
}

function saveSessionToken(token: string): void {
  try {
    localStorage.setItem(SESSION_STORAGE_KEY, token);
    setAuthToken(token);
  } catch {
    // ignore
  }
}

function clearSessionToken(): void {
  try {
    localStorage.removeItem(SESSION_STORAGE_KEY);
    clearAuthToken();
  } catch {
    // ignore
  }
}

const EmailAuthContext = createContext<EmailAuthContextValue | null>(null);

export function EmailAuthProvider({ children }: { children: React.ReactNode }) {
  const [currentDoctor, setCurrentDoctor] = useState<DoctorAccount | null>(null);
  const [currentPatient, setCurrentPatient] = useState<PatientAccount | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);

  // On mount, check for existing token and verify it
  useEffect(() => {
    const token = loadSessionToken();
    if (token) {
      setAuthToken(token);
      // Verify the token is still valid
      get<{ user?: DoctorAccount }>('/auth/verify.php')
        .then((data) => {
          if (data?.user) {
            setCurrentDoctor(data.user);
          } else {
            // Try patient verification
            return get<{ patient?: PatientAccount }>('/auth/patients/verify.php')
              .then((pData) => {
                if (pData?.patient) {
                  setCurrentPatient(pData.patient);
                }
              })
              .catch(() => {
                clearSessionToken();
              });
          }
        })
        .catch(() => {
          clearSessionToken();
        })
        .finally(() => {
          setIsInitializing(false);
        });
    } else {
      setIsInitializing(false);
    }
  }, []);

  const signUp = useCallback(
    async (data: {
      email: string;
      password: string;
      full_name: string;
      name_bn?: string;
      role?: string;
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
          name_bn: data.name_bn ?? '',
          role: data.role ?? 'doctor',
          specialization: data.specialization ?? '',
          phone: data.phone ?? '',
          bmdc_registration: data.bmdc_registration ?? '',
        });
        // Don't throw — the API returns a success message for pending accounts
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : 'Sign up failed.';
        setAuthError(msg);
        throw e;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  const signIn = useCallback(
    async (email: string, password: string) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        const result = await post<{ token: string; user: DoctorAccount }>(
          '/auth/login.php',
          { email, password },
        );
        saveSessionToken(result.token);
        setCurrentDoctor(result.user);
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : 'Sign in failed.';
        setAuthError(msg);
        throw e;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  const signOut = useCallback(async () => {
    try {
      await post('/auth/logout.php');
    } catch {
      // Ignore logout errors
    }
    clearSessionToken();
    setCurrentDoctor(null);
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const updateProfile = useCallback(
    async (data: Partial<DoctorAccount>) => {
      if (!currentDoctor) return;
      try {
        const updated = await post<DoctorAccount>('/auth/update_profile.php', data);
        setCurrentDoctor(updated);
      } catch (e) {
        console.error('Profile update failed:', e);
      }
    },
    [currentDoctor],
  );

  const getPendingAccounts = useCallback(async (): Promise<DoctorAccount[]> => {
    try {
      const result = await get<{ users: DoctorAccount[] }>('/auth/pending.php');
      return result.users ?? [];
    } catch {
      return [];
    }
  }, []);

  const approveAccount = useCallback(
    async (id: number, role?: string) => {
      try {
        await post('/auth/approve.php', {
          user_id: id,
          ...(role ? { role } : {}),
        });
      } catch (e) {
        console.error('Approval failed:', e);
      }
    },
    [],
  );

  const approveAccountWithRole = useCallback(
    async (id: number, role: StaffRole) => {
      await approveAccount(id, role);
    },
    [approveAccount],
  );

  const reassignRole = useCallback(
    async (id: number, role: StaffRole) => {
      try {
        await post('/auth/reassign_role.php', {
          user_id: id,
          role,
        });
      } catch (e) {
        console.error('Role reassignment failed:', e);
      }
    },
    [],
  );

  const rejectAccount = useCallback(
    async (id: number) => {
      try {
        await post('/auth/reject.php', {
          user_id: id,
        });
      } catch (e) {
        console.error('Rejection failed:', e);
      }
    },
    [],
  );

  // ── Patient auth ──────────────────────────────────────────────────────────

  const patientSignUp = useCallback(
    async (data: {
      registerNumber: string;
      phone: string;
      password: string;
    }) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        await post('/auth/patients/register.php', {
          register_number: data.registerNumber,
          phone: data.phone,
          password: data.password,
        });
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : 'Registration failed.';
        setAuthError(msg);
        throw e;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  const patientSignIn = useCallback(
    async (phone: string, password: string) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        const result = await post<{ token: string; patient: PatientAccount }>(
          '/auth/patients/login.php',
          { phone, password },
        );
        saveSessionToken(result.token);
        setCurrentPatient(result.patient);
      } catch (e: unknown) {
        const msg =
          e instanceof Error ? e.message : 'Sign in failed.';
        setAuthError(msg);
        throw e;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  const patientSignOut = useCallback(async () => {
    try {
      await post('/auth/logout.php');
    } catch {
      // Ignore
    }
    clearSessionToken();
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const getPendingPatients = useCallback(async (): Promise<PatientAccount[]> => {
    try {
      const result = await get<{ patients: PatientAccount[] }>(
        '/auth/patients/pending.php',
      );
      return result.patients ?? [];
    } catch {
      return [];
    }
  }, []);

  const approvePatient = useCallback(
    async (id: number) => {
      try {
        await post('/auth/patients/approve.php', {
          patient_login_id: id,
        });
      } catch (e) {
        console.error('Patient approval failed:', e);
      }
    },
    [],
  );

  const rejectPatient = useCallback(
    async (_id: number) => {
      // Reject patient endpoint not yet implemented in PHP
      console.warn('Patient reject not yet implemented');
    },
    [],
  );

  const updatePatientCredentials = useCallback(
    async (
      _registerNumber: string,
      _newPhone?: string,
      _newPassword?: string,
    ) => {
      // Update patient credentials endpoint not yet implemented in PHP
      console.warn('updatePatientCredentials not yet implemented');
    },
    [],
  );

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
  if (!ctx)
    throw new Error('useEmailAuth must be used inside EmailAuthProvider');
  return ctx;
}

// ── Inactivity Timer ────────────────────────────────────────────────────────

const INACTIVITY_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
const INACTIVITY_WARNING_MS = 13 * 60 * 1000; // 13 minutes (2 min before logout)
const ACTIVITY_EVENTS = [
  'mousemove',
  'keydown',
  'click',
  'touchstart',
  'scroll',
] as const;

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
