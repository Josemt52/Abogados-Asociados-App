import React, { useState, useEffect } from 'react';
import { Search, Edit, Trash2, UserPlus, Shield, User as UserIcon } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useFetch } from '../../hooks/useFetch';
import { api } from '../../api';
import Table from '../../components/UI/Table';
import Button from '../../components/UI/Button';
import Modal from '../../components/UI/Modal';
import toast from 'react-hot-toast';

interface User {
  id: number;
  nombre: string;
  username: string;
  rol: {
    id: number;
    nombre: string;
  };
}

interface EditUserData {
  nombre: string;
  username: string;
  rolId: number;
  password?: string;
}

const Usuarios: React.FC = () => {
  const { user: currentUser } = useAuth();
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredUsuarios, setFilteredUsuarios] = useState<User[]>([]);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [editFormData, setEditFormData] = useState<EditUserData>({
    nombre: '',
    username: '',
    rolId: 2,
    password: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Verificar que el usuario es admin
  const isAdmin = currentUser?.rol?.nombre?.toLowerCase() === 'admin';

  const { data: usuarios, loading, refetch } = useFetch<User[]>(
    async () => {
      const response = await api.get('/api/usuarios');
      return response.data;
    },
    []
  );

  // Filtrar usuarios según búsqueda
  useEffect(() => {
    if (!usuarios) {
      setFilteredUsuarios([]);
      return;
    }

    if (!searchTerm.trim()) {
      setFilteredUsuarios(usuarios);
      return;
    }

    const term = searchTerm.toLowerCase();
    const filtered = usuarios.filter(
      (u) =>
        u.nombre.toLowerCase().includes(term) ||
        u.username.toLowerCase().includes(term) ||
        u.rol.nombre.toLowerCase().includes(term)
    );
    setFilteredUsuarios(filtered);
  }, [usuarios, searchTerm]);

  const handleEditClick = (usuario: User) => {
    setSelectedUser(usuario);
    setEditFormData({
      nombre: usuario.nombre,
      username: usuario.username,
      rolId: usuario.rol.id,
      password: '',
    });
    setShowEditModal(true);
  };

  const handleDeleteClick = (usuario: User) => {
    setSelectedUser(usuario);
    setShowDeleteModal(true);
  };

  const handleEditSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUser) return;

    setIsSubmitting(true);
    try {
      const updateData: any = {
        nombre: editFormData.nombre,
        username: editFormData.username,
        rol: editFormData.rolId === 1 ? 'admin' : 'usuario',
      };

      // Solo incluir password si se ingresó uno nuevo
      if (editFormData.password && editFormData.password.trim()) {
        updateData.password = editFormData.password;
      }

      await api.put(`/api/usuarios/${selectedUser.id}`, updateData);
      toast.success('Usuario actualizado correctamente');
      setShowEditModal(false);
      setSelectedUser(null);
      refetch();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Error al actualizar usuario');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!selectedUser) return;

    setIsSubmitting(true);
    try {
      await api.delete(`/api/usuarios/${selectedUser.id}`);
      toast.success('Usuario eliminado correctamente');
      setShowDeleteModal(false);
      setSelectedUser(null);
      refetch();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Error al eliminar usuario');
    } finally {
      setIsSubmitting(false);
    }
  };

  const columns = [
    {
      key: 'id',
      header: 'ID',
      render: (value: number) => (
        <span className="font-mono text-xs text-gray-500">#{value}</span>
      ),
    },
    {
      key: 'nombre',
      header: 'Nombre',
      render: (value: string) => (
        <div className="flex items-center space-x-2">
          <UserIcon className="h-4 w-4 text-gray-400" />
          <span className="font-medium text-gray-900">{value}</span>
        </div>
      ),
    },
    {
      key: 'username',
      header: 'Usuario',
      render: (value: string) => (
        <span className="text-gray-700">{value}</span>
      ),
    },
    {
      key: 'rol',
      header: 'Rol',
      render: (value: { nombre: string }) => (
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
            value.nombre.toLowerCase() === 'admin'
              ? 'bg-purple-100 text-purple-800'
              : 'bg-blue-100 text-blue-800'
          }`}
        >
          <Shield className="h-3 w-3 mr-1" />
          {value.nombre}
        </span>
      ),
    },
    {
      key: 'acciones',
      header: 'Acciones',
      render: (_: any, row: User) => (
        <div className="flex items-center space-x-2">
          <button
            onClick={(e) => {
              e.stopPropagation();
              handleEditClick(row);
            }}
            className="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors"
            title="Editar usuario"
          >
            <Edit className="h-4 w-4" />
          </button>
          <button
            onClick={(e) => {
              e.stopPropagation();
              handleDeleteClick(row);
            }}
            className="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors"
            title="Eliminar usuario"
            disabled={row.id === Number(currentUser?.id)}
          >
            <Trash2 className="h-4 w-4" />
          </button>
        </div>
      ),
    },
  ];

  // Verificación de permisos
  if (!isAdmin) {
    return (
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <div className="text-center">
          <Shield className="h-12 w-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-semibold text-gray-900 mb-2">
            Acceso Restringido
          </h2>
          <p className="text-gray-600">
            No tienes permisos para acceder a esta sección.
            <br />
            Solo los administradores pueden gestionar usuarios.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
          <p className="text-gray-600">
            Administra los usuarios del sistema y sus roles
          </p>
        </div>
        <Button
          variant="primary"
          icon={<UserPlus className="h-4 w-4" />}
          onClick={() => window.location.href = '/usuarios/registrar'}
        >
          Nuevo Usuario
        </Button>
      </div>

      {/* Search Bar */}
      <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div className="relative">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <Search className="h-5 w-5 text-gray-400" />
          </div>
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          placeholder="Buscar por nombre, usuario o rol..."
          className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      {/* Results Summary */}
      {!loading && filteredUsuarios && (
        <div className="text-sm text-gray-600">
          {filteredUsuarios.length} usuario{filteredUsuarios.length !== 1 ? 's' : ''}{' '}
          {searchTerm ? 'encontrado(s)' : 'registrado(s)'}
        </div>
      )}

      {/* Table */}
      <Table
        columns={columns}
        data={filteredUsuarios || []}
        loading={loading}
        emptyMessage={
          searchTerm
            ? 'No se encontraron usuarios con ese criterio'
            : 'No hay usuarios registrados'
        }
      />

      {/* Edit Modal */}
      <Modal
        isOpen={showEditModal}
        onClose={() => {
          setShowEditModal(false);
          setSelectedUser(null);
        }}
        title="Editar Usuario"
        size="md"
      >
        <form onSubmit={handleEditSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Nombre completo
            </label>
            <input
              type="text"
              value={editFormData.nombre}
              onChange={(e) =>
                setEditFormData({ ...editFormData, nombre: e.target.value })
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Nombre de usuario
            </label>
            <input
              type="text"
              value={editFormData.username}
              onChange={(e) =>
                setEditFormData({ ...editFormData, username: e.target.value })
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Rol
            </label>
            <select
              value={editFormData.rolId}
              onChange={(e) =>
                setEditFormData({ ...editFormData, rolId: parseInt(e.target.value) })
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value={2}>Usuario</option>
              <option value={1}>Admin</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Nueva Contraseña (opcional)
            </label>
            <input
              type="password"
              value={editFormData.password}
              onChange={(e) =>
                setEditFormData({ ...editFormData, password: e.target.value })
              }
              placeholder="Dejar vacío para mantener la actual"
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            />
            <p className="text-xs text-gray-500 mt-1">
              Solo ingresa una contraseña si deseas cambiarla
            </p>
          </div>

          <div className="flex justify-end space-x-3 pt-4 border-t">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setShowEditModal(false);
                setSelectedUser(null);
              }}
              disabled={isSubmitting}
            >
              Cancelar
            </Button>
            <Button type="submit" variant="primary" loading={isSubmitting}>
              Guardar Cambios
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => {
          setShowDeleteModal(false);
          setSelectedUser(null);
        }}
        title="Confirmar Eliminación"
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-gray-700">
            ¿Estás seguro de que deseas eliminar al usuario{' '}
            <span className="font-semibold">{selectedUser?.username}</span>?
          </p>
          <p className="text-sm text-red-600">
            Esta acción no se puede deshacer.
          </p>

          <div className="flex justify-end space-x-3 pt-4 border-t">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setShowDeleteModal(false);
                setSelectedUser(null);
              }}
              disabled={isSubmitting}
            >
              Cancelar
            </Button>
            <Button
              type="button"
              variant="primary"
              onClick={handleDeleteConfirm}
              loading={isSubmitting}
              className="bg-red-600 hover:bg-red-700 focus:ring-red-500"
            >
              Eliminar
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default Usuarios;
