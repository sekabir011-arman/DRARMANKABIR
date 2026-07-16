/**
 * Admin Authentication Hook — PHP Backend
 *
 * Replaces the old hardcoded ADMIN_ACCOUNTS array.
 * Now validates admin credentials against the PHP/MySQL backend.
 */

import { useCallback, useState } from 'react';
import { post, setAuthToken, clearAuthToken } from '../lib/api';

const STORAGE_KEY = 'adminSession';

function loadSession(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'true';
  } catch {
    return false;
  }
}

export function useAdminAuth() {
  const [isAdmin, setIsAdmin] = useState<boolean>(loadSession);

  const adminLogin = useCallback(
    async (username: string, password: string): Promise<boolean> => {
      try {
        const result = await post<{ token: string; user: Record<string, unknown> }>(
          '/auth/login.php',
          { email: username, password },
        );
        if (result?.token) {
          setAuthToken(result.token);
          localStorage.setItem(STORAGE_KEY, 'true');
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
    clearAuthToken();
    localStorage.removeItem(STORAGE_KEY);
    setIsAdmin(false);
  }, []);

  return { isAdmin, adminLogin, adminLogout };
}
