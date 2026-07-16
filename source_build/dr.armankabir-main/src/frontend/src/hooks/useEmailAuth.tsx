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
import { post, get, setAuthToken, clearAuthToken } from "../lib/api";

// ─── Types ──────────────────────────────────────────────────────────────────

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

// ─── Auth helpers (minimal localStorage for session persistence) ────────────

const CANONICAL_EMAIL_KEY = "app_current_user_email";

export function setCanonicalUserEmail(email: string): void {
  try {
    localStorage.setItem(CANONICAL_EMAIL_KEY, email);
  } catch {
    // ignore
  }
}

export function clearCanonicalUserEmail(): void {
  try {
    localStorage.removeItem(CANONICAL_EMAIL_KEY);
  } catch {
    // ignore
  }
}

// ─── Local storage helpers (kept for backward compatibility) ────────────────

export function loadRegistry(): DoctorAccount[] {
  return [];
}

export function saveRegistry(_registry: DoctorAccount[]): void {
  // No-op — registry is server-side
}

export function loadPatientRegistry(): PatientAccount[] {
  return [];
}

export function savePatientRegistry(_registry: PatientAccount[]): void {
  // No-op — registry is server-side
}

export function appendAuditLog(_entry: Omit<AuditLogEntry, "id">): void {
  // Audit logs are handled server-side
}

export function getAuditLog(): AuditLogEntry[] {
  return [];
}

// ─── Normalize register number ─────────────────────────────────────────────

function normalizeRegNo(rn: string): string {
  const parts = rn.trim().split("/");
  if (parts.length === 2) {
    const num = Number.parseInt(parts[0].trim(), 10);
    return `${Number.isNaN(num) ? parts[0].trim() : num}/${parts[1].trim()}`;
  }
  return rn.trim().toLowerCase();
}

// ─── Context Types ──────────────────────────────────────────────────────────

interface EmailAuthContextValue {
  currentDoctor: DoctorAccount | null;
  currentPatient: PatientAccount | null;
  isInitializing: boolean;
  isLoggingIn: boolean;
  authError: string | null;
  signUp: (
    data: Omit<DoctorAccount, "id" | "passwordHash" | "createdAt" | "status"> & { password: string },
  ) => Promise<void>;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => void;
  updateProfile: (data: Partial<Omit<DoctorAccount, "id" | "passwordHash" | "createdAt">>) => void;
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

  // Restore session on mount
  useEffect(() => {
    const restoreSession = async () => {
      try {
        const data = await get<{ user: Record<string, any> }>("/auth/verify.php");
        if (data?.user) {
          const u = data.user;
          const doctor: DoctorAccount = {
            id: String(u.id),
            email: u.email || "",
            name: u.full_name || "",
            designation: u.specialization || "",
            degree: "",
            specialization: u.specialization || "",
            hospital: "",
            phone: u.phone || "",
            createdAt: u.last_login_at || "",
            role: (u.role as StaffRole) || "doctor",
            status: "approved",
          };
          setCurrentDoctor(doctor);
          setCanonicalUserEmail(u.email || "");
        }
      } catch {
        // Not authenticated or session expired
      }

      // Try to restore patient session
      try {
        const stored = localStorage.getItem("patient_session_data");
        if (stored) {
          const parsed = JSON.parse(stored);
          if (parsed.token) {
            localStorage.setItem("patientAuthToken", parsed.token);
            setCurrentPatient(parsed.patient || null);
          }
        }
      } catch {
        // ignore
      }

      setIsInitializing(false);
    };
    restoreSession();
  }, []);

  // ─── Doctor/Staff Sign Up ──────────────────────────────────────────────────

  const signUp = useCallback(
    async (data: Omit<DoctorAccount, "id" | "passwordHash" | "createdAt" | "status"> & { password: string }) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        await post("/auth/register.php", {
          email: data.email,
          password: data.password,
          full_name: data.name,
          name_bn: "",
          role: data.role || "doctor",
          specialization: data.specialization || "",
          phone: data.phone || "",
          bmdc_registration: "",
        });
        // The registration endpoint throws on failure or returns success
        // If we get here, the account was created but needs approval
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

  // ─── Doctor/Staff Sign In ──────────────────────────────────────────────────

  const signIn = useCallback(async (email: string, password: string) => {
    setIsLoggingIn(true);
    setAuthError(null);
    try {
      const data = await post<{ token: string; user: Record<string, any> }>("/auth/login.php", {
        email,
        password,
      });

      // Store token
      setAuthToken(data.token);
      setCanonicalUserEmail(data.user.email || email);

      const doctor: DoctorAccount = {
        id: String(data.user.id),
        email: data.user.email || email,
        name: data.user.full_name || "",
        designation: data.user.specialization || "",
        degree: "",
        specialization: data.user.specialization || "",
        hospital: "",
        phone: data.user.phone || "",
        createdAt: "",
        role: (data.user.role as StaffRole) || "doctor",
        status: "approved",
      };

      setCurrentDoctor(doctor);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Sign in failed.";
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  // ─── Doctor/Staff Sign Out ─────────────────────────────────────────────────

  const signOut = useCallback(() => {
    // Attempt server-side logout
    post("/auth/logout.php", {}).catch(() => {});
    clearAuthToken();
    clearCanonicalUserEmail();
    setCurrentDoctor(null);
    setAuthError(null);
  }, []);

  // ─── Update Profile ────────────────────────────────────────────────────────

  const updateProfile = useCallback(
    (data: Partial<Omit<DoctorAccount, "id" | "passwordHash" | "createdAt">>) => {
      // Profile updates go through PHP API
      if (!currentDoctor) return;
      post("/auth/update_profile.php", data).catch(() => {});
      setCurrentDoctor((prev) => (prev ? { ...prev, ...data } : prev));
    },
    [currentDoctor],
  );

  // ─── Account Management ────────────────────────────────────────────────────

  const getPendingAccounts = useCallback((): DoctorAccount[] => {
    // Handled via PHP API — this is a sync function kept for interface compat
    return [];
  }, []);

  const approveAccount = useCallback((id: string) => {
    post("/auth/approve.php", { user_id: parseInt(id, 10) }).catch(() => {});
  }, []);

  const approveAccountWithRole = useCallback((id: string, role: StaffRole) => {
    post("/auth/approve.php", { user_id: parseInt(id, 10), role }).catch(() => {});
  }, []);

  const rejectAccount = useCallback((id: string) => {
    post("/auth/reject.php", { user_id: parseInt(id, 10) }).catch(() => {});
  }, []);

  const reassignRole = useCallback((id: string, role: StaffRole) => {
    post("/auth/reassign_role.php", { user_id: parseInt(id, 10), role }).catch(() => {});
  }, []);

  // ── Patient auth ──────────────────────────────────────────────────────────

  const patientSignUp = useCallback(
    async (data: { registerNumber: string; phone: string; password: string }) => {
      setIsLoggingIn(true);
      setAuthError(null);
      try {
        await post("/auth/patients/register.php", {
          register_number: data.registerNumber,
          phone: data.phone,
          password: data.password,
        });
        throw new Error("Account created! Please wait for doctor approval before logging in.");
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
      const data = await post<{ token: string; patient: Record<string, any> }>("/auth/patients/login.php", {
        phone,
        password,
      });

      // Store token and patient data
      localStorage.setItem("patientAuthToken", data.token);
      localStorage.setItem("patient_session_data", JSON.stringify(data));

      const p = data.patient;
      const patient: PatientAccount = {
        id: String(p.id),
        phone: p.phone || phone,
        name: p.full_name || "",
        age: p.age || "",
        gender: p.gender || "",
        registerNumber: p.register_number || "",
        patientId: String(p.patient_id || ""),
        status: (p.status as "pending" | "approved" | "rejected") || "approved",
        createdAt: "",
      };
      setCurrentPatient(patient);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Sign in failed.";
      setAuthError(msg);
      throw e;
    } finally {
      setIsLoggingIn(false);
    }
  }, []);

  const patientSignOut = useCallback(() => {
    localStorage.removeItem("patientAuthToken");
    localStorage.removeItem("patient_session_data");
    setCurrentPatient(null);
    setAuthError(null);
  }, []);

  const getPendingPatients = useCallback((): PatientAccount[] => {
    return [];
  }, []);

  const approvePatient = useCallback((id: string) => {
    post("/auth/patients/approve.php", { patient_login_id: parseInt(id, 10) }).catch(() => {});
  }, []);

  const rejectPatient = useCallback((id: string) => {
    post("/auth/patients/reject.php", { patient_login_id: parseInt(id, 10) }).catch(() => {});
  }, []);

  const updatePatientCredentials = useCallback(
    (_registerNumber: string, _newPhone?: string, _newPassword?: string) => {
      // Handled via PHP API
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
  if (!ctx) throw new Error("useEmailAuth must be used inside EmailAuthProvider");
  return ctx;
}

// ── Inactivity Timer ──────────────────────────────────────────────────────────

const INACTIVITY_TIMEOUT_MS = 15 * 60 * 1000;
const INACTIVITY_WARNING_MS = 13 * 60 * 1000;
const ACTIVITY_EVENTS = ["mousemove", "keydown", "click", "touchstart", "scroll"] as const;

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
