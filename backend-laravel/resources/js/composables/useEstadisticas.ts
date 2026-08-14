import { onMounted, ref } from 'vue';
import { estadisticasAPI } from '@/api';

export interface DashboardStats {
    expedientesActivos: number;
    enProgreso: number;
    finalizados: number;
    urgentes: number;
}

export const useEstadisticas = () => {
    const dashboardStats = ref<DashboardStats>({
        expedientesActivos: 0,
        enProgreso: 0,
        finalizados: 0,
        urgentes: 0,
    });
    const loading = ref(true);

    const refreshStats = async (): Promise<void> => {
        try {
            loading.value = true;
            const data = await estadisticasAPI.getDashboard();

            dashboardStats.value = {
                expedientesActivos: data.expedientes.total || 0,
                enProgreso: data.expedientes.en_progreso || 0,
                finalizados: data.expedientes.finalizados || 0,
                urgentes: data.expedientes.urgentes || 0,
            };
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
        } finally {
            loading.value = false;
        }
    };

    onMounted(refreshStats);

    return { dashboardStats, loading, refreshStats };
};
