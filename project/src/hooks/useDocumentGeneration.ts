import { useState } from 'react';
import { expedientesAPI } from '../api';
import { downloadBlob } from '../utils/fileDownload';
import toast from 'react-hot-toast';

interface UseDocumentGenerationReturn {
  generateWord: (expedienteId: string, filename?: string) => Promise<void>;
  generatePDF: (expedienteId: string, filename?: string) => Promise<void>;
  addResolution: (expedienteId: string, data: { contenidoHtml: string; numeroResolucion: string }) => Promise<void>;
  isGenerating: boolean;
}

export const useDocumentGeneration = (): UseDocumentGenerationReturn => {
  const [isGenerating, setIsGenerating] = useState(false);

  const generateWord = async (expedienteId: string, filename?: string) => {
    setIsGenerating(true);
    try {
      const blob = await expedientesAPI.generateWord(expedienteId, filename);
      const downloadFilename = filename ? `${filename}.docx` : `expediente-${expedienteId}.docx`;
      downloadBlob(blob, downloadFilename);
      toast.success('Documento Word generado correctamente');
    } catch (error) {
      console.error('Error generating Word document:', error);
      toast.error('Error al generar el documento Word');
    } finally {
      setIsGenerating(false);
    }
  };

  const generatePDF = async (expedienteId: string, filename?: string) => {
    setIsGenerating(true);
    try {
      const blob = await expedientesAPI.generatePDF(expedienteId, filename);
      const downloadFilename = filename ? `${filename}.pdf` : `expediente-${expedienteId}.pdf`;
      downloadBlob(blob, downloadFilename);
      toast.success('PDF generado correctamente');
    } catch (error) {
      console.error('Error generating PDF:', error);
      toast.error('Error al generar el PDF');
    } finally {
      setIsGenerating(false);
    }
  };

  const addResolution = async (expedienteId: string, data: { contenidoHtml: string; numeroResolucion: string }) => {
    setIsGenerating(true);
    try {
      await expedientesAPI.addResolution(expedienteId, data);
      toast.success('Resolución añadida correctamente');
    } catch (error) {
      console.error('Error adding resolution:', error);
      toast.error('Error al añadir la resolución');
    } finally {
      setIsGenerating(false);
    }
  };

  return {
    generateWord,
    generatePDF,
    addResolution,
    isGenerating,
  };
};