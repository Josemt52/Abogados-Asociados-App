import axios from './axios';

export interface ArchivoMetadata {
    id: number;
    expediente_id: number;
    nombre_archivo: string;
    tipo_archivo: string;
}

export interface Expediente {
    id: number;
    numero: string;
    materia: string | null;
    juzgado: string | null;
    especialista: string | null;
    tercero: string | null;
    demandado: string | null;
    demandante: string | null;
    estado: string | null;
    archivo: boolean;
    nombre_archivo: string | null;
    archivo_data?: ArchivoMetadata | null;
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

export type UpdateExpedienteData = Partial<CreateExpedienteData>;

export const expedientesAPI = {
    async getAll(): Promise<Expediente[]> {
        const response = await axios.get('/expedientes');
        return response.data;
    },

    async getById(id: number): Promise<Expediente> {
        const response = await axios.get(`/expedientes/${id}`);
        return response.data;
    },

    async create(data: CreateExpedienteData): Promise<Expediente> {
        const response = await axios.post('/expedientes', data);
        return response.data;
    },

    async update(id: number, data: UpdateExpedienteData): Promise<Expediente> {
        const response = await axios.put(`/expedientes/${id}`, data);
        return response.data;
    },

    async delete(id: number): Promise<void> {
        await axios.delete(`/expedientes/${id}`);
    },

    async uploadFile(
        id: number,
        file: File,
        onProgress?: (progress: number) => void,
    ): Promise<Expediente> {
        const formData = new FormData();
        formData.append('file', file);

        const response = await axios.post(`/expedientes/${id}/archivo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: onProgress
                ? (event) => {
                    if (event.total) {
                        onProgress(Math.round((event.loaded * 100) / event.total));
                    }
                }
                : undefined,
        });

        return response.data;
    },

    async downloadFile(id: number): Promise<Blob> {
        const response = await axios.get(`/expedientes/${id}/archivo/download`, {
            responseType: 'blob',
        });
        return response.data;
    },

    async generateWord(id: number): Promise<Blob> {
        const response = await axios.get(`/expedientes/${id}/word`, {
            responseType: 'blob',
        });
        return response.data;
    },

    async generatePdf(id: number): Promise<Blob> {
        const response = await axios.get(`/expedientes/${id}/pdf`, {
            responseType: 'blob',
        });
        return response.data;
    },
};
