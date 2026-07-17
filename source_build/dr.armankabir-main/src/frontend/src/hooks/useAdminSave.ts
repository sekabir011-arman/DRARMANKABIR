/**
 * Admin Save Operations — PHP/MySQL Backend
 *
 * All admin operations go through the PHP API via authService and auditService.
 * No localStorage used for any data storage.
 */

import type { StaffRole } from "../types";
import { authService } from "../services/auth";
import { auditService } from "../services/audit";
import type { AuditLogEntry } from "../services/audit";
import { post } from "../lib/apiClient";

// ── Audit helpers ─────────────────────────────────────────────────────────────

/**
 * Append an entry to the audit log via the PHP API.
 */
async function appendAuditLog(entry: {
  timestamp?: string;
  userRole: string;
  userName: string;
  action: string;
  target: string;
}): Promise<void> {
  try {
    await post("/audit/create.php", {
      action: entry.action,
      target: entry.target,
      details: JSON.stringify({
        userRole: entry.userRole,
        userName: entry.userName,
      }),
    });
  } catch {
    // Audit logging is non-critical
    console.warn("[Audit] Failed to log entry");
  }
}

// ── Constants ─────────────────────────────────────────────────────────────────

export const STAFF_ROLE_LABELS: Record<StaffRole, string> = {
  admin: "Admin",
  consultant_doctor: "Consultant Doctor",
  assistant_professor: "Assistant Professor",
  associate_professor: "Associate Professor",
  professor: "Professor",
  medical_officer: "Medical Officer",
  assistant_registrar: "Assistant Registrar",
  registrar: "Registrar",
  intern_doctor: "Intern Doctor",
  nurse: "Nurse",
  reception: "Reception",
  staff: "Staff",
  doctor: "Doctor",
  patient: "Patient",
};

// ── Registry load/save (delegated to PHP API) ────────────────────────────────

/** Load doctor registry from PHP API */
export async function loadRegistry(): Promise<any[]> {
  try {
    const result = await post<{ users: any[] }>("/auth/list.php", {
      role: "doctor",
    });
    return result.users ?? [];
  } catch {
    return [];
  }
}

/** Load patient registry from PHP API */
export async function loadPatientRegistry(): Promise<any[]> {
  try {
    const result = await post<{ patients: any[] }>(
      "/auth/patients/list.php",
    );
    return result.patients ?? [];
  } catch {
    return [];
  }
}

/** Save doctor registry (no-op — data is persisted via PHP API) */
export function saveRegistry(_registry: any[]): boolean {
  // Registry is managed server-side via authService operations
  return true;
}

/** Save patient registry (no-op — data is persisted via PHP API) */
export function savePatientRegistry(_registry: any[]): boolean {
  // Patient registry is managed server-side via authService operations
  return true;
}

// ── Enhanced save wrappers (compatibility shims) ─────────────────────────────

/**
 * Save doctor registry with error handling (delegated to PHP API).
 */
export const enhancedSaveRegistry = async (
  registry: any[],
): Promise<boolean> => {
  try {
    // Registry updates go through authService operations
    saveRegistry(registry);
    return true;
  } catch {
    return false;
  }
};

/**
 * Save patient registry with error handling (delegated to PHP API).
 */
export const enhancedSavePatientRegistry = async (
  registry: any[],
): Promise<boolean> => {
  try {
    savePatientRegistry(registry);
    return true;
  } catch {
    return false;
  }
};

// ── Admin actions ─────────────────────────────────────────────────────────────

/**
 * Admin action: Approve staff account.
 */
export const approveStaffAccount = async (
  accountId: number,
  selectedRole: StaffRole,
): Promise<boolean> => {
  try {
    await authService.approveAccount(accountId, selectedRole);
    await appendAuditLog({
      userRole: "admin",
      userName: "Admin",
      action: `Approved account as ${STAFF_ROLE_LABELS[selectedRole] ?? selectedRole}`,
      target: `Account #${accountId}`,
    });
    return true;
  } catch (err) {
    console.error("[Admin] Failed to approve staff:", err);
    return false;
  }
};

/**
 * Admin action: Reject staff account.
 */
export const rejectStaffAccount = async (
  accountId: number,
): Promise<boolean> => {
  try {
    await authService.rejectAccount(accountId);
    await appendAuditLog({
      userRole: "admin",
      userName: "Admin",
      action: "Rejected account",
      target: `Account #${accountId}`,
    });
    return true;
  } catch (err) {
    console.error("[Admin] Failed to reject staff:", err);
    return false;
  }
};

/**
 * Admin action: Approve patient account.
 */
export const approvePatientAccount = async (
  patientId: number,
): Promise<boolean> => {
  try {
    await authService.approvePatient(patientId);
    await appendAuditLog({
      userRole: "admin",
      userName: "Admin",
      action: "Approved patient account",
      target: `Patient #${patientId}`,
    });
    return true;
  } catch (err) {
    console.error("[Admin] Failed to approve patient:", err);
    return false;
  }
};

/**
 * Admin action: Reject patient account.
 */
export const rejectPatientAccount = async (
  patientId: number,
): Promise<boolean> => {
  try {
    await authService.rejectPatient(patientId);
    await appendAuditLog({
      userRole: "admin",
      userName: "Admin",
      action: "Rejected patient account",
      target: `Patient #${patientId}`,
    });
    return true;
  } catch (err) {
    console.error("[Admin] Failed to reject patient:", err);
    return false;
  }
};

/**
 * Admin action: Reassign staff role.
 */
export const reassignStaffRole = async (
  accountId: number,
  newRole: StaffRole,
): Promise<boolean> => {
  try {
    await authService.reassignRole(accountId, newRole);
    await appendAuditLog({
      userRole: "admin",
      userName: "Admin",
      action: `Role changed to ${STAFF_ROLE_LABELS[newRole]}`,
      target: `Account #${accountId}`,
    });
    return true;
  } catch (err) {
    console.error("[Admin] Failed to reassign role:", err);
    return false;
  }
};

/**
 * Verify PHP API connectivity.
 */
export const verifyStorageCapacity = async (): Promise<{
  available: boolean;
  percentUsed: number;
  message: string;
}> => {
  try {
    // Check if the server is reachable
    await post("/auth/verify.php");
    return {
      available: true,
      percentUsed: ,
      message: "Server storage available",
    };
  } catch {
    return {
      available: false,
      percentUsed: ,
      message: "Server unreachable - check your connection",
    };
  }
};
