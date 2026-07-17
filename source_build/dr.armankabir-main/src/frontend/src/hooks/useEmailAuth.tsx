/**
 * Email-based authentication hook
 *
 * Uses authService which communicates with the PHP/MySQL backend.
 * Tokens are stored in httpOnly cookies by the backend, not in localStorage.
 * This hook manages the React-side auth state and user profile.
 */

import { useCallback, useEffect, useState } from 'react';
import { authService } from '../services/authService';
import type { UserProfile } from '../types';

export interface AuthState {
  isAuthenticated: boolean;
  user: UserProfile | null;
  isLoading: boolean;
  error: string | null;
}

export function useEmailAuth() {
  const [state, setState] = useState<AuthState>({
    isAuthenticated: false,
    user: null,
    isLoading: true,
    error: null,
  });

  // Check session on mount
  useEffect(() => {
    let cancelled = false;

    async function checkSession() {
      try {
        setState(prev => ({ ...prev, isLoading: true, error: null }));
        const session = await authService.getSession();
        if (cancelled) return;

        if (session && session.user) {
          setState({
            isAuthenticated: true,
            user: session.user,
            isLoading: false,
            error: null,
          });
        } else {
          setState({
            isAuthenticated: false,
            user: null,
            isLoading: false,
            error: null,
          });
        }
      } catch (err) {
        if (cancelled) return;
        // Not authenticated — silent fail
        setState({
          isAuthenticated: false,
          user: null,
          isLoading: false,
          error: null,
        });
      }
    }

    checkSession();
    return () => { cancelled = true; };
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));
    try {
      const result = await authService.login(email, password);
      if (result && result.user) {
        setState({
          isAuthenticated: true,
          user: result.user,
          isLoading: false,
          error: null,
        });
        return true;
      }
      throw new Error('Invalid credentials');
    } catch (err: any) {
      const message = err?.message || 'Login failed';
      setState(prev => ({ ...prev, isLoading: false, error: message }));
      return false;
    }
  }, []);

  const register = useCallback(async (email: string, password: string, name?: string) => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));
    try {
      const result = await authService.register(email, password, name);
      if (result && result.user) {
        setState({
          isAuthenticated: true,
          user: result.user,
          isLoading: false,
          error: null,
        });
        return true;
      }
      throw new Error('Registration failed');
    } catch (err: any) {
      const message = err?.message || 'Registration failed';
      setState(prev => ({ ...prev, isLoading: false, error: message }));
      return false;
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await authService.logout();
    } catch {
      // Even if server call fails, clear local state
    }
    setState({
      isAuthenticated: false,
      user: null,
      isLoading: false,
      error: null,
    });
  }, []);

  const clearError = useCallback(() => {
    setState(prev => ({ ...prev, error: null }));
  }, []);

  return {
    ...state,
    login,
    register,
    logout,
    clearError,
  };
}
