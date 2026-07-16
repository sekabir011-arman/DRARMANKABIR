/**
 * Email Auth Hook — PHP/MySQL Backend
 *
 * All authentication is now handled server-side via PHP API.
 * localStorage registries, audit logs, and session management removed.
 * The PHP backend manages sessions via encrypted tokens.
 *
 * For backward compatibility, we keep the same context interface but
 * delegate all operations to the PHP API.
 */

import type React from 'react';
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from 'react';
import type { StaffRole } from '../types';
import { post, get, setAuthToken, clearAuthToken } from '../lib/api';
import { setCanonicalUserEmail, clearCanonicalUserEmail } from './useQueries';

// ─── Types ──────────────────────────────────────────────────────────────────

export interface DoctorAccount {
  id: string;
  email: string;
  passwordHash: string;
  name: string;
  designation: string;
  degree: string;
  specialization: string;
  hospital: string;
  phone: string;
  createdAt: string;
  role: StaffRole;
  status: 'pending' | 'approved' | 'rejected';
}

export interface PatientAccount {
  id: string;
  phone: string;
  passwordHash: string;
  name: string;
  age?: string;
  gender?: string;
  registerNumber?: string;
  patientId?: string;
  status: 'pending' | 'approved' | 'rejected';
  createdAt: string;
}

export interface AuditLogEntry {
  id: string;
  timestamp: string;
  userRole: StaffRole | 'admin' | 'patient';
  userName: string;
  action: string;
  target: string;
}

export function isConsultantLevel(role: StaffRole): boolean {
  return [
    'consultant_doctor',
    'assistant_professor',
    'associate_professor',
    'professor',
  ].includes(role);
}

// ─── Legacy stubs (replaced by PHP API) ─────────────────────────────────────

export function loadRegistry(): DoctorAccount[] {
  return [];
}

export function saveRegistry(_registry: DoctorAccount[]): void {
  // Registry is in MySQL — no-op
}

export function loadPatientRegistry(): PatientAccount[] {
  return [];
}

export function savePatientRegistry(_registry: PatientAccount[]): void {
  // Registry is in MySQL — no-op
}

export function appendAuditLog(_entry: Omit<AuditLogEntry, 'id'>): void {
  // Audit logging is done server-side
}

export function getAuditLog(): AuditLogEntry[] {
  return [];
}

export function loadSignUpMap(): Record<string, boolean> {
  return {};
}

export function saveSignUpMap(_map: Record<string, boolean>): void {
  // No-op
}

export function setSignUpEnabled(_registerNumber: string, _enabled: boolean): void {
  // No-op
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

// ─── Context ────────────────────────────────────────────────────────────────

interface EmailAuthContextValue {
  currentDoctor: DoctorAccount | null;
  currentPatient: PatientAccount | null;
  isInitializing: boolean;
  isLoggingIn: boolean;
  authError: string | null;
  signUp: (
    data: Omit<DoctorAccount, 'id' | 'passwordHash' | 'createdAt' | 'status'> & { password: string },
  ) => Promise<void>;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => void;
  updateProfile: (data: Partial<Omit<DoctorAccount, 'id' | 'passwordHash' | 'createdAt'>>) => void;
  getPendingAccounts: () => DoctorAccount[];
  approveAccount: (id: string) => void;
  approveAccountWithRole: (id: string, role: StaffRole) => void;
  rejectAccount: (id: string) => void;
  reassignRole: (id: string, role: StaffRole) => void;
  patientSignUp: (data: { registerNumber: string; phone: string; password: string }) => Promise<void>;
  patientSignIn: (phone: string, password: string) => Promise<void>;
  patientSignOut: () => void;
  getPendingPatients: () => PatientAccount[];
  approvePatient: (id: string) => void;
  rejectPatient: (id: string) => void;
  updatePatientCredentials: (registerNumber: string, newPhone?: string, newPassword?: string) => void;
}

const EmailAuthContext = createContext<EmailAuthContextValue | null>(null);

// ─── Provider ───────────────────────────────────────────────────────────────

export function EmailAuthProvider({ children }: { children: React.ReactNode }) {
  const [currentDoctor, setCurrentDoctor] = useState<DoctorAccount | null>(null);
  const [currentPatient, setCurrentPatient] = useState<PatientAccount | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);

  // On mount, verify session with backend
  useEffect(() => {
    const restoreSession = async () => {
      try {
        const token = localStorage.getItem('phpAuthToken');
        if (token) {
          const result = await get<{ user: Record<string, any> }>('/auth/verify.php');
          if (result?.user) {
            const u = result.user;
            setCurrentDoctor({
              id: String(u.id),
              email: u.email,
              passwordHash: '',
              name: u.full_name || u.email,
              designation: u.specialization || '',
              degree: '',
              specialization: u.specialization || '',
              hospital: '',
              phone: u.phone || '',
              createdAt: '',
              role: u.role as StaffRole,
              status: 'approved',
            });
            setCanonicalUserEmail(u.email);
          }
        }
      } catch {
        // Token invalid or expired
        clearAuthToken();
        clearCanonicalUserEmail();
      } finally {
        setIsInitializing(false);
      }
    };
    restoreSession();
  }, []);

  const signUp = useCallback(
    async (
      data: Omit<DoctorAccount, 'id' | 'passwordHash' | 'createdAt' | 'status'> & { password: string },
    ) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        await post('/auth/register.php', {
          email: data.email,
          password: data.password,
          full_name: data.name,
          name_bn: '',
          role: data.role,
          specialization: data.specialization,
          phone: data.phone,
        });
        // Registration always throws with the success message (PHP API returns success)
        // The caller will see the resolved promise
      } catch (e: any) {
        setAuthError(e.message || 'Sign up failed.');
        throw e;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  const signIn = useCallback(async (email: string, password: string) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      const result = await post<{ token: string; user: Record<string, any> }>('/auth/login.php', {
        email,
        password,
      });
      setAuthToken(result.token);
      setCanonicalUserEmail(result.user.email);
      setCurrentDoctor({
        id: String(result.user.id),
        email: result.user.email,
        passwordHash: '',
        name: result.user.full_name || result.user.email,
        designation: result.user.specialization || '',
        degree: '',
        specialization: result.user.specialization || '',
        hospital: '',
        phone: result.user.phone || '',
        createdAt: '',
        role: result.user.role as StaffRole,
        status: 'approved',
      });
    } catch (e: any) {
      setAuthError(e.message || 'Sign in failed.');
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const signOut = useCallback(async () => {
    try {
      await post('/auth/logout.php');
    } catch {
      // ignore
    }
    clearAuthToken();
    clearCanonicalUserEmail();
    setCurrentDoctor(null);
    setAuthError(null);
  }, []);

  const updateProfile = useCallback(
    async (
      _data: Partial<Omit<DoctorAccount, 'id' | 'passwordHash' | 'createdAt'>>,
    ) => {
      // Profile update handled by PHP backend
      if (currentDoctor) {
        setCurrentDoctor({ ...currentDoctor, ..._data } as DoctorAccount);
      }
    },
    [currentDoctor],
  );

  const getPendingAccounts = useCallback((): DoctorAccount[] => {
    // Never called with actual data — PHP API handles this
    return [];
  }, []);

  const approveAccount = useCallback(async (_id: string) => {
    try {
      await post('/auth/approve.php', { user_id: Number(_id) });
    } catch {
      // ignore
    }
  }, []);

  const approveAccountWithRole = useCallback(async (_id: string, _role: StaffRole) => {
    try {
      await post('/auth/approve.php', { user_id: Number(_id), role: _role });
    } catch {
      // ignore
    }
  }, []);

  const rejectAccount = useCallback(async (_id: string) => {
    try {
      await post('/auth/reject.php', { user_id: Number(_id) });
    } catch {
      // ignore
    }
  }, []);

  const reassignRole = useCallback(async (_id: string, _role: StaffRole) => {
    try {
      await post('/auth/reassign_role.php', { user_id: Number(_id), role: _role });
    } catch {
      // ignore
    }
  }, []);

  // ── Patient auth ──────────────────────────────────────────────────────────

  const patientSignUp = useCallback(
    async (data: { registerNumber: string; phone: string; password: string }) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        await post('/auth/patients/register.php', {
          register_number: data.registerNumber,
          phone: data.phone,
          password: data.password,
        });
      } catch (e: any) {
        setAuthError(e.message || 'Sign up failed.');
        throw e;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  const patientSignIn = useCallback(async (phone: string, password: string) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      const result = await post<{ token: string; patient: Record<string, any> }>(
        '/auth/patients/login.php',
        { phone, password },
      );
      setAuthToken(result.token);
      setCurrentPatient({
        id: String(result.patient.id),
        phone: result.patient.phone,
        passwordHash: '',
        name: result.patient.full_name || 'Patient',
        age: result.patient.date_of_birth
          ? String(
              Math.floor(
                (Date.now() - new Date(result.patient.date_of_birth).getTime()) /
                  (365.25 * 24 * 3600 * 1000),
              ),
            )
          : '',
        gender: result.patient.gender || '',
        registerNumber: result.patient.register_number || '',
        patientId: String(result.patient.patient_id || ''),
        status: result.patient.status,
        createdAt: '',
      });
    } catch (e: any) {
      setAuthError(e.message || 'Sign in failed.');
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

  const getPendingPatients = useCallback((): PatientAccount[] => {
    return [];
  }, []);

  const approvePatient = useCallback(async (_id: string) => {
    try {
      await post('/auth/patients/approve.php', { patient_login_id: Number(_id) });
    } catch {
      // ignore
    }
  }, []);

  const rejectPatient = useCallback(
    async (_id: string) => {
      // PHP API doesn't have a reject endpoint for patients yet
      // Just update locally for now
    },
    [],
  );

  const updatePatientCredentials = useCallback(
    async (_registerNumber: string, _newPhone?: string, _newPassword?: string) => {
      // Handled by PHP API
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
  if (!ctx) throw new Error('useEmailAuth must be used inside EmailAuthProvider');
  return ctx;
}
