import { useState } from 'react';
import { expedientesAPI } from '../api';
import toast from 'react-hot-toast';

export const useDocumentGeneration = () => {
  const [isGenerating, setIsGenerating] = useState(false);

  const downloadBlob = (blob: Blob, filename: string) => {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  const generateWord = async (expedienteId: string, numeroExpediente?: string) => {
    try {
      setIsGenerating(true);
      const blob = await expedientesAPI.generateWord(Number(expedienteId));
      const filename = `expediente_${numeroExpediente || expedienteId}.docx`;
      downloadBlob(blob, filename);
      toast.success('Documento Word generado correctamente');
    } catch (error) {
      toast.error('Error al generar documento Word');
      console.error(error);
    } finally {
      setIsGenerating(false);
    }
  };

  const generatePDF = async (expedienteId: string, numeroExpediente?: string) => {
    try {
      setIsGenerating(true);
      const blob = await expedientesAPI.generatePdf(Number(expedienteId));
      const filename = `expediente_${numeroExpediente || expedienteId}.pdf`;
      downloadBlob(blob, filename);
      toast.success('Documento PDF generado correctamente');
    } catch (error) {
      toast.error('Error al generar documento PDF');
      console.error(error);
    } finally {
      setIsGenerating(false);
    }
  };

  return {
    generateWord,
    generatePDF,
    isGenerating,
  };
};
