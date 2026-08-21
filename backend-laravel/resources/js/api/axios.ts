import axios from 'axios';
import { toast } from '@/composables/useToast';

declare module 'axios' {
    interface AxiosRequestConfig {
        silent?: boolean;
    }

    interface InternalAxiosRequestConfig {
        silent?: boolean;
    }
}

const configuredApiUrl = import.meta.env.VITE_API_URL?.trim().replace(/\/+$/, '');

const axiosInstance = axios.create({
    baseURL: configuredApiUrl ? `${configuredApiUrl}/api` : '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

axiosInstance.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.config?.silent === true) {
            return Promise.reject(error);
        }

        const status = error.response?.status;
        const data = error.response?.data;
        const isLoginRequest = error.config?.url?.includes('/auth/login');

        if (status === 401 && !isLoginRequest) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            window.dispatchEvent(new Event('app:logout'));
            toast.error('Sesión expirada. Inicia sesión nuevamente.');
        } else if (status === 403) {
            toast.error(data?.error || 'No tienes permisos para realizar esta acción.');
        } else if (status === 404) {
            toast.error(data?.error || 'Recurso no encontrado.');
        } else if (status === 422 && data?.errors) {
            const firstError = Object.values(data.errors)[0];
            toast.error(Array.isArray(firstError) ? String(firstError[0]) : 'Error de validación.');
        } else if (status >= 500) {
            toast.error('Error del servidor. Intenta nuevamente más tarde.');
        } else if (!error.response && error.request) {
            toast.error('No se pudo conectar con el servidor.');
        }

        return Promise.reject(error);
    },
);

export default axiosInstance;
