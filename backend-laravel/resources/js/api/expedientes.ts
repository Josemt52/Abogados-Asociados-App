import axios from './axios';
import type { JSONContent } from '@tiptap/core';

export interface ArchivoMetadata {
    id: number;
    expediente_id: number;
    nombre_archivo: string;
    tipo_archivo: string;
}

export interface Expediente {
    id: number;
    numero: string;
    materia: string | null;
    juzgado: string | null;
    especialista: string | null;
    tercero: string | null;
    demandado: string | null;
    demandante: string | null;
    estado: string | null;
    archivo: boolean;
    nombre_archivo: string | null;
    archivo_data?: ArchivoMetadata | null;
    ultima_resolucion: number | null;
    resolucion_detectada?: number | null;
    created_at: string;
    updated_at: string;
}

export type ResolucionEstado = 'base' | 'pendiente' | 'completada';

export interface Resolucion {
    id: number;
    expediente_id: number;
    numero: number;
    estado: ResolucionEstado;
    es_documento_base: boolean;
    nombre_archivo: string | null;
    tipo_archivo?: string | null;
    version_editor?: number;
    contenido_editado_at?: string | null;
    created_at: string;
    updated_at: string;
    completada_at?: string | null;
}

export interface ResolutionEditorHeaderField {
    label: string;
    value: string;
}

export interface ResolutionEditorHeaderData {
    numero: string;
    materia: string;
    juzgado: string;
    especialista: string;
    tercero: string;
    demandado: string;
    demandante: string;
}

export interface ResolutionEditorPayload {
    expediente_id: number;
    resolucion_id: number;
    numero: number;
    estado: ResolucionEstado;
    document_name: string | null;
    header: ResolutionEditorHeaderField[];
    header_data: ResolutionEditorHeaderData;
    content: JSONContent;
    version: number;
    saved_at: string | null;
}

export interface ResolutionEditorCompletion {
    expediente: Expediente;
    resolucion: Resolucion;
}

export interface ResolucionesSnapshot {
    ultima_resolucion: number | null;
    resolucion_detectada: number | null;
    resoluciones: Resolucion[];
}

export interface PlantillaResolucion {
    blob: Blob;
    resolucionId: number;
    numero: number;
    filename: string;
}

export interface CreateExpedienteData {
    numero: string;
    materia?: string;
    juzgado?: string;
    especialista?: string;
    tercero?: string;
    demandado?: string;
    demandante?: string;
    estado?: string;
}

export type UpdateExpedienteData = Partial<CreateExpedienteData>;

const isNullableInteger = (value: unknown): value is number | null =>
    value === null || (typeof value === 'number' && Number.isInteger(value));

const isRecord = (value: unknown): value is Record<string, unknown> =>
    value !== null && typeof value === 'object' && !Array.isArray(value);

const isPositiveInteger = (value: unknown): value is number =>
    typeof value === 'number' && Number.isInteger(value) && value > 0;

const isResolutionState = (value: unknown): value is ResolucionEstado =>
    value === 'base' || value === 'pendiente' || value === 'completada';

const isNullableString = (value: unknown): value is string | null =>
    value === null || typeof value === 'string';

const parseResolutionEditorPayload = (value: unknown): ResolutionEditorPayload => {
    if (!isRecord(value)) {
        throw new Error('El servidor devolvió un documento de resolución inválido.');
    }

    const header = value.header;
    const headerData = value.header_data;
    const content = value.content;
    const validHeader =
        Array.isArray(header) &&
        header.every(
            (field) =>
                isRecord(field) &&
                typeof field.label === 'string' &&
                typeof field.value === 'string',
        );
    const validContent = isRecord(content) && content.type === 'doc';
    const validHeaderData =
        isRecord(headerData) &&
        [
            'numero',
            'materia',
            'juzgado',
            'especialista',
            'tercero',
            'demandado',
            'demandante',
        ].every((field) => typeof headerData[field] === 'string');

    if (
        !isPositiveInteger(value.expediente_id) ||
        !isPositiveInteger(value.resolucion_id) ||
        !isPositiveInteger(value.numero) ||
        !isResolutionState(value.estado) ||
        !isNullableString(value.document_name) ||
        !validHeader ||
        !validHeaderData ||
        !validContent ||
        typeof value.version !== 'number' ||
        !Number.isInteger(value.version) ||
        value.version < 0 ||
        !isNullableString(value.saved_at)
    ) {
        throw new Error('El servidor devolvió un documento de resolución inválido.');
    }

    return {
        expediente_id: value.expediente_id,
        resolucion_id: value.resolucion_id,
        numero: value.numero,
        estado: value.estado,
        document_name: value.document_name,
        header: header as ResolutionEditorHeaderField[],
        header_data: headerData as unknown as ResolutionEditorHeaderData,
        content: content as JSONContent,
        version: value.version,
        saved_at: value.saved_at,
    };
};

export const expedientesAPI = {
    async getAll(): Promise<Expediente[]> {
        const response = await axios.get('/expedientes');
        return response.data;
    },

    async getById(id: number): Promise<Expediente> {
        const response = await axios.get(`/expedientes/${id}`);
        return response.data;
    },

    async create(data: CreateExpedienteData): Promise<Expediente> {
        const response = await axios.post('/expedientes', data);
        return response.data;
    },

    async update(id: number, data: UpdateExpedienteData): Promise<Expediente> {
        const response = await axios.put(`/expedientes/${id}`, data);
        return response.data;
    },

    async delete(id: number): Promise<void> {
        await axios.delete(`/expedientes/${id}`);
    },

    async uploadFile(
        id: number,
        file: File,
        onProgress?: (progress: number) => void,
    ): Promise<Expediente> {
        const formData = new FormData();
        formData.append('file', file);

        const response = await axios.post(`/expedientes/${id}/archivo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: onProgress
                ? (event) => {
                    if (event.total) {
                        onProgress(Math.round((event.loaded * 100) / event.total));
                    }
                }
                : undefined,
        });

        return response.data;
    },

    async downloadFile(id: number): Promise<Blob> {
        const response = await axios.get(`/expedientes/${id}/archivo/download`, {
            responseType: 'blob',
        });
        return response.data;
    },

    async generateWord(id: number): Promise<Blob> {
        const response = await axios.get(`/expedientes/${id}/word`, {
            responseType: 'blob',
        });
        return response.data;
    },

    async generatePdf(id: number): Promise<Blob> {
        const response = await axios.get(`/expedientes/${id}/pdf`, {
            responseType: 'blob',
        });
        return response.data;
    },

    async getResoluciones(id: number): Promise<ResolucionesSnapshot> {
        const response = await axios.get(`/expedientes/${id}/resoluciones`);
        const data: unknown = response.data;

        if (!data || typeof data !== 'object') {
            throw new Error('El servidor devolvió un historial de resoluciones inválido.');
        }

        const snapshot = data as Record<string, unknown>;

        if (
            !isNullableInteger(snapshot.ultima_resolucion) ||
            !isNullableInteger(snapshot.resolucion_detectada) ||
            !Array.isArray(snapshot.resoluciones)
        ) {
            throw new Error('El servidor devolvió un historial de resoluciones inválido.');
        }

        return {
            ultima_resolucion: snapshot.ultima_resolucion,
            resolucion_detectada: snapshot.resolucion_detectada,
            resoluciones: snapshot.resoluciones as Resolucion[],
        };
    },

    async confirmarResolucionInicial(id: number, numero: number): Promise<void> {
        await axios.post(`/expedientes/${id}/resoluciones/confirmar-inicial`, { numero });
    },

    async generarSiguienteResolucion(id: number): Promise<PlantillaResolucion> {
        const response = await axios.post(`/expedientes/${id}/resoluciones/siguiente`, undefined, {
            responseType: 'blob',
        });
        const resolucionId = Number(response.headers['x-resolucion-id']);
        const numero = Number(response.headers['x-resolucion-numero']);

        if (!Number.isInteger(resolucionId) || !Number.isInteger(numero)) {
            throw new Error('El servidor no identificó la resolución generada.');
        }

        const contentDisposition = String(response.headers['content-disposition'] ?? '');
        const encodedFilename = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        const plainFilename = contentDisposition.match(/filename="?([^";]+)"?/i)?.[1];
        const filename = encodedFilename
            ? decodeURIComponent(encodedFilename)
            : plainFilename || `resolucion_${numero}.docx`;

        return { blob: response.data, resolucionId, numero, filename };
    },

    async iniciarEditorResolucion(expedienteId: number): Promise<ResolutionEditorPayload> {
        const response = await axios.post(
            `/expedientes/${expedienteId}/resoluciones/siguiente/editor`,
        );

        return parseResolutionEditorPayload(response.data);
    },

    async getEditorResolucion(
        expedienteId: number,
        resolucionId: number,
    ): Promise<ResolutionEditorPayload> {
        const response = await axios.get(
            `/expedientes/${expedienteId}/resoluciones/${resolucionId}/editor`,
        );

        return parseResolutionEditorPayload(response.data);
    },

    async guardarEditorResolucion(
        expedienteId: number,
        resolucionId: number,
        content: JSONContent,
        headerData: ResolutionEditorHeaderData,
        version: number,
    ): Promise<ResolutionEditorPayload> {
        const response = await axios.put(
            `/expedientes/${expedienteId}/resoluciones/${resolucionId}/editor`,
            { content, header_data: headerData, version },
        );

        return parseResolutionEditorPayload(response.data);
    },

    async finalizarEditorResolucion(
        expedienteId: number,
        resolucionId: number,
        version: number,
    ): Promise<ResolutionEditorCompletion> {
        const response = await axios.post(
            `/expedientes/${expedienteId}/resoluciones/${resolucionId}/finalizar-editor`,
            { version },
        );

        return response.data as ResolutionEditorCompletion;
    },

    async completarResolucion(
        expedienteId: number,
        resolucionId: number,
        file: File,
        onProgress?: (progress: number) => void,
    ): Promise<void> {
        const formData = new FormData();
        formData.append('file', file);

        await axios.post(
            `/expedientes/${expedienteId}/resoluciones/${resolucionId}/completar`,
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: onProgress
                    ? (event) => {
                        if (event.total) {
                            onProgress(Math.round((event.loaded * 100) / event.total));
                        }
                    }
                    : undefined,
            },
        );
    },

    async downloadResolucion(expedienteId: number, resolucionId: number): Promise<Blob> {
        const response = await axios.get(
            `/expedientes/${expedienteId}/resoluciones/${resolucionId}/download`,
            { responseType: 'blob' },
        );
        return response.data;
    },
};
