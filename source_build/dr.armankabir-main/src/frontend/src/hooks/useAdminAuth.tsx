/**
 * Admin Auth Hook — PHP/MySQL Backend
 *
 * Admin authentication now goes through the PHP API.
 * Hardcoded accounts removed.
 */

import { useCallback, useState } from "react";
import { post } from "../lib/api";

function loadSession(): boolean {
  return localStorage.getItem("adminSession") === "true";
}

export function useAdminAuth() {
  const [isAdmin, setIsAdmin] = useState<boolean>(loadSession);

  const adminLogin = useCallback(
    async (username: string, password: string): Promise<boolean> => {
      try {
        const result = await post<{ token: string }>("/auth/login.php", {
          email: username,
          password,
        });
        if (result?.token) {
          localStorage.setItem("phpAuthToken", result.token);
          localStorage.setItem("adminSession", "true");
          setIsAdmin(true);
          return true;
        }
      } catch {
        // Login failed
      }
      return false;
    },
    [],
  );

  const adminLogout = useCallback(() => {
    localStorage.removeItem("adminSession");
    localStorage.removeItem("phpAuthToken");
    setIsAdmin(false);
  }, []);

  return { isAdmin, adminLogin, adminLogout };
}
