import { storage } from "../lib/storageAdapter";
/**
 * PHP API Client
 *
 * Centralized fetch wrapper for communicating with the PHP/MySQL backend.
 * Handles auth token injection, JSON parsing, and error normalization.
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

function getAuthToken(): string | null {
  try {
    return storage.getItem('phpAuthToken');
  } catch {
    return null;
  }
}

export function setAuthToken(token: string): void {
  try {
    storage.setItem('phpAuthToken', token);
  } catch {
    // ignore
  }
}

export function clearAuthToken(): void {
  try {
    storage.removeItem('phpAuthToken');
  } catch {
    // ignore
  }
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
    throw new ApiError('Network error. Please check your connection.', 0);
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
