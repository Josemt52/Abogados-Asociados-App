import axios from 'axios';
import toast from 'react-hot-toast';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080';

// Create axios instance
export const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor for auth
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // On 401, clear storage and notify the app instead of forcing a hard redirect.
      // A global listener (AuthProvider / Layout) will handle navigation and cleanup.
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      try {
        window.dispatchEvent(new Event('app:logout'));
      } catch (e) {
        // fallback to hard redirect if dispatch fails
        window.location.href = '/login';
      }
    }
    
    const message = error.response?.data?.message || 'Error en la operación';
    toast.error(message);
    
    return Promise.reject(error);
  }
);

// API endpoints
export const authAPI = {
  login: async (credentials: { username: string; password: string }) => {
    const response = await api.post('/api/auth/login', credentials);
    // backend returns { user, token }
    return response.data;
  },
  
  register: async (userData: any) => {
    const response = await api.post('/api/usuarios', userData);
    return response.data;
  }
};

export const expedientesAPI = {
  getAll: async (page?: number, search?: string) => {
    const params = new URLSearchParams();
    if (page) params.append('page', page.toString());
    if (search) params.append('search', search);
    
    const response = await api.get(`/api/expedientes?${params}`);
    return response.data;
  },
  
  getById: async (id: string) => {
    const response = await api.get(`/api/expedientes/${id}`);
    return response.data;
  },
  
  create: async (expedienteData: any) => {
    const response = await api.post('/api/expedientes', expedienteData);
    return response.data;
  },
  
  update: async (id: string, expedienteData: any) => {
    const response = await api.put(`/api/expedientes/${id}`, expedienteData);
    return response.data;
  },
  
  delete: async (id: string) => {
    const response = await api.delete(`/api/expedientes/${id}`);
    return response.data;
  },
  
  uploadFile: async (id: string, file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    
    const response = await api.post(`/api/expedientes/${id}/archivo`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },
  
  downloadFile: async (id: string, archivoId: string) => {
    const response = await api.get(`/api/expedientes/${id}/archivo/${archivoId}/download`, {
      responseType: 'blob',
    });
    return response.data;
  },

  // Descargar archivo del expediente directamente (sin necesitar archivoId)
  downloadExpedienteFile: async (id: string) => {
    const response = await api.get(`/api/expedientes/${id}/archivo/download`, {
      responseType: 'blob',
    });
    return response.data;
  },
  
  generateWord: async (id: string, nombreArchivo?: string) => {
    const response = await api.post(`/api/expedientes/${id}/word`, { nombreArchivo }, {
      responseType: 'blob',
    });
    return response.data;
  },
  
  addResolution: async (id: string, data: { contenidoHtml: string; numeroResolucion: string }) => {
    const response = await api.post(`/api/expedientes/${id}/word/resolucion`, data);
    return response.data;
  },
  
  generatePDF: async (id: string, nombreArchivo?: string) => {
    const response = await api.post(`/api/expedientes/${id}/pdf`, { nombreArchivo }, {
      responseType: 'blob',
    });
    return response.data;
  }
};

export const estadisticasAPI = {
  getDashboardStats: async () => {
    const response = await api.get('/api/estadisticas/dashboard');
    return response.data;
  },
  
  getExpedientesPorEstado: async () => {
    const response = await api.get('/api/estadisticas/expedientes-por-estado');
    return response.data;
  },
  
  getActividadReciente: async () => {
    const response = await api.get('/api/estadisticas/actividad-reciente');
    return response.data;
  }
};

export const usuariosAPI = {
  getAll: async () => {
    const response = await api.get('/api/usuarios');
    return response.data;
  },
  
  getById: async (id: number) => {
    const response = await api.get(`/api/usuarios/${id}`);
    return response.data;
  },
  
  update: async (id: number, userData: any) => {
    const response = await api.put(`/api/usuarios/${id}`, userData);
    return response.data;
  },
  
  delete: async (id: number) => {
    const response = await api.delete(`/api/usuarios/${id}`);
    return response.data;
  }
};