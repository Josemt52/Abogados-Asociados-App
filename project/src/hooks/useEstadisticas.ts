import { useState, useEffect } from 'react';
import { estadisticasAPI } from '../api';
import toast from 'react-hot-toast';

interface DashboardStats {
  expedientesActivos: number;
  enProgreso: number;
  finalizados: number;
  urgentes: number;
  totalUsuarios: number;
}

interface ExpedientesPorEstado {
  [estado: string]: number;
}

interface ActividadReciente {
  ultimosExpedientes: any[];
  totalHoy: number;
}

export const useEstadisticas = () => {
  const [dashboardStats, setDashboardStats] = useState<DashboardStats>({
    expedientesActivos: 0,
    enProgreso: 0,
    finalizados: 0,
    urgentes: 0,
    totalUsuarios: 0,
  });
  
  const [expedientesPorEstado, setExpedientesPorEstado] = useState<ExpedientesPorEstado>({});
  const [actividadReciente, setActividadReciente] = useState<ActividadReciente>({
    ultimosExpedientes: [],
    totalHoy: 0,
  });
  
  const [loading, setLoading] = useState(true);

  const fetchDashboardStats = async () => {
    try {
      const stats = await estadisticasAPI.getDashboardStats();
      setDashboardStats(stats);
    } catch (error) {
      console.error('Error fetching dashboard stats:', error);
      toast.error('Error al cargar estadísticas del dashboard');
    }
  };

  const fetchExpedientesPorEstado = async () => {
    try {
      const stats = await estadisticasAPI.getExpedientesPorEstado();
      setExpedientesPorEstado(stats);
    } catch (error) {
      console.error('Error fetching expedientes por estado:', error);
      toast.error('Error al cargar estadísticas por estado');
    }
  };

  const fetchActividadReciente = async () => {
    try {
      const actividad = await estadisticasAPI.getActividadReciente();
      setActividadReciente(actividad);
    } catch (error) {
      console.error('Error fetching actividad reciente:', error);
      toast.error('Error al cargar actividad reciente');
    }
  };

  const refreshStats = async () => {
    setLoading(true);
    try {
      await Promise.all([
        fetchDashboardStats(),
        fetchExpedientesPorEstado(),
        fetchActividadReciente(),
      ]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    refreshStats();
  }, []);

  return {
    dashboardStats,
    expedientesPorEstado,
    actividadReciente,
    loading,
    refreshStats,
  };
};
