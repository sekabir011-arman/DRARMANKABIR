/**
 * Email Auth Hook — PHP/MySQL Backend
 *
 * All authentication is handled server-side via the PHP API.
 * PHP sessions (cookies) manage authentication state.
 * No tokens are stored in localStorage.
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
import { authService } from "../services";
import type { DoctorAccount, PatientAccount } from "../services";

export { DoctorAccount, PatientAccount };

export interface AuditLogEntry {
  id: string;
  timestamp: string;
  userRole: StaffRole | "admin" | "patient";
  userName: string;
  action: string;
  target: string;
}

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

// ── Legacy registry helpers (kept for backward compatibility, now no-ops) ──

export function loadRegistry(): DoctorAccount[] {
  return [];
}

export function saveRegistry(_registry: DoctorAccount[]): void {
  // No-op — registry is server-side via authService
}

export function loadPatientRegistry(): PatientAccount[] {
  return [];
}

export function savePatientRegistry(_registry: PatientAccount[]): void {
  // No-op — registry is server-side via authService
}

export function appendAuditLog(_entry: Omit<AuditLogEntry, "id">): void {
  // Audit logging handled by PHP backend
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
  return true;
}

// Normalize register number: "0001/26" and "1/26" treated as equal
function normalizeRegNo(rn: string): string {
  const parts = rn.trim().split("/");
  if (parts.length === 2) {
    const num = Number.parseInt(parts[0].trim(), 10);
    return `${Number.isNaN(num) ? parts[0].trim() : num}/${parts[1].trim()}`;
  }
  return rn.trim().toLowerCase();
}

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
    role?: string;
    specialization?: string;
    phone?: string;
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
  patientSignOut: () => void;
  getPendingPatients: () => Promise<PatientAccount[]>;
  approvePatient: (id: number) => Promise<void>;
  rejectPatient: (id: number) => Promise<void>;
  updatePatientCredentials: (
    registerNumber: string,
    newPhone?: string,
    newPassword?: string,
  ) => Promise<void>;
}

const EmailAuthContext = createContext<EmailAuthContextValue | null>(null);

export function EmailAuthProvider({ children }: { children: React.ReactNode }) {
  const [currentDoctor, setCurrentDoctor] = useState<DoctorAccount | null>(null);
  const [currentPatient, setCurrentPatient] = useState<PatientAccount | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [authError, setAuthError] = useState<string | null>(null);

  // Restore session on mount by verifying the PHP session cookie
  useEffect(() => {
    const restoreSession = async () => {
      try {
        // PHP session cookie is auto-sent; verify it
        const user = await authService.verifySession();
        if (user) {
          setCurrentDoctor(user);
        }
      } catch {
        // Session invalid or expired
      } finally {
        setIsInitializing(false);
      }
    };
    restoreSession();
  }, []);

  const signUp = useCallback(
    async (data: {
      email: string;
      password: string;
      full_name: string;
      role?: string;
      specialization?: string;
      phone?: string;
    }) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        await authService.signUp(data);
        throw new Error("Account created! Please wait for admin approval before logging in.");
      } catch (e: unknown) {
        const msg = e instanceof Error ? e.message : "Sign up failed.";
        setAuthError(msg);
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
      const result = await authService.signIn(email, password);
      setCurrentDoctor(result.user);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Sign in failed.";
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const signOut = useCallback(async () => {
    await authService.signOut();
    setCurrentDoctor(null);
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const updateProfile = useCallback(
    async (_data: Partial<DoctorAccount>) => {
      // Profile update handled by PHP backend via authService
      // API endpoint: POST /api/auth/update_profile.php
    },
    [],
  );

  const getPendingAccounts = useCallback(async (): Promise<DoctorAccount[]> => {
    return authService.getPendingAccounts();
  }, []);

  const approveAccount = useCallback(async (id: number, role?: string) => {
    await authService.approveAccount(id, role);
  }, []);

  const approveAccountWithRole = useCallback(async (id: number, role: StaffRole) => {
    await approveAccount(id, role);
  }, [approveAccount]);

  const rejectAccount = useCallback(async (id: number) => {
    await authService.rejectAccount(id);
  }, []);

  const reassignRole = useCallback(async (id: number, role: StaffRole) => {
    await authService.reassignRole(id, role);
  }, []);

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
        const { registerNumber, phone, password } = data;

        if (!registerNumber?.trim()) {
          throw new Error(
            "Register number is required. Please contact the clinic to get your register number.",
          );
        }

        await authService.patientSignUp({
          registerNumber: registerNumber.trim(),
          phone,
          password,
        });

        throw new Error(
          "Account created! Please wait for doctor approval before logging in.",
        );
      } catch (e: unknown) {
        const msg = e instanceof Error ? e.message : "Sign up failed.";
        setAuthError(msg);
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
      const result = await authService.patientSignIn(phone, password);
      setCurrentPatient(result.patient);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Sign in failed.";
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const patientSignOut = useCallback(() => {
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const getPendingPatients = useCallback(async (): Promise<PatientAccount[]> => {
    return authService.getPendingPatients();
  }, []);

  const approvePatient = useCallback(async (id: number) => {
    await authService.approvePatient(id);
  }, []);

  const rejectPatient = useCallback(async (id: number) => {
    await authService.rejectPatient(id);
  }, []);

  const updatePatientCredentials = useCallback(
    async (_registerNumber: string, _newPhone?: string, _newPassword?: string) => {
      // Patient credential update handled by PHP backend
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
    throw new Error("useEmailAuth must be used inside EmailAuthProvider");
  return ctx;
}

// ── Inactivity Timer ──────────────────────────────────────────────────────────

const INACTIVITY_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
const INACTIVITY_WARNING_MS = 13 * 60 * 1000; // 13 minutes (2 min before logout)
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
