import axios, { AxiosInstance } from 'axios';

const API_URL = import.meta.env.VITE_BACKEND_API_URL || 'http://localhost:3001';

const api: AxiosInstance = axios.create({
  baseURL: `${API_URL}/api`,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle response errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Auth endpoints
export const authApi = {
  register: (email: string, password: string, role?: string) =>
    api.post('/auth/register', { email, password, role }),
  login: (email: string, password: string) =>
    api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
};

// Patient endpoints
export const patientApi = {
  create: (patient: any) => api.post('/patients', patient),
  getAll: () => api.get('/patients'),
  getById: (id: string) => api.get(`/patients/${id}`),
  update: (id: string, patient: any) => api.put(`/patients/${id}`, patient),
  delete: (id: string) => api.delete(`/patients/${id}`),
  getSince: (timestamp: number) => api.get(`/patients/sync/since/${timestamp}`),
};

// Visit endpoints
export const visitApi = {
  create: (visit: any) => api.post('/visits', visit),
  getAll: () => api.get('/visits'),
  getById: (id: string) => api.get(`/visits/${id}`),
  getByPatientId: (patientId: string) => api.get(`/visits/patient/${patientId}`),
  update: (id: string, visit: any) => api.put(`/visits/${id}`, visit),
  delete: (id: string) => api.delete(`/visits/${id}`),
};

// Prescription endpoints
export const prescriptionApi = {
  create: (prescription: any) => api.post('/prescriptions', prescription),
  getAll: () => api.get('/prescriptions'),
  getById: (id: string) => api.get(`/prescriptions/${id}`),
  getByPatientId: (patientId: string) => api.get(`/prescriptions/patient/${patientId}`),
  getByVisitId: (visitId: string) => api.get(`/prescriptions/visit/${visitId}`),
  update: (id: string, prescription: any) => api.put(`/prescriptions/${id}`, prescription),
  delete: (id: string) => api.delete(`/prescriptions/${id}`),
};

// User Profile endpoints
export const userProfileApi = {
  getMe: () => api.get('/user-profiles/me'),
  getById: (userId: string) => api.get(`/user-profiles/${userId}`),
  save: (name: string) => api.post('/user-profiles/me/save', { name }),
};

export default api;
