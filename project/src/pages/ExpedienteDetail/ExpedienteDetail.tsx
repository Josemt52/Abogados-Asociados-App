import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { expedientesAPI } from '../../api';
import { useFetch } from '../../hooks/useFetch';
import { useDocumentGeneration } from '../../hooks/useDocumentGeneration';
import { 
  ArrowLeft, 
  Edit, 
  Upload, 
  Download, 
  FileText, 
  File,
  Trash2,
  Clock,
  User,
  Scale
} from 'lucide-react';
import Button from '../../components/UI/Button';
import Modal from '../../components/UI/Modal';
import ExpedienteForm from '../../components/ExpedienteForm/ExpedienteForm';
import FileUploader from '../../components/FileUploader/FileUploader';
import { downloadBlob } from '../../utils/fileDownload';
import toast from 'react-hot-toast';

const ExpedienteDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  
  const [showEditModal, setShowEditModal] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showUpdateStatusModal, setShowUpdateStatusModal] = useState(false);
  const [statusText, setStatusText] = useState<string>('');
  const [statusLoading, setStatusLoading] = useState(false);
  const [loading, setLoading] = useState(false);

  const { data: expediente, loading: expedienteLoading, refetch } = useFetch(
    () => expedientesAPI.getById(id!),
    [id]
  );

  const { generateWord, generatePDF, isGenerating } = useDocumentGeneration();

  if (!id) {
    navigate('/expedientes');
    return null;
  }

  const handleFileUpload = async (file: File, onProgress?: (progress: number) => void) => {
    try {
      // Simulate progress for demo purposes
      if (onProgress) {
        for (let i = 0; i <= 100; i += 10) {
          setTimeout(() => onProgress(i), i * 10);
        }
      }
      
      await expedientesAPI.uploadFile(id, file);
      toast.success('Archivo subido correctamente');
      setShowUploadModal(false);
      await refetch(); // Refrescar para mostrar el nuevo estado

      // Pre-fill status text from refreshed expediente and ask user if they want to update it
      setStatusText((expediente && expediente.estado) || '');
      setShowUpdateStatusModal(true);
    } catch (error) {
      toast.error('Error al subir el archivo');
      throw error;
    }
  };

  const handleDownloadFile = async () => {
    try {
      setLoading(true);
      const blob = await expedientesAPI.downloadFile(id);
      const filename = expediente?.nombre_archivo || 'documento.docx';
      downloadBlob(blob, filename);
      toast.success('Archivo descargado correctamente');
    } catch (error) {
      toast.error('Error al descargar el archivo');
    } finally {
      setLoading(false);
    }
  };

  const handleGenerateWord = async () => {
    await generateWord(id, expediente?.numero);
  };

  const handleGeneratePDF = async () => {
    await generatePDF(id, expediente?.numero);
  };

  const handleDelete = async () => {
    try {
      setLoading(true);
      await expedientesAPI.delete(id);
      toast.success('Expediente eliminado correctamente');
      navigate('/expedientes');
    } catch (error) {
      toast.error('Error al eliminar el expediente');
    } finally {
      setLoading(false);
      setShowDeleteConfirm(false);
    }
  };

  const handleEditSuccess = () => {
    setShowEditModal(false);
    refetch();
  };

  const handleUpdateStatus = async () => {
    try {
      setStatusLoading(true);
      await expedientesAPI.update(id!, { estado: statusText });
      toast.success('Estado del expediente actualizado');
      setShowUpdateStatusModal(false);
      await refetch();
    } catch (error) {
      toast.error('Error al actualizar el estado');
    } finally {
      setStatusLoading(false);
    }
  };

  if (expedienteLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-pulse text-gray-500">Cargando expediente...</div>
      </div>
    );
  }

  if (!expediente) {
    return (
      <div className="text-center py-12">
        <Scale className="h-12 w-12 text-gray-400 mx-auto mb-4" />
        <p className="text-gray-500">Expediente no encontrado</p>
        <Button
          variant="outline"
          onClick={() => navigate('/expedientes')}
          className="mt-4"
        >
          Volver a Expedientes
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button
            variant="outline"
            icon={<ArrowLeft className="h-4 w-4" />}
            onClick={() => navigate('/expedientes')}
          >
            Volver
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Expediente #{expediente.numero}
            </h1>
            <p className="text-gray-600">{expediente.materia}</p>
          </div>
        </div>
        <div className="flex space-x-3">
          <Button
            variant="outline"
            icon={<Edit className="h-4 w-4" />}
            onClick={() => setShowEditModal(true)}
          >
            Editar
          </Button>
          <Button
            variant="outline"
            icon={<Upload className="h-4 w-4" />}
            onClick={() => setShowUploadModal(true)}
          >
            Subir Archivo
          </Button>
        </div>
      </div>

      {/* Status Banner */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-center space-x-2">
          <Clock className="h-5 w-5 text-blue-600" />
          <span className="text-sm font-medium text-blue-900">
            Estado: {expediente.estado ? 'Activo' : 'En proceso'}
          </span>
          <span className="text-sm text-blue-700">
            • Última actualización: {new Date().toLocaleDateString()}
          </span>
        </div>
      </div>

      {/* Main Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Expediente Details */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <Scale className="h-5 w-5 mr-2 text-blue-600" />
              Información del Expediente
            </h2>
            <dl className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <dt className="text-sm font-medium text-gray-500">Número</dt>
                <dd className="mt-1 text-sm text-gray-900 font-medium">{expediente.numero}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Materia</dt>
                <dd className="mt-1 text-sm text-gray-900">{expediente.materia}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Juzgado</dt>
                <dd className="mt-1 text-sm text-gray-900">{expediente.juzgado}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500 flex items-center">
                  <User className="h-4 w-4 mr-1" />
                  Especialista
                </dt>
                <dd className="mt-1 text-sm text-gray-900">{expediente.especialista}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Tercero</dt>
                <dd className="mt-1 text-sm text-gray-900">{expediente.tercero || 'N/A'}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Demandado</dt>
                <dd className="mt-1 text-sm text-gray-900">{expediente.demandado || 'N/A'}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Demandante</dt>
                <dd className="mt-1 text-sm text-gray-900">{expediente.demandante || 'N/A'}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-500">Archivo</dt>
                <dd className="mt-1">
                  {expediente.archivo ? (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      ✓ {expediente.nombre_archivo || 'Disponible'}
                    </span>
                  ) : (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                      Sin archivo
                    </span>
                  )}
                </dd>
              </div>
            </dl>
          </div>

          {/* Estado */}
          {expediente.estado && (
            <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Estado Actual</h2>
              <div 
                className="prose max-w-none text-sm text-gray-700 bg-gray-50 p-4 rounded-lg"
              >
                {expediente.estado}
              </div>
            </div>
          )}

        </div>

        {/* Actions Sidebar */}
        <div className="space-y-6">
          {/* Quick Actions */}
          <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Acciones Rápidas</h3>
            <div className="space-y-3">
              {expediente.archivo && (
                <Button
                  variant="outline"
                  icon={<Download className="h-4 w-4" />}
                  onClick={handleDownloadFile}
                  loading={loading}
                  className="w-full justify-start"
                >
                  Descargar Archivo
                </Button>
              )}
              <Button
                variant="outline"
                icon={<FileText className="h-4 w-4" />}
                onClick={handleGenerateWord}
                loading={isGenerating}
                className="w-full justify-start"
              >
                Generar Word
              </Button>
              <Button
                variant="outline"
                icon={<File className="h-4 w-4" />}
                onClick={handleGeneratePDF}
                loading={isGenerating}
                className="w-full justify-start"
              >
                Generar PDF
              </Button>
            </div>
          </div>

          {/* File Info */}
          {expediente.archivo && (
            <div className="bg-green-50 border border-green-200 rounded-lg p-6">
              <h3 className="text-sm font-semibold text-green-900 mb-3">Archivo Adjunto</h3>
              <div className="space-y-2">
                <p className="text-sm text-green-700">
                  <strong>Nombre:</strong> {expediente.nombre_archivo || 'documento.docx'}
                </p>
                <p className="text-sm text-green-700">
                  <strong>Estado:</strong> Disponible
                </p>
              </div>
            </div>
          )}

          {/* Danger Zone */}
          <div className="bg-white shadow-sm rounded-lg border border-red-200 p-6">
            <h3 className="text-lg font-semibold text-red-900 mb-4">Zona de Peligro</h3>
            <Button
              variant="danger"
              icon={<Trash2 className="h-4 w-4" />}
              onClick={() => setShowDeleteConfirm(true)}
              className="w-full justify-start"
            >
              Eliminar Expediente
            </Button>
          </div>
        </div>
      </div>

      {/* Edit Modal */}
      <Modal
        isOpen={showEditModal}
        onClose={() => setShowEditModal(false)}
        title="Editar Expediente"
        size="xl"
      >
        <ExpedienteForm
          expediente={expediente}
          onSuccess={handleEditSuccess}
          onCancel={() => setShowEditModal(false)}
        />
      </Modal>

      {/* Upload Modal */}
      <Modal
        isOpen={showUploadModal}
        onClose={() => setShowUploadModal(false)}
        title="Subir Archivo"
        size="md"
      >
        <FileUploader
          onUpload={handleFileUpload}
          loading={loading}
        />
      </Modal>

      {/* Update Status Modal (shown after successful upload) */}
      <Modal
        isOpen={showUpdateStatusModal}
        onClose={() => setShowUpdateStatusModal(false)}
        title="Actualizar estado del expediente"
        size="md"
      >
        <div className="space-y-4">
          <p className="text-sm text-gray-600">¿Desea actualizar el estado del expediente ahora? Puede dejar una nota o pegar el texto del estado.</p>
          <textarea
            rows={6}
            value={statusText}
            onChange={(e) => setStatusText(e.target.value)}
            className="w-full border border-gray-300 rounded-md p-2 text-sm"
          />
          <div className="flex justify-end space-x-3">
            <Button variant="outline" onClick={() => setShowUpdateStatusModal(false)}>
              No, luego
            </Button>
            <Button variant="primary" onClick={handleUpdateStatus} loading={statusLoading}>
              Guardar estado
            </Button>
          </div>
        </div>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteConfirm}
        onClose={() => setShowDeleteConfirm(false)}
        title="Confirmar Eliminación"
        size="md"
      >
        <div className="space-y-4">
          <div className="flex items-center space-x-3">
            <div className="flex-shrink-0">
              <Trash2 className="h-6 w-6 text-red-600" />
            </div>
            <div>
              <p className="text-sm text-gray-900">
                ¿Está seguro que desea eliminar este expediente?
              </p>
              <p className="text-sm text-gray-500">
                Esta acción no se puede deshacer.
              </p>
            </div>
          </div>
          <div className="bg-red-50 border border-red-200 rounded-md p-3">
            <p className="text-sm text-red-700">
              <strong>Expediente:</strong> #{expediente.numero} - {expediente.materia}
            </p>
          </div>
          <div className="flex justify-end space-x-3">
            <Button
              variant="outline"
              onClick={() => setShowDeleteConfirm(false)}
            >
              Cancelar
            </Button>
            <Button
              variant="danger"
              onClick={handleDelete}
              loading={loading}
            >
              Eliminar Expediente
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default ExpedienteDetail;