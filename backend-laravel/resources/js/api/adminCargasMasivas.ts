import axios from './axios';

export type AdminImportStatus = 'pendiente' | 'revision' | 'error' | 'registrado';

export interface ExtractedCaseData {
    numero: string | null;
    materia: string | null;
    juzgado: string | null;
    especialista: string | null;
    tercero: string[];
    demandado: string[];
    demandante: string[];
    confianza_campos?: Record<string, number>;
    limite_pagina?: string;
}

export interface AdminImportItem {
    id: number;
    nombre: string;
    nombre_descarga: string;
    extension: string;
    tamano: number;
    estado: AdminImportStatus;
    motivo: string | null;
    confianza: number | null;
    metodo_extraccion: string | null;
    datos: ExtractedCaseData | null;
    error: string | null;
    es_duplicado: boolean;
    expediente: { id: number; numero: string } | null;
    lote: { id: string; usuario: string | null } | null;
    procesado_at: string | null;
    created_at: string | null;
}

export interface AdminImportList {
    resumen: {
        pendientes: number;
        revision: number;
        errores: number;
    };
    items: {
        data: AdminImportItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

export interface ImportConfiguration {
    registro_automatico: boolean;
    confianza_minima: number;
}

export interface ApproveImportData {
    numero: string;
    materia?: string | null;
    juzgado?: string | null;
    especialista?: string | null;
    tercero?: string[];
    demandado?: string[];
    demandante?: string[];
}

export interface ApproveImportResponse {
    message: string;
    item: AdminImportItem;
}

export const adminCargasMasivasAPI = {
    async list(params?: {
        estado?: AdminImportStatus;
        buscar?: string;
        page?: number;
    }): Promise<AdminImportList> {
        const response = await axios.get('/admin/cargas-masivas/items', { params });
        return response.data as AdminImportList;
    },

    async approve(id: number, data: ApproveImportData): Promise<ApproveImportResponse> {
        const response = await axios.post(`/admin/cargas-masivas/items/${id}/aprobar`, data);
        return response.data as ApproveImportResponse;
    },

    async retry(id: number): Promise<void> {
        await axios.post(`/admin/cargas-masivas/items/${id}/reprocesar`);
    },

    async download(id: number): Promise<Blob> {
        const response = await axios.get(`/admin/cargas-masivas/items/${id}/download`, {
            responseType: 'blob',
        });
        return response.data as Blob;
    },

    async getConfiguration(): Promise<ImportConfiguration> {
        const response = await axios.get('/admin/cargas-masivas/configuracion');
        return response.data as ImportConfiguration;
    },

    async updateConfiguration(data: ImportConfiguration): Promise<ImportConfiguration> {
        const response = await axios.put('/admin/cargas-masivas/configuracion', data);
        return response.data as ImportConfiguration;
    },
};
