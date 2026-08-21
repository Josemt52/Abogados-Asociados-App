import axios from './axios';
import type { IConfig } from '@onlyoffice/document-editor-vue';

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
    master_pdf_rebuild_version?: number;
    master_pdf_rebuild_status?: 'ready' | 'pending' | 'failed';
    master_pdf_rebuild_error?: string | null;
    master_pdf_rebuild_requested_at?: string | null;
    master_pdf_rebuilt_at?: string | null;
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
    created_at: string;
    updated_at: string;
    completada_at?: string | null;
    onlyoffice_saved_at?: string | null;
}

export type OnlyOfficeDocumentType = 'expediente' | 'resolucion';
export type OnlyOfficeMode = 'edit' | 'view';

export interface OnlyOfficeDocumentMetadata {
    type: OnlyOfficeDocumentType;
    id: number;
    fileName: string;
}

export interface OnlyOfficeSessionLease {
    token: string;
    version: number;
    heartbeatIntervalSeconds: number;
}

export interface OnlyOfficeHeartbeat {
    active: true;
    version: number;
    expiresAt: string;
}

export interface OnlyOfficeEditorSession {
    documentServerUrl: string;
    config: IConfig;
    document: OnlyOfficeDocumentMetadata;
    editable: boolean;
    finalizable: boolean;
    session: OnlyOfficeSessionLease | null;
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

const readString = (record: Record<string, unknown>, ...keys: string[]): string | null => {
    for (const key of keys) {
        const value = record[key];

        if (typeof value === 'string' && value.trim()) {
            return value.trim();
        }
    }

    return null;
};

const readPositiveInteger = (value: unknown): number | null => {
    const parsed = typeof value === 'number' ? value : Number(value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
};

const normalizeOnlyOfficeSession = (
    payload: unknown,
    requestedType: OnlyOfficeDocumentType,
    requestedId: number,
): OnlyOfficeEditorSession => {
    if (!isRecord(payload)) {
        throw new Error('El servidor devolvió una configuración de edición inválida.');
    }

    const root = !('config' in payload) && isRecord(payload.data) ? payload.data : payload;
    const documentServerUrl = readString(
        root,
        'documentServerUrl',
        'document_server_url',
        'serverUrl',
    );
    const config = root.config;

    if (!documentServerUrl || !isRecord(config) || !isRecord(config.document)) {
        throw new Error('La configuración de ONLYOFFICE está incompleta.');
    }

    const configDocument = config.document;
    const requiredDocumentValues = ['fileType', 'key', 'title', 'url'];

    if (
        requiredDocumentValues.some(
            (key) => typeof configDocument[key] !== 'string' || !configDocument[key],
        )
    ) {
        throw new Error('La configuración del documento de ONLYOFFICE está incompleta.');
    }

    const document = isRecord(root.document)
        ? root.document
        : isRecord(root.metadata)
          ? root.metadata
          : isRecord(root.metadatos)
            ? root.metadatos
            : {};
    const responseType = readString(document, 'type', 'tipo');
    const normalizedType =
        responseType === 'expediente' || responseType === 'resolucion'
            ? responseType
            : requestedType;
    const documentId = readPositiveInteger(document.id) ?? requestedId;
    const fileName =
        readString(document, 'fileName', 'file_name', 'nombreArchivo', 'nombre_archivo') ??
        String(configDocument.title);
    const editorMode = isRecord(config.editorConfig)
        ? readString(config.editorConfig, 'mode')
        : null;
    const editable =
        typeof root.editable === 'boolean' ? root.editable : editorMode === 'edit';
    const finalizable = typeof root.finalizable === 'boolean' ? root.finalizable : false;
    const sessionPayload = root.session;
    let editingSession: OnlyOfficeSessionLease | null = null;

    if (sessionPayload !== null && sessionPayload !== undefined) {
        if (!isRecord(sessionPayload)) {
            throw new Error('La información de la sesión de edición es inválida.');
        }

        const token = readString(sessionPayload, 'token');
        const version = readPositiveInteger(sessionPayload.version);
        const heartbeatIntervalSeconds = readPositiveInteger(
            sessionPayload.heartbeatIntervalSeconds ?? sessionPayload.heartbeat_interval_seconds,
        );

        if (!token || version === null || heartbeatIntervalSeconds === null) {
            throw new Error('La información de la sesión de edición está incompleta.');
        }

        editingSession = { token, version, heartbeatIntervalSeconds };
    }

    return {
        documentServerUrl: documentServerUrl.replace(/\/+$/, ''),
        config: config as IConfig,
        document: {
            type: normalizedType,
            id: documentId,
            fileName,
        },
        editable,
        finalizable,
        session: editingSession,
    };
};

export const expedientesAPI = {
    async getAll(): Promise<Expediente[]> {
        const response = await axios.get('/expedientes');
        return response.data;
    },

    async getById(id: number, options: { silent?: boolean } = {}): Promise<Expediente> {
        const response = await axios.get(`/expedientes/${id}`, options);
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

    async getOnlyOfficeConfig(
        type: OnlyOfficeDocumentType,
        id: number,
        mode: OnlyOfficeMode = 'edit',
    ): Promise<OnlyOfficeEditorSession> {
        const response = await axios.get(`/onlyoffice/config/${type}/${id}`, {
            params: { mode },
        });

        return normalizeOnlyOfficeSession(response.data, type, id);
    },

    async heartbeatOnlyOfficeSession(
        type: OnlyOfficeDocumentType,
        id: number,
        token: string,
    ): Promise<OnlyOfficeHeartbeat> {
        const response = await axios.post(
            `/onlyoffice/session/${type}/${id}/heartbeat`,
            { token },
            { silent: true },
        );

        return response.data as OnlyOfficeHeartbeat;
    },

    async completarResolucionOnline(
        expedienteId: number,
        resolucionId: number,
    ): Promise<void> {
        await axios.post(
            `/expedientes/${expedienteId}/resoluciones/${resolucionId}/completar-online`,
        );
    },

    async retryMasterPdf(expedienteId: number): Promise<void> {
        await axios.post(`/expedientes/${expedienteId}/pdf-master/reintentar`);
    },
};
