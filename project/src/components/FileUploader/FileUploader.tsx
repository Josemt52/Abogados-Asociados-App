import React, { useState } from 'react';
import { Upload, File, X, AlertCircle } from 'lucide-react';
import Button from '../UI/Button';
import ProgressBar from '../UI/ProgressBar';
import { validateFileSize, validateFileType, formatFileSize } from '../../utils/fileDownload';
import toast from 'react-hot-toast';

interface FileUploaderProps {
  onUpload: (file: File, onProgress?: (progress: number) => void) => Promise<void>;
  accept?: string;
  maxSize?: number;
  loading?: boolean;
  multiple?: boolean;
}

const FileUploader: React.FC<FileUploaderProps> = ({
  onUpload,
  accept = '.docx',
  maxSize = parseInt(import.meta.env.VITE_MAX_FILE_SIZE || '10485760'), // 10MB default
  loading = false,
  multiple = false,
}) => {
  const [dragOver, setDragOver] = useState(false);
  const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isUploading, setIsUploading] = useState(false);

  const validateFile = (file: File) => {
    if (!validateFileSize(file, maxSize)) {
      toast.error(`El archivo es demasiado grande. Máximo ${formatFileSize(maxSize)}`);
      return false;
    }

    const acceptedTypes = accept.split(',').map(type => type.trim());
    if (!validateFileType(file, acceptedTypes)) {
      toast.error(`Tipo de archivo no permitido. Tipos aceptados: ${accept}`);
      return false;
    }

    return true;
  };

  const handleFileSelect = (files: FileList | null) => {
    if (!files) return;

    const validFiles: File[] = [];
    Array.from(files).forEach(file => {
      if (validateFile(file)) {
        validFiles.push(file);
      }
    });

    if (multiple) {
      setSelectedFiles(prev => [...prev, ...validFiles]);
    } else {
      setSelectedFiles(validFiles.slice(0, 1));
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    handleFileSelect(e.dataTransfer.files);
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    handleFileSelect(e.target.files);
    e.target.value = ''; // Reset input
  };

  const handleUpload = async (file: File) => {
    setIsUploading(true);
    setUploadProgress(0);

    try {
      await onUpload(file, (progress) => {
        setUploadProgress(progress);
      });
      
      setSelectedFiles(prev => prev.filter(f => f !== file));
      toast.success('Archivo subido correctamente');
    } catch (error) {
      toast.error('Error al subir el archivo');
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
    }
  };

  const removeFile = (file: File) => {
    setSelectedFiles(prev => prev.filter(f => f !== file));
  };

  const clearAllFiles = () => {
    setSelectedFiles([]);
  };

  return (
    <div className="space-y-4">
      {selectedFiles.length === 0 ? (
        <div
          className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
            dragOver
              ? 'border-blue-400 bg-blue-50'
              : 'border-gray-300 hover:border-gray-400'
          }`}
          onDragOver={(e) => {
            e.preventDefault();
            setDragOver(true);
          }}
          onDragLeave={() => setDragOver(false)}
          onDrop={handleDrop}
        >
          <Upload className="h-12 w-12 text-gray-400 mx-auto mb-4" />
          <p className="text-sm text-gray-600 mb-2">
            Arrastra y suelta {multiple ? 'archivos' : 'un archivo'} aquí, o 
          </p>
          <label className="inline-block">
            <input
              type="file"
              className="hidden"
              accept={accept}
              multiple={multiple}
              onChange={handleFileChange}
              disabled={loading || isUploading}
            />
            <Button
              variant="outline"
              as="span"
              className="cursor-pointer"
              disabled={loading || isUploading}
            >
              Seleccionar {multiple ? 'archivos' : 'archivo'}
            </Button>
          </label>
          <p className="text-xs text-gray-500 mt-2">
            Tipos permitidos: {accept} • Máximo {formatFileSize(maxSize)}
            {multiple && ' por archivo'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-medium text-gray-900">
              {selectedFiles.length} archivo{selectedFiles.length > 1 ? 's' : ''} seleccionado{selectedFiles.length > 1 ? 's' : ''}
            </h4>
            {selectedFiles.length > 1 && (
              <Button
                variant="outline"
                size="sm"
                onClick={clearAllFiles}
                disabled={isUploading}
              >
                Limpiar todo
              </Button>
            )}
          </div>

          {isUploading && (
            <ProgressBar progress={uploadProgress} />
          )}

          <div className="space-y-2">
            {selectedFiles.map((file, index) => (
              <div key={index} className="border border-gray-200 rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-3">
                    <File className="h-8 w-8 text-blue-600" />
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {file.name}
                      </p>
                      <p className="text-sm text-gray-500">
                        {formatFileSize(file.size)}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => handleUpload(file)}
                      loading={isUploading}
                      disabled={loading}
                    >
                      Subir
                    </Button>
                    <button
                      onClick={() => removeFile(file)}
                      className="text-gray-400 hover:text-red-600 transition-colors"
                      disabled={isUploading || loading}
                    >
                      <X className="h-5 w-5" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div className="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
            <label className="cursor-pointer">
              <input
                type="file"
                className="hidden"
                accept={accept}
                multiple={multiple}
                onChange={handleFileChange}
                disabled={loading || isUploading}
              />
              <span className="text-sm text-gray-600">
                + Agregar más archivos
              </span>
            </label>
          </div>
        </div>
      )}

      {/* Error/Warning messages */}
      <div className="text-xs text-gray-500">
        <div className="flex items-center space-x-1">
          <AlertCircle className="h-3 w-3" />
          <span>
            Los archivos se suben individualmente. Tipos permitidos: {accept}
          </span>
        </div>
      </div>
    </div>
  );
};

export default FileUploader;