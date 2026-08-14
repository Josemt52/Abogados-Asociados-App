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
    async create(data: CreateContactoData): Promise<Contacto> {
        const response = await axios.post('/contacto', data);
        return response.data.contact;
    },

    async getAll(): Promise<Contacto[]> {
        const response = await axios.get('/contacto');
        return response.data;
    },

    async getById(id: number): Promise<Contacto> {
        const response = await axios.get(`/contacto/${id}`);
        return response.data;
    },

    async delete(id: number): Promise<void> {
        await axios.delete(`/contacto/${id}`);
    },
};
