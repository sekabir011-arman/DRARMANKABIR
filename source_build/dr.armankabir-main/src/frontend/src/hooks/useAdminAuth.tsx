/**
 * Admin Auth Hook — PHP/MySQL Backend
 *
 * Removed hardcoded ADMIN_ACCOUNTS.
 * Admin login is handled via the PHP API /api/auth/login.php.
 * The 'adminSession' localStorage key is kept for backward compat
 * but the actual auth is token-based via the PHP backend.
 */

import { useCallback, useState } from "react";
import { post, setAuthToken, clearAuthToken } from "../lib/api";

const STORAGE_KEY = "adminSession";

function loadSession(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === "true";
  } catch {
    return false;
  }
}

export function useAdminAuth() {
  const [isAdmin, setIsAdmin] = useState<boolean>(loadSession);

  const adminLogin = useCallback(
    async (username: string, password: string): Promise<boolean> => {
      try {
        const result = await post<{ token: string; user: { role: string } }>("/auth/login.php", {
          email: username,
          password,
        });

        if (result?.token && result?.user?.role === "admin") {
          setAuthToken(result.token);
          localStorage.setItem(STORAGE_KEY, "true");
          localStorage.setItem("app_current_user_email", username);
          setIsAdmin(true);
          return true;
        }
        return false;
      } catch {
        return false;
      }
    },
    [],
  );

  const adminLogout = useCallback(() => {
    clearAuthToken();
    localStorage.removeItem(STORAGE_KEY);
    setIsAdmin(false);
  }, []);

  return { isAdmin, adminLogin, adminLogout };
}
