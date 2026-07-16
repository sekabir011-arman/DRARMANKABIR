/**
 * Authentication hooks — PHP/MySQL Backend
 *
 * All auth operations now use the PHP/MySQL API.
 * localStorage is no longer the primary auth store.
 * The PHP backend manages sessions via tokens.
 */

import type React from "react";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import type { StaffRole } from "../types";
import { post, get, clearAuthToken, setAuthToken } from "../lib/api";
import { setCanonicalUserEmail, clearCanonicalUserEmail } from "./useQueries";

// ─── Types ───────────────────────────────────────────────────────────────────

export interface DoctorAccount {
  id: string;
  email: string;
  name: string;
  designation: string;
  degree: string;
  specialization: string;
  hospital: string;
  phone: string;
  createdAt: string;
  role: StaffRole;
  status: "pending" | "approved" | "rejected";
}

export interface PatientAccount {
  id: string;
  phone: string;
  name: string;
  age?: string;
  gender?: string;
  registerNumber?: string;
  patientId?: string;
  status: "pending" | "approved" | "rejected";
  createdAt: string;
}

export interface AuditLogEntry {
  id: string;
  timestamp: string;
  userRole: StaffRole | "admin" | "patient";
  userName: string;
  action: string;
  target: string;
}

// ─── Role helpers ────────────────────────────────────────────────────────────

const VALID_ROLES: StaffRole[] = [
  "admin",
  "consultant_doctor",
  "assistant_professor",
  "associate_professor",
  "professor",
  "medical_officer",
  "assistant_registrar",
  "registrar",
  "intern_doctor",
  "nurse",
  "reception",
  "staff",
  "patient",
  "doctor",
];

export function isConsultantLevel(role: StaffRole): boolean {
  return [
    "consultant_doctor",
    "assistant_professor",
    "associate_professor",
    "professor",
  ].includes(role);
}

// ─── Registry helpers (now fetch from PHP API) ───────────────────────────────

export async function loadRegistry(): Promise<DoctorAccount[]> {
  try {
    const data = await get<{ users: any[] }>('/staff/list.php');
    return (data.users || []).map((d: any) => ({
      id: String(d.id),
      email: d.email || '',
      name: d.full_name || d.name || '',
      designation: d.designation || '',
      degree: d.degree || '',
      specialization: d.specialization || '',
      hospital: d.hospital || '',
      phone: d.phone || '',
      createdAt: d.created_at || '',
      role: VALID_ROLES.includes(d.role) ? d.role : "doctor",
      status: d.registration_status || "approved",
    }));
  } catch {
    return [];
  }
}

export async function loadPatientRegistry(): Promise<PatientAccount[]> {
  try {
    // Fetch patients with login accounts
    const data = await get<{ items: any[] }>('/patients/list.php', { has_login: '1' });
    return (data.items || []).map((p: any) => ({
      id: String(p.patient_login_id || p.id),
      phone: p.phone || '',
      name: p.full_name || p.name || '',
      age: p.age || '',
      gender: p.gender || '',
      registerNumber: p.register_number || '',
      patientId: String(p.id),
      status: p.login_status || p.status || "approved",
      createdAt: p.created_at || '',
    }));
  } catch {
    return [];
  }
}

// ─── Context ─────────────────────────────────────────────────────────────────

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
  signOut: () => void;
  updateProfile: (data: Record<string, any>) => Promise<void>;
  getPendingAccounts: () => Promise<DoctorAccount[]>;
  approveAccount: (id: string) => Promise<void>;
  approveAccountWithRole: (id: string, role: StaffRole) => Promise<void>;
  rejectAccount: (id: string) => Promise<void>;
  reassignRole: (id: string, role: StaffRole) => Promise<void>;
  // Patient auth
  patientSignUp: (data: {
    registerNumber: string;
    phone: string;
    password: string;
  }) => Promise<void>;
  patientSignIn: (phone: string, password: string) => Promise<void>;
  patientSignOut: () => void;
  getPendingPatients: () => Promise<PatientAccount[]>;
  approvePatient: (id: string) => Promise<void>;
  rejectPatient: (id: string) => Promise<void>;
}

const EmailAuthContext = createContext<EmailAuthContextValue | null>(null);

export function EmailAuthProvider({ children }: { children: React.ReactNode }) {
  const [currentDoctor, setCurrentDoctor] = useState<DoctorAccount | null>(null);
  const [currentPatient, setCurrentPatient] = useState<PatientAccount | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);

  // On mount, verify existing token
  useEffect(() => {
    const init = async () => {
      try {
        const user = await get<Record<string, any>>('/auth/verify.php');
        if (user && user.id) {
          const doctor: DoctorAccount = {
            id: String(user.id),
            email: user.email || '',
            name: user.full_name || '',
            designation: user.designation || '',
            degree: user.degree || '',
            specialization: user.specialization || '',
            hospital: user.hospital || '',
            phone: user.phone || '',
            createdAt: user.created_at || '',
            role: VALID_ROLES.includes(user.role) ? user.role : "doctor",
            status: user.registration_status || "approved",
          };
          setCurrentDoctor(doctor);
          if (user.email) setCanonicalUserEmail(user.email);
        }
      } catch {
        // Token invalid or expired
        clearAuthToken();
        clearCanonicalUserEmail();
      }
      setIsInitializing(false);
    };
    init();
  }, []);

  const signUp = useCallback(async (data: {
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
      await post('/auth/register.php', data);
      // Registration takes effect after admin approval
    } catch (e: any) {
      const msg = e.message || "Sign up failed.";
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
      const result = await post<{ token: string; user: Record<string, any> }>('/auth/login.php', { email, password });
      const userData = result.user;
      setAuthToken(result.token);
      setCanonicalUserEmail(userData.email);

      const doctor: DoctorAccount = {
        id: String(userData.id),
        email: userData.email || '',
        name: userData.full_name || '',
        designation: userData.designation || '',
        degree: userData.degree || '',
        specialization: userData.specialization || '',
        hospital: userData.hospital || '',
        phone: userData.phone || '',
        createdAt: '',
        role: VALID_ROLES.includes(userData.role) ? userData.role : "doctor",
        status: "approved",
      };
      setCurrentDoctor(doctor);
    } catch (e: any) {
      const msg = e.message || "Sign in failed.";
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
      // Proceed with local logout even if server call fails
    }
    clearAuthToken();
    clearCanonicalUserEmail();
    setCurrentDoctor(null);
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const updateProfile = useCallback(async (data: Record<string, any>) => {
    // Profile updates handled by PHP backend
    if (!currentDoctor) return;
    try {
      const updated = await post<Record<string, any>>('/auth/update_profile.php', data);
      if (updated?.user) {
        setCurrentDoctor((prev) => prev ? { ...prev, ...updated.user } : prev);
      }
    } catch {
      // Silently fail
    }
  }, [currentDoctor]);

  const getPendingAccounts = useCallback(async (): Promise<DoctorAccount[]> => {
    try {
      const data = await get<{ users: any[] }>('/auth/pending.php');
      return (data.users || []).map((d: any) => ({
        id: String(d.id),
        email: d.email || '',
        name: d.full_name || '',
        designation: '',
        degree: '',
        specialization: d.specialization || '',
        hospital: '',
        phone: d.phone || '',
        createdAt: d.created_at || '',
        role: VALID_ROLES.includes(d.role) ? d.role : "doctor",
        status: "pending",
      }));
    } catch {
      return [];
    }
  }, []);

  const approveAccount = useCallback(async (id: string) => {
    await post('/auth/approve.php', { user_id: parseInt(id, 10) });
  }, []);

  const approveAccountWithRole = useCallback(async (id: string, role: StaffRole) => {
    await post('/auth/approve.php', { user_id: parseInt(id, 10), role });
  }, []);

  const rejectAccount = useCallback(async (id: string) => {
    await post('/auth/reject.php', { user_id: parseInt(id, 10) });
  }, []);

  const reassignRole = useCallback(async (id: string, role: StaffRole) => {
    await post('/auth/reassign_role.php', { user_id: parseInt(id, 10), role });
  }, []);

  // ── Patient auth ──────────────────────────────────────────────────────────

  const patientSignUp = useCallback(async (data: {
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
    } catch (e: any) {
      const msg = e.message || "Sign up failed.";
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
      const result = await post<{ token: string; patient: Record<string, any> }>('/auth/patients/login.php', { phone, password });
      const patientData = result.patient;
      // Store patient token separately
      try {
        localStorage.setItem('phpPatientToken', result.token);
      } catch {}

      const patient: PatientAccount = {
        id: String(patientData.id),
        phone: patientData.phone || '',
        name: patientData.full_name || '',
        age: '',
        gender: patientData.gender || '',
        registerNumber: patientData.register_number || '',
        patientId: String(patientData.patient_id),
        status: patientData.status || "approved",
        createdAt: '',
      };
      setCurrentPatient(patient);
    } catch (e: any) {
      const msg = e.message || "Sign in failed.";
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const patientSignOut = useCallback(() => {
    try {
      localStorage.removeItem('phpPatientToken');
    } catch {}
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const getPendingPatients = useCallback(async (): Promise<PatientAccount[]> => {
    try {
      const data = await get<{ items: any[] }>('/patients/list.php', { login_status: 'pending' });
      return (data.items || []).map((p: any) => ({
        id: String(p.patient_login_id || p.id),
        phone: p.phone || '',
        name: p.full_name || '',
        age: '',
        gender: p.gender || '',
        registerNumber: p.register_number || '',
        patientId: String(p.id),
        status: "pending",
        createdAt: p.created_at || '',
      }));
    } catch {
      return [];
    }
  }, []);

  const approvePatient = useCallback(async (id: string) => {
    await post('/auth/patients/approve.php', { patient_login_id: parseInt(id, 10) });
  }, []);

  const rejectPatient = useCallback(async (id: string) => {
    await post('/auth/patients/reject.php', { patient_login_id: parseInt(id, 10) });
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
      }}
    >
      {children}
    </EmailAuthContext.Provider>
  );
}

export function useEmailAuth(): EmailAuthContextValue {
  const ctx = useContext(EmailAuthContext);
  if (!ctx)
    throw new Error("useEmailAuth must be used inside EmailAuthProvider");
  return ctx;
}

// ── Inactivity Timer ──────────────────────────────────────────────────────────

const INACTIVITY_TIMEOUT_MS = 15 * 60 * 1000;
const INACTIVITY_WARNING_MS = 13 * 60 * 1000;
const ACTIVITY_EVENTS = [
  "mousemove",
  "keydown",
  "click",
  "touchstart",
  "scroll",
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
