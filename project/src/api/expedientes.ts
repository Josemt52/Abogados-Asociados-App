import axios from './axios';

export interface Expediente {
  id: number;
  numero: string;
  materia: string;
  juzgado: string;
  especialista: string;
  tercero: string;
  demandado: string;
  demandante: string;
  estado: string;
  archivo: boolean;
  nombre_archivo: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateExpedienteData {
  numero: string;
  materia?: string;
  juzgado?: string;
  especialista?: string;
  tercero?: string;
  demandado?: string;
  demandante?: string;
  estado?: string;
}

export interface UpdateExpedienteData extends Partial<CreateExpedienteData> {}

export const expedientesAPI = {
  getAll: async (): Promise<Expediente[]> => {
    const response = await axios.get('/expedientes');
    return response.data;
  },

  getById: async (id: number): Promise<Expediente> => {
    const response = await axios.get(`/expedientes/${id}`);
    return response.data;
  },

  create: async (data: CreateExpedienteData): Promise<Expediente> => {
    const response = await axios.post('/expedientes', data);
    return response.data;
  },

  update: async (id: number, data: UpdateExpedienteData): Promise<Expediente> => {
    const response = await axios.put(`/expedientes/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await axios.delete(`/expedientes/${id}`);
  },

  uploadFile: async (id: number, file: File): Promise<Expediente> => {
    const formData = new FormData();
    formData.append('file', file);

    const response = await axios.post(`/expedientes/${id}/archivo`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  downloadFile: async (id: number): Promise<Blob> => {
    const response = await axios.get(`/expedientes/${id}/archivo/download`, {
      responseType: 'blob',
    });
    return response.data;
  },

  generateWord: async (id: number): Promise<Blob> => {
    const response = await axios.get(`/expedientes/${id}/word`, {
      responseType: 'blob',
    });
    return response.data;
  },

  generatePdf: async (id: number): Promise<Blob> => {
    const response = await axios.get(`/expedientes/${id}/pdf`, {
      responseType: 'blob',
    });
    return response.data;
  },
};
