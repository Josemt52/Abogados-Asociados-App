import axios from './axios';
import { Expediente } from './expedientes';

export interface Estadisticas {
  expedientes: {
    total: number;
    en_proceso: number;
    finalizado: number;
    archivado: number;
  };
  usuarios: {
    total: number;
  };
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
  getDashboard: async (): Promise<Estadisticas> => {
    const response = await axios.get('/estadisticas');
    return response.data;
  },

  getExpedientesPorEstado: async (): Promise<EstadisticasPorEstado> => {
    const response = await axios.get('/estadisticas/expedientes-por-estado');
    return response.data;
  },

  getExpedientesPorTipo: async (): Promise<EstadisticasPorTipo> => {
    const response = await axios.get('/estadisticas/expedientes-por-tipo');
    return response.data;
  },
};
