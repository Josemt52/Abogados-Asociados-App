import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { expedientesAPI } from '../../api';
import { useFetch } from '../../hooks/useFetch';
import { Plus, Search, Filter, Eye } from 'lucide-react';
import Table from '../../components/UI/Table';
import Button from '../../components/UI/Button';
import Modal from '../../components/UI/Modal';
import ExpedienteForm from '../../components/ExpedienteForm/ExpedienteForm';

const Expedientes: React.FC = () => {
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [showCreateModal, setShowCreateModal] = useState(false);

  const { data: expedientes, loading, refetch } = useFetch(
    () => expedientesAPI.getAll(),
    []
  );

  const handleRowClick = (expediente: any) => {
    navigate(`/expedientes/${expediente.id}`);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
    refetch();
  };

  const handleCreateSuccess = () => {
    setShowCreateModal(false);
    refetch();
  };

  // Viewer modal state
  const [showViewerModal, setShowViewerModal] = useState(false);
  const [viewerLoading, setViewerLoading] = useState(false);
  const [viewerBlobUrl, setViewerBlobUrl] = useState<string | null>(null);
  const [viewerMessage, setViewerMessage] = useState<string | null>(null);
  const [viewerMimeType, setViewerMimeType] = useState<string | null>(null);
  const [selectedViewerRow, setSelectedViewerRow] = useState<any | null>(null);

  const handleViewClick = async (e: React.MouseEvent, row: any) => {
    e.stopPropagation();
    // store selected row for modal actions (download filename etc.)
    setSelectedViewerRow(row);

    // If the expediente has no file, show friendly message
    if (!row.archivo) {
      setViewerMessage('Este expediente no tiene un documento asociado todavía.');
      setViewerBlobUrl(null);
      setShowViewerModal(true);
      return;
    }

    try {
      setViewerLoading(true);
      setViewerMessage(null);
      // First try to download the stored file (could be docx, pdf, etc.)
      const originalBlob = await expedientesAPI.downloadFile(row.id);
      const originalType = (originalBlob as Blob).type || '';

      if (originalType.includes('pdf')) {
        const url = URL.createObjectURL(originalBlob);
        setViewerBlobUrl(url);
        setViewerMimeType('application/pdf');
        setShowViewerModal(true);
      } else {
        // Not a PDF: attempt server-side conversion from Word -> PDF using existing endpoint
        try {
          const pdfBlob = await expedientesAPI.generatePdf(row.id);
          const pdfUrl = URL.createObjectURL(pdfBlob);
          setViewerBlobUrl(pdfUrl);
          setViewerMimeType('application/pdf');
          setShowViewerModal(true);
        } catch (convErr) {
          // Conversion failed: fall back to download original file and show message
          console.warn('PDF conversion failed, falling back to download', convErr);
          const url = URL.createObjectURL(originalBlob);
          setViewerBlobUrl(url);
          setViewerMimeType(originalType || null);
          setViewerMessage('No se pudo convertir el documento a PDF. Puede descargar el archivo original.');
          setShowViewerModal(true);
        }
      }
    } catch (err) {
      console.error('Error fetching expediente PDF', err);
      setViewerMessage('Error al descargar el documento. Intente nuevamente.');
      setViewerBlobUrl(null);
      setShowViewerModal(true);
    } finally {
      setViewerLoading(false);
    }
  };

  const columns = [
    { key: 'numero', header: 'Número' },
    { key: 'materia', header: 'Materia' },
    { key: 'juzgado', header: 'Juzgado' },
    { key: 'especialista', header: 'Especialista' },
    { key: 'demandante', header: 'Demandante' },
    { key: 'demandado', header: 'Demandado' },
    {
      key: 'archivo',
      header: 'Archivo',
      render: (value: any) => (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
          value ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
        }`}>
          {value ? 'Sí' : 'No'}
        </span>
      ),
    },
    {
      key: 'acciones',
      header: 'Acciones',
      render: (_: any, row: any) => (
        <div className="flex items-center justify-end space-x-2">
          <button
            onClick={(e) => handleViewClick(e, row)}
            className="p-1.5 text-gray-700 hover:bg-gray-100 rounded transition-colors"
            title="Visualizar documento"
          >
            <Eye className="h-4 w-4" />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Expedientes</h1>
          <p className="text-gray-600">Gestiona todos los expedientes del sistema</p>
        </div>
        <Button
          variant="primary"
          icon={<Plus className="h-4 w-4" />}
          onClick={() => setShowCreateModal(true)}
        >
          Nuevo Expediente
        </Button>
      </div>

      {/* Search and Filters */}
      <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <form onSubmit={handleSearch} className="flex space-x-4">
          <div className="flex-1">
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <Search className="h-5 w-5 text-gray-400" />
              </div>
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Buscar por número de expediente..."
                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
          <Button type="submit" variant="outline">
            Buscar
          </Button>
          <Button type="button" variant="outline" icon={<Filter className="h-4 w-4" />}>
            Filtros
          </Button>
        </form>
      </div>

      {/* Results Summary */}
      {!loading && expedientes && (
        <div className="text-sm text-gray-600">
          {Array.isArray(expedientes) ? expedientes.length : 0} expedientes encontrados
        </div>
      )}

      {/* Table */}
      <Table
        columns={columns}
        data={Array.isArray(expedientes) ? expedientes : []}
        loading={loading}
        onRowClick={handleRowClick}
        emptyMessage="No se encontraron expedientes"
      />

      {/* Pagination */}
      {!loading && expedientes && Array.isArray(expedientes) && expedientes.length > 0 && (
        <div className="flex items-center justify-between bg-white px-4 py-3 border border-gray-200 rounded-lg">
          <div className="text-sm text-gray-700">
            Página {currentPage}
          </div>
          <div className="flex space-x-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
              disabled={currentPage === 1}
            >
              Anterior
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setCurrentPage(prev => prev + 1)}
            >
              Siguiente
            </Button>
          </div>
        </div>
      )}

      {/* Create Modal */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        title="Crear Nuevo Expediente"
        size="xl"
      >
        <ExpedienteForm
          onSuccess={handleCreateSuccess}
          onCancel={() => setShowCreateModal(false)}
        />
      </Modal>

      {/* Viewer Modal */}
      <Modal
        isOpen={showViewerModal}
        onClose={() => {
          setShowViewerModal(false);
          // cleanup blob URL
          if (viewerBlobUrl) {
            URL.revokeObjectURL(viewerBlobUrl);
            setViewerBlobUrl(null);
          }
          setViewerMessage(null);
          setViewerMimeType(null);
          setSelectedViewerRow(null);
        }}
        title={`Visor de Documento - ${selectedViewerRow?.numero || ''}`}
        size="full"
      >
        <div className="flex flex-col h-[85vh]">
          {/* Toolbar */}
          {!viewerLoading && viewerBlobUrl && (
            <div className="flex items-center justify-between px-4 py-3 bg-gray-50 border-b">
              <div className="text-sm text-gray-600">
                {selectedViewerRow?.nombre_archivo || 'documento.pdf'}
              </div>
              <a
                href={viewerBlobUrl}
                download={selectedViewerRow?.nombre_archivo || 'documento.pdf'}
                className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors"
              >
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Descargar
              </a>
            </div>
          )}

          {/* Content */}
          <div className="flex-1 overflow-hidden">
            {viewerLoading && (
              <div className="flex items-center justify-center h-full">
                <div className="text-center">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                  <p className="text-gray-600">Cargando documento...</p>
                </div>
              </div>
            )}

            {!viewerLoading && viewerMessage && (
              <div className="flex items-center justify-center h-full">
                <div className="text-center p-6">
                  <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <p className="text-gray-700">{viewerMessage}</p>
                </div>
              </div>
            )}

            {!viewerLoading && viewerBlobUrl && viewerMimeType && viewerMimeType.includes('pdf') && (
              <iframe
                src={viewerBlobUrl}
                title="Documento PDF"
                className="w-full h-full"
                style={{ border: 'none' }}
              />
            )}

            {!viewerLoading && viewerBlobUrl && viewerMimeType && !viewerMimeType.includes('pdf') && (
              <div className="flex items-center justify-center h-full">
                <div className="text-center p-6">
                  <svg className="w-16 h-16 text-yellow-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <p className="mb-4 text-gray-700 font-medium">El archivo asociado no es un PDF</p>
                  <p className="text-sm text-gray-500 mb-4">Este tipo de archivo no puede previsualizarse en el navegador</p>
                  <a
                    href={viewerBlobUrl}
                    download={selectedViewerRow?.nombre_archivo || 'documento'}
                    className="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar archivo
                  </a>
                </div>
              </div>
            )}
          </div>
        </div>
      </Modal>
    </div>
  );
};

export default Expedientes;