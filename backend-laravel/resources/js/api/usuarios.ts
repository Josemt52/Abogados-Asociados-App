import axios from './axios';

export interface Usuario {
    id: number;
    nombre: string;
    username: string;
    rol_id: number;
    rol?: {
        id: number;
        nombre: string;
    };
    created_at: string;
    updated_at: string;
}

export interface CreateUsuarioData {
    nombre: string;
    username: string;
    password: string;
    rol_id: number;
}

export interface UpdateUsuarioData {
    nombre?: string;
    username?: string;
    password?: string;
    rol_id?: number;
}

export const usuariosAPI = {
    async getAll(): Promise<Usuario[]> {
        const response = await axios.get('/usuarios');
        return response.data;
    },

    async getById(id: number): Promise<Usuario> {
        const response = await axios.get(`/usuarios/${id}`);
        return response.data;
    },

    async create(data: CreateUsuarioData): Promise<Usuario> {
        const response = await axios.post('/usuarios', data);
        return response.data.usuario;
    },

    async update(id: number, data: UpdateUsuarioData): Promise<Usuario> {
        const response = await axios.put(`/usuarios/${id}`, data);
        return response.data.usuario;
    },

    async delete(id: number): Promise<void> {
        await axios.delete(`/usuarios/${id}`);
    },
};
