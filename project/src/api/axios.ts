import axios from 'axios';
import toast from 'react-hot-toast';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

const axiosInstance = axios.create({
  baseURL: `${API_URL}/api`,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor - Add token to requests
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Handle errors globally
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      const { status, data } = error.response;

      // Handle 401 Unauthorized - Token expired or invalid
      if (status === 401) {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.dispatchEvent(new Event('app:logout'));
        toast.error('Sesión expirada. Por favor, inicia sesión nuevamente.');
        return Promise.reject(error);
      }

      // Handle 403 Forbidden
      if (status === 403) {
        toast.error(data.error || 'No tienes permisos para realizar esta acción');
        return Promise.reject(error);
      }

      // Handle 404 Not Found
      if (status === 404) {
        toast.error(data.error || 'Recurso no encontrado');
        return Promise.reject(error);
      }

      // Handle 422 Validation Error
      if (status === 422) {
        const errors = data.errors;
        if (errors) {
          const firstError = Object.values(errors)[0];
          toast.error(Array.isArray(firstError) ? firstError[0] : 'Error de validación');
        } else {
          toast.error(data.message || 'Error de validación');
        }
        return Promise.reject(error);
      }

      // Handle 500 Server Error
      if (status >= 500) {
        toast.error('Error del servidor. Por favor, intenta más tarde.');
        return Promise.reject(error);
      }

      // Generic error message
      toast.error(data.error || data.message || 'Ocurrió un error inesperado');
    } else if (error.request) {
      // Network error
      toast.error('Error de conexión. Verifica tu conexión a internet.');
    } else {
      toast.error('Error inesperado. Por favor, intenta nuevamente.');
    }

    return Promise.reject(error);
  }
);

export default axiosInstance;
