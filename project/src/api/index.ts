// Export all API modules
export { authAPI } from './auth';
export { expedientesAPI } from './expedientes';
export { usuariosAPI } from './usuarios';
export { contactoAPI } from './contacto';
export { estadisticasAPI } from './estadisticas';

// Export types
export type { LoginCredentials, LoginResponse } from './auth';
export type { Expediente, CreateExpedienteData, UpdateExpedienteData } from './expedientes';
export type { Usuario, CreateUsuarioData, UpdateUsuarioData } from './usuarios';
export type { Contacto, CreateContactoData } from './contacto';
export type { Estadisticas, EstadisticasPorEstado, EstadisticasPorTipo } from './estadisticas';
