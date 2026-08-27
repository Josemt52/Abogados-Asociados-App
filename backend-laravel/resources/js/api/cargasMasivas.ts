import axios from './axios';

export interface CargaMasivaProgress {
    id: string;
    estado: 'cargando' | 'procesando' | 'completado';
    total: number;
    recibidos: number;
    procesados: number;
    progreso: number;
}

export interface CargaMasivaUploadSlot {
    id: number;
    nombre: string;
}

export interface CargaMasivaCreated extends CargaMasivaProgress {
    cargas: CargaMasivaUploadSlot[];
}

export const cargasMasivasAPI = {
    async create(files: File[]): Promise<CargaMasivaCreated> {
        const response = await axios.post('/cargas-masivas', {
            archivos: files.map((file) => ({
                nombre: file.name,
                tamano: file.size,
            })),
        });

        return response.data as CargaMasivaCreated;
    },

    async upload(
        batchId: string,
        itemId: number,
        file: File,
        onProgress?: (progress: number) => void,
    ): Promise<CargaMasivaProgress> {
        const data = new FormData();
        data.append('archivo', file);
        const response = await axios.post(
            `/cargas-masivas/${encodeURIComponent(batchId)}/items/${itemId}/archivo`,
            data,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: onProgress
                    ? (event) => {
                          if (event.total) {
                              onProgress(Math.round((event.loaded / event.total) * 100));
                          }
                      }
                    : undefined,
            },
        );

        return response.data as CargaMasivaProgress;
    },

    async getProgress(batchId: string): Promise<CargaMasivaProgress> {
        const response = await axios.get(`/cargas-masivas/${encodeURIComponent(batchId)}`);

        return response.data as CargaMasivaProgress;
    },
};
