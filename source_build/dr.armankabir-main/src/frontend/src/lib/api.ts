/**
 * PHP API Client (Legacy Compatibility Layer)
 *
 * @deprecated Use apiClient.ts for all new code.
 * This file is kept for backward compatibility only.
 * Authentication is now managed via PHP sessions (cookies auto-sent by the browser).
 * No tokens stored in localStorage.
 *
 * All services now import from apiClient.ts directly.
 * This module is only used by hybridStorage.ts (no-op stubs).
 */

const API_BASE = '/api';

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
  timestamp?: string;
}

export interface PaginatedData<T> {
  items: T[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    total_pages: number;
    has_more: boolean;
  };
}

class ApiError extends Error {
  statusCode: number;
  errors?: Record<string, string[]>;

  constructor(message: string, statusCode: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiError';
    this.statusCode = statusCode;
    this.errors = errors;
  }
}

// In-memory auth token (not persisted to localStorage)
let _authToken: string | null = null;

function getAuthToken(): string | null {
  return _authToken;
}

export function setAuthToken(token: string): void {
  _authToken = token;
}

export function clearAuthToken(): void {
  _authToken = null;
}

function buildUrl(path: string, params?: Record<string, string | number | undefined | null>): string {
  const url = `${API_BASE}${path}`;
  if (!params) return url;

  const searchParams = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      searchParams.set(key, String(value));
    }
  }
  const qs = searchParams.toString();
  return qs ? `${url}?${qs}` : url;
}

async function request<T = any>(
  method: string,
  path: string,
  body?: any,
  params?: Record<string, string | number | undefined | null>,
  customHeaders?: Record<string, string>,
): Promise<T> {
  const url = buildUrl(path, params);
  const headers: Record<string, string> = {
    ...customHeaders,
  };

  const token = getAuthToken();
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const options: RequestInit = {
    method,
    headers,
    credentials: 'same-origin', // PHP session cookie
  };

  if (body !== undefined) {
    if (body instanceof FormData) {
      // Let browser set Content-Type with boundary for FormData
      options.body = body;
    } else {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
  }

  let response: Response;
  try {
    response = await fetch(url, options);
  } catch (err) {
    throw new ApiError('Network error. Please check your connection.', );
  }

  let json: ApiResponse<T>;
  try {
    json = await response.json();
  } catch {
    throw new ApiError(`Unexpected response (HTTP ${response.status})`, response.status);
  }

  if (!response.ok || !json.success) {
    throw new ApiError(
      json.message || 'Request failed',
      response.status,
      json.errors,
    );
  }

  return json.data as T;
}

/**
 * HTTP method helpers
 */

export function get<T = any>(
  path: string,
  params?: Record<string, string | number | undefined | null>,
): Promise<T> {
  return request<T>('GET', path, undefined, params);
}

export function post<T = any>(
  path: string,
  body?: any,
  params?: Record<string, string | number | undefined | null>,
): Promise<T> {
  return request<T>('POST', path, body, params);
}

export function put<T = any>(
  path: string,
  body?: any,
): Promise<T> {
  return request<T>('PUT', path, body);
}

export function del<T = any>(
  path: string,
  body?: any,
): Promise<T> {
  return request<T>('DELETE', path, body);
}

export function upload<T = any>(
  path: string,
  formData: FormData,
): Promise<T> {
  return request<T>('POST', path, formData, undefined, {}); // no Content-Type, let browser set it
}

export { ApiError };
