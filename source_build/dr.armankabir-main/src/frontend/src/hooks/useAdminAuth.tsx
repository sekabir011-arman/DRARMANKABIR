/**
 * Admin Auth Hook — PHP/MySQL Backend
 *
 * Admin authentication now goes through the PHP API / authService.
 * No localStorage used — PHP session cookies handle authentication.
 */

import { useCallback, useState } from "react";
import { authService } from "../services/auth";

export function useAdminAuth() {
  const [isAdmin, setIsAdmin] = useState<boolean>(false);

  const adminLogin = useCallback(
    async (username: string, password: string): Promise<boolean> => {
      try {
        const result = await authService.signIn(username, password);
        if (result.user) {
          setIsAdmin(result.user.role === "admin");
          return true;
        }
      } catch {
        // Login failed
      }
      return false;
    },
    [],
  );

  const adminLogout = useCallback(async () => {
    await authService.signOut();
    setIsAdmin(false);
  }, []);

  return { isAdmin, adminLogin, adminLogout };
}
