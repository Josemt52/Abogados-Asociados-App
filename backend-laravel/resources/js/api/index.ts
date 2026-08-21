export { authAPI } from './auth';
export { contactoAPI } from './contacto';
export { estadisticasAPI } from './estadisticas';
export { expedientesAPI } from './expedientes';
export { usuariosAPI } from './usuarios';

export type { LoginCredentials, LoginResponse } from './auth';
export type { Contacto, CreateContactoData } from './contacto';
export type { Estadisticas, EstadisticasPorEstado, EstadisticasPorTipo } from './estadisticas';
export type {
    CreateExpedienteData,
    Expediente,
    OnlyOfficeDocumentMetadata,
    OnlyOfficeDocumentType,
    OnlyOfficeEditorSession,
    OnlyOfficeHeartbeat,
    OnlyOfficeMode,
    OnlyOfficeSessionLease,
    PlantillaResolucion,
    Resolucion,
    ResolucionEstado,
    ResolucionesSnapshot,
    UpdateExpedienteData,
} from './expedientes';
export type { CreateUsuarioData, UpdateUsuarioData, Usuario } from './usuarios';
