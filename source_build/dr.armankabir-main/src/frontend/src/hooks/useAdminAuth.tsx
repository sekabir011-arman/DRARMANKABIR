/**
 * Admin Auth Hook — PHP/MySQL Backend
 *
 * Admin authentication via PHP API.
 * No hardcoded accounts, no localization of credentials.
 * localStorage used only for session token persistence (via phpAuthToken).
 */

import { createContext, useCallback, useContext, useEffect, useState } from "react";
import type React from "react";
import { get, setAuthToken, clearAuthToken } from "../lib/api";
import type { StaffRole } from "../types";

export interface AdminUser {
  id: number;
  email: string;
  full_name: string;
  role: StaffRole;
  is_super_admin?: boolean;
  avatar_url?: string;
}

interface AdminAuthContextValue {
  admin: AdminUser | null;
  isAdmin: boolean;
  isInitializing: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  error: string | null;
}

const AdminAuthContext = createContext<AdminAuthContextValue | null>(null);

export function AdminAuthProvider({ children }: { children: React.ReactNode }) {
  const [admin, setAdmin] = useState<AdminUser | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Restore session on mount
  useEffect(() => {
    const restoreSession = async () => {
      try {
        const token = localStorage.getItem("phpAuthToken");
        if (token) {
          const result = await get<{ user: AdminUser }>("/auth/verify.php");
          if (result?.user) {
            setAdmin(result.user);
          } else {
            localStorage.removeItem("phpAuthToken");
          }
        }
      } catch {
        localStorage.removeItem("phpAuthToken");
      } finally {
        setIsInitializing(false);
      }
    };
    restoreSession();
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    setError(null);
    try {
      const result = await fetch("/api/auth/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });

      if (!result.ok) {
        const err = await result.json().catch(() => ({ message: "Login failed" }));
        throw new Error(err.message || "Invalid credentials");
      }

      const data = await result.json();
      setAuthToken(data.token);
      setAdmin(data.user);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Login failed";
      setError(msg);
      throw e;
    }
  }, []);

  const logout = useCallback(() => {
    fetch("/api/auth/logout.php", { method: "POST" }).catch(() => {});
    clearAuthToken();
    setAdmin(null);
    setError(null);
  }, []);

  return (
    <AdminAuthContext.Provider
      value={{
        admin,
        isAdmin: admin !== null && (admin.role === "admin" || admin.is_super_admin === true),
        isInitializing,
        login,
        logout,
        error,
      }}
    >
      {children}
    </AdminAuthContext.Provider>
  );
}

export function useAdminAuth(): AdminAuthContextValue {
  const ctx = useContext(AdminAuthContext);
  if (!ctx) throw new Error("useAdminAuth must be used inside AdminAuthProvider");
  return ctx;
}
