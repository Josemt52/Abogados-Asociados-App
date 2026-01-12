import axios from './axios';

export interface Contacto {
  id: number;
  nombre: string;
  email: string;
  telefono?: string;
  mensaje: string;
  created_at: string;
  updated_at: string;
}

export interface CreateContactoData {
  nombre: string;
  email: string;
  telefono?: string;
  mensaje: string;
}

export const contactoAPI = {
  // Public endpoint
  create: async (data: CreateContactoData): Promise<Contacto> => {
    const response = await axios.post('/contacto', data);
    return response.data.contact;
  },

  // Admin only endpoints
  getAll: async (): Promise<Contacto[]> => {
    const response = await axios.get('/contacto');
    return response.data;
  },

  getById: async (id: number): Promise<Contacto> => {
    const response = await axios.get(`/contacto/${id}`);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await axios.delete(`/contacto/${id}`);
  },
};
