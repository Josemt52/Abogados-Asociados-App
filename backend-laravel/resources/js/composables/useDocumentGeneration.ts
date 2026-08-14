import { ref } from 'vue';
import { expedientesAPI } from '@/api';
import { downloadBlob } from '@/utils/fileDownload';
import { useToast } from './useToast';

export const useDocumentGeneration = () => {
    const isGenerating = ref(false);
    const toast = useToast();

    const generateWord = async (expedienteId: string | number, numeroExpediente?: string): Promise<void> => {
        try {
            isGenerating.value = true;
            const blob = await expedientesAPI.generateWord(Number(expedienteId));
            downloadBlob(blob, `expediente_${numeroExpediente || expedienteId}.docx`);
            toast.success('Documento Word generado correctamente.');
        } catch (error) {
            console.error(error);
            toast.error('Error al generar el documento Word.');
        } finally {
            isGenerating.value = false;
        }
    };

    const generatePDF = async (expedienteId: string | number, numeroExpediente?: string): Promise<void> => {
        try {
            isGenerating.value = true;
            const blob = await expedientesAPI.generatePdf(Number(expedienteId));
            downloadBlob(blob, `expediente_${numeroExpediente || expedienteId}.pdf`);
            toast.success('Documento PDF generado correctamente.');
        } catch (error) {
            console.error(error);
            toast.error('Error al generar el documento PDF.');
        } finally {
            isGenerating.value = false;
        }
    };

    return { generateWord, generatePDF, isGenerating };
};
