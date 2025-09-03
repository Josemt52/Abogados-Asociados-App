import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useEstadisticas } from '../../hooks/useEstadisticas';
import { FileText, Users, Scale, TrendingUp, Clock, AlertCircle, RefreshCw } from 'lucide-react';
import Button from '../../components/UI/Button';

const Main: React.FC = () => {
  const { user } = useAuth();
  const { dashboardStats, loading, refreshStats } = useEstadisticas();

  const quickActions = [
    {
      title: 'Ver Expedientes',
      description: 'Administrar y consultar todos los expedientes',
      icon: FileText,
      href: '/expedientes',
      color: 'bg-blue-500',
    },
    {
      title: 'Registrar Usuario',
      description: 'Crear nuevos usuarios del sistema',
      icon: Users,
      href: '/usuarios/registrar',
      color: 'bg-green-500',
    },
  ];

  const stats = [
    {
      name: 'Expedientes Activos',
      value: loading ? '...' : dashboardStats.expedientesActivos.toString(),
      icon: Scale,
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
    },
    {
      name: 'En Progreso',
      value: loading ? '...' : dashboardStats.enProgreso.toString(),
      icon: Clock,
      color: 'text-yellow-600',
      bgColor: 'bg-yellow-100',
    },
    {
      name: 'Finalizados',
      value: loading ? '...' : dashboardStats.finalizados.toString(),
      icon: TrendingUp,
      color: 'text-green-600',
      bgColor: 'bg-green-100',
    },
    {
      name: 'Urgentes',
      value: loading ? '...' : dashboardStats.urgentes.toString(),
      icon: AlertCircle,
      color: 'text-red-600',
      bgColor: 'bg-red-100',
    },
  ];

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex justify-between items-start">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            Panel Principal
          </h1>
          <p className="text-gray-600">
            Bienvenido, <span className="font-semibold">{user?.username}</span>. 
            Administra expedientes y gestiona el sistema jurídico.
          </p>
        </div>
        <Button
          variant="outline"
          onClick={refreshStats}
          disabled={loading}
          className="flex items-center space-x-2"
        >
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          <span>Actualizar</span>
        </Button>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat) => {
          const Icon = stat.icon;
          return (
            <div
              key={stat.name}
              className="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
            >
              <div className="flex items-center">
                <div className={`p-2 rounded-lg ${stat.bgColor}`}>
                  <Icon className={`h-6 w-6 ${stat.color}`} />
                </div>
                <div className="ml-4">
                  <p className="text-sm font-medium text-gray-600">{stat.name}</p>
                  <p className="text-2xl font-semibold text-gray-900">{stat.value}</p>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          Acciones Rápidas
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {quickActions.map((action) => {
            const Icon = action.icon;
            return (
              <Link
                key={action.title}
                to={action.href}
                className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow group"
              >
                <div className="flex items-start space-x-4">
                  <div className={`p-3 rounded-lg ${action.color} group-hover:scale-110 transition-transform`}>
                    <Icon className="h-6 w-6 text-white" />
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold text-gray-900 mb-2 group-hover:text-blue-700 transition-colors">
                      {action.title}
                    </h3>
                    <p className="text-gray-600">
                      {action.description}
                    </p>
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      </div>

      {/* Recent Activity */}
      <div>
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          Actividad Reciente
        </h2>
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 divide-y divide-gray-200">
          <div className="p-4">
            <div className="flex items-center space-x-3">
              <div className="w-2 h-2 bg-green-500 rounded-full"></div>
              <p className="text-sm text-gray-900">
                Expediente #2024-001 actualizado
              </p>
              <span className="text-xs text-gray-500">Hace 2 horas</span>
            </div>
          </div>
          <div className="p-4">
            <div className="flex items-center space-x-3">
              <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
              <p className="text-sm text-gray-900">
                Nuevo documento generado para expediente #2024-003
              </p>
              <span className="text-xs text-gray-500">Hace 5 horas</span>
            </div>
          </div>
          <div className="p-4">
            <div className="flex items-center space-x-3">
              <div className="w-2 h-2 bg-yellow-500 rounded-full"></div>
              <p className="text-sm text-gray-900">
                Resolución añadida a expediente #2024-002
              </p>
              <span className="text-xs text-gray-500">Hace 1 día</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Main;