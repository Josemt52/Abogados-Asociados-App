import { useState, useEffect } from 'react';
import { estadisticasAPI } from '../api';

interface DashboardStats {
  expedientesActivos: number;
  enProgreso: number;
  finalizados: number;
  urgentes: number;
}

export const useEstadisticas = () => {
  const [dashboardStats, setDashboardStats] = useState<DashboardStats>({
    expedientesActivos: 0,
    enProgreso: 0,
    finalizados: 0,
    urgentes: 0,
  });
  const [loading, setLoading] = useState(true);

  const loadStats = async () => {
    try {
      setLoading(true);
      const data = await estadisticasAPI.getDashboard();
      
      setDashboardStats({
        expedientesActivos: data.expedientes?.total || 0,
        enProgreso: data.expedientes?.en_progreso || 0,
        finalizados: data.expedientes?.finalizados || 0,
        urgentes: data.expedientes?.urgentes || 0,
      });
    } catch (error) {
      console.error('Error loading stats:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadStats();
  }, []);

  const refreshStats = () => {
    loadStats();
  };

  return {
    dashboardStats,
    loading,
    refreshStats,
  };
};
