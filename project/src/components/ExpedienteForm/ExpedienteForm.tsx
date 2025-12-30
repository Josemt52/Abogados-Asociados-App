import React, { useState } from 'react';
import { expedientesAPI } from '../../api';
import Button from '../UI/Button';
import toast from 'react-hot-toast';

interface ExpedienteFormProps {
  expediente?: any;
  onSuccess: () => void;
  onCancel: () => void;
}

const ExpedienteForm: React.FC<ExpedienteFormProps> = ({
  expediente,
  onSuccess,
  onCancel,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    numero: expediente?.numero || '',
    materia: expediente?.materia || '',
    juzgado: expediente?.juzgado || '',
    especialista: expediente?.especialista || '',
    tercero: expediente?.tercero || '',
    demandado: expediente?.demandado || '',
    demandante: expediente?.demandante || '',
    estado: expediente?.estado || '',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.numero.trim()) {
      newErrors.numero = 'El número de expediente es obligatorio';
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
      if (expediente?.id) {
        await expedientesAPI.update(expediente.id, formData);
        toast.success('Expediente actualizado correctamente');
      } else {
        await expedientesAPI.create(formData);
        toast.success('Expediente creado correctamente');
      }
      onSuccess();
    } catch (error) {
      // Error handling is done by the API interceptor
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: '' }));
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label htmlFor="numero" className="block text-sm font-medium text-gray-700 mb-1">
            Número de Expediente *
          </label>
          <input
            type="text"
            id="numero"
            name="numero"
            value={formData.numero}
            onChange={handleChange}
            placeholder="EXP-2024-001"
            className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 ${
              errors.numero ? 'border-red-500' : 'border-gray-300'
            }`}
          />
          {errors.numero && (
            <p className="mt-1 text-sm text-red-600">{errors.numero}</p>
          )}
        </div>

        <div>
          <label htmlFor="materia" className="block text-sm font-medium text-gray-700 mb-1">
            Materia
          </label>
          <input
            type="text"
            id="materia"
            name="materia"
            value={formData.materia}
            onChange={handleChange}
            placeholder="Civil, Penal, Laboral..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label htmlFor="juzgado" className="block text-sm font-medium text-gray-700 mb-1">
            Juzgado
          </label>
          <input
            type="text"
            id="juzgado"
            name="juzgado"
            value={formData.juzgado}
            onChange={handleChange}
            placeholder="Juzgado Civil..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label htmlFor="especialista" className="block text-sm font-medium text-gray-700 mb-1">
            Especialista
          </label>
          <input
            type="text"
            id="especialista"
            name="especialista"
            value={formData.especialista}
            onChange={handleChange}
            placeholder="Nombre del especialista..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label htmlFor="tercero" className="block text-sm font-medium text-gray-700 mb-1">
            Tercero
          </label>
          <input
            type="text"
            id="tercero"
            name="tercero"
            value={formData.tercero}
            onChange={handleChange}
            placeholder="Tercero involucrado..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label htmlFor="demandado" className="block text-sm font-medium text-gray-700 mb-1">
            Demandado
          </label>
          <input
            type="text"
            id="demandado"
            name="demandado"
            value={formData.demandado}
            onChange={handleChange}
            placeholder="Nombre del demandado..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label htmlFor="demandante" className="block text-sm font-medium text-gray-700 mb-1">
            Demandante
          </label>
          <input
            type="text"
            id="demandante"
            name="demandante"
            value={formData.demandante}
            onChange={handleChange}
            placeholder="Nombre del demandante..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div>
          <label htmlFor="estado" className="block text-sm font-medium text-gray-700 mb-1">
            Estado
          </label>
          <input
            type="text"
            id="estado"
            name="estado"
            value={formData.estado}
            onChange={handleChange}
            placeholder="Estado del expediente..."
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
        <Button
          variant="outline"
          onClick={onCancel}
          type="button"
        >
          Cancelar
        </Button>
        <Button
          variant="primary"
          type="submit"
          loading={loading}
        >
          {expediente?.id ? 'Actualizar' : 'Crear'} Expediente
        </Button>
      </div>
    </form>
  );
};

export default ExpedienteForm;
