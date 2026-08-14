import axios from './axios';
import type { Expediente } from './expedientes';

export interface Estadisticas {
    expedientes: {
        total: number;
        en_progreso: number;
        finalizados: number;
        urgentes: number;
        pendientes: number;
    };
    usuarios: { total: number };
    mensajes: {
        total: number;
        recientes: number;
    };
    expedientes_recientes: Expediente[];
}

export interface EstadisticasPorEstado {
    [estado: string]: number;
}

export interface EstadisticasPorTipo {
    [tipo: string]: number;
}

export const estadisticasAPI = {
    async getDashboard(): Promise<Estadisticas> {
        const response = await axios.get('/estadisticas');
        return response.data;
    },

    async getExpedientesPorEstado(): Promise<EstadisticasPorEstado> {
        const response = await axios.get('/estadisticas/expedientes-por-estado');
        return response.data;
    },

    async getExpedientesPorTipo(): Promise<EstadisticasPorTipo> {
        const response = await axios.get('/estadisticas/expedientes-por-tipo');
        return response.data;
    },
};
