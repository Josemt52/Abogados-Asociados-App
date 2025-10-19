import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authAPI } from '../../api';
import { User, Mail, Lock, UserCheck } from 'lucide-react';
import Button from '../../components/UI/Button';
import toast from 'react-hot-toast';

const RegistrarUsuario: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    username: '',
    email: '',
    password: '',
    confirmarPassword: '',
    rol: 'usuario',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.username.trim()) {
      newErrors.username = 'El usuario es obligatorio';
    } else if (formData.username.length < 3) {
      newErrors.username = 'El usuario debe tener al menos 3 caracteres';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'El email es obligatorio';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'El email no es válido';
    }

    if (!formData.password.trim()) {
      newErrors.password = 'La contraseña es obligatoria';
    } else if (formData.password.length < 6) {
      newErrors.password = 'La contraseña debe tener al menos 6 caracteres';
    }

    if (formData.password !== formData.confirmarPassword) {
      newErrors.confirmarPassword = 'Las contraseñas no coinciden';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    setLoading(true);

    try {
      // Remove confirmarPassword before sending to API
      const { confirmarPassword, ...userData } = formData;
      await authAPI.register(userData);
      
      toast.success('Usuario registrado correctamente');
      navigate('/main');
    } catch (error) {
      // Error handling is done by the API interceptor
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }));
    }
  };

  return (
    <div className="max-w-2xl mx-auto">
      <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900 mb-2">
            Registrar Nuevo Usuario
          </h1>
          <p className="text-gray-600">
            Complete la información para crear un nuevo usuario del sistema
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label htmlFor="username" className="block text-sm font-medium text-gray-700 mb-1">
                Usuario *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <User className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="text"
                  id="username"
                  name="username"
                  value={formData.username}
                  onChange={handleChange}
                  className={`block w-full pl-10 pr-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${
                    errors.username ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Nombre de usuario"
                />
              </div>
              {errors.username && (
                <p className="mt-1 text-sm text-red-600">{errors.username}</p>
              )}
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                Email *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  className={`block w-full pl-10 pr-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${
                    errors.email ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="correo@ejemplo.com"
                />
              </div>
              {errors.email && (
                <p className="mt-1 text-sm text-red-600">{errors.email}</p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                Contraseña *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Lock className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="password"
                  id="password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  className={`block w-full pl-10 pr-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${
                    errors.password ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Mínimo 6 caracteres"
                />
              </div>
              {errors.password && (
                <p className="mt-1 text-sm text-red-600">{errors.password}</p>
              )}
            </div>

            <div>
              <label htmlFor="confirmarPassword" className="block text-sm font-medium text-gray-700 mb-1">
                Confirmar Contraseña *
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Lock className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="password"
                  id="confirmarPassword"
                  name="confirmarPassword"
                  value={formData.confirmarPassword}
                  onChange={handleChange}
                  className={`block w-full pl-10 pr-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${
                    errors.confirmarPassword ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Repita la contraseña"
                />
              </div>
              {errors.confirmarPassword && (
                <p className="mt-1 text-sm text-red-600">{errors.confirmarPassword}</p>
              )}
            </div>
          </div>

          <div>
            <label htmlFor="rol" className="block text-sm font-medium text-gray-700 mb-1">
              Rol
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <UserCheck className="h-5 w-5 text-gray-400" />
              </div>
              <select
                id="rol"
                name="rol"
                value={formData.rol}
                onChange={handleChange}
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="usuario">Usuario</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <Button
              type="button"
              variant="outline"
              onClick={() => navigate('/main')}
            >
              Cancelar
            </Button>
            <Button
              type="submit"
              variant="primary"
              loading={loading}
            >
              Registrar Usuario
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default RegistrarUsuario;