package j.m.services;

import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

import j.m.utils.DBConnection;

public class ExpedienteService {

    public  static void crearExpediente(String numero, String materia, String juzgado, String especialista,
                            String tercero, String demandado, String demandante,
                            String estadoActual, String tipoArchivo, String nombreArchivo) {
    // Validación de campos obligatorios
    if (numero == null || numero.trim().isEmpty()) {
        System.out.println("Error: El número de expediente no puede estar vacío.");
        return;
    }
    if (estadoActual == null || estadoActual.trim().isEmpty()) {
        System.out.println("Error: El estado inicial no puede estar vacío.");
        return;
    }

    String queryExpediente = "INSERT INTO expedientes (numero, materia, juzgado, especialista, tercero, demandado, demandante, estado_actual) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    String queryArchivo = "INSERT INTO archivos (expediente_numero, tipo_archivo, nombre_archivo) VALUES (?, ?, ?)";

    boolean datosGuardados = false;

    try (Connection connection = DBConnection.getConnection()) {
        // Iniciar transacción
        connection.setAutoCommit(false);

        try (PreparedStatement stmtExpediente = connection.prepareStatement(queryExpediente);
             PreparedStatement stmtArchivo = connection.prepareStatement(queryArchivo)) {

            // Insertar expediente
            stmtExpediente.setString(1, numero);
            stmtExpediente.setString(2, materia);
            stmtExpediente.setString(3, juzgado);
            stmtExpediente.setString(4, especialista);
            stmtExpediente.setString(5, tercero);
            stmtExpediente.setString(6, demandado);
            stmtExpediente.setString(7, demandante);
            stmtExpediente.setString(8, estadoActual);
            stmtExpediente.executeUpdate();

            // Registrar el archivo asociado al expediente
            stmtArchivo.setString(1, numero);
            stmtArchivo.setString(2, tipoArchivo);
            stmtArchivo.setString(3, nombreArchivo);
            stmtArchivo.executeUpdate();

            // Confirmar la transacción
            connection.commit();
            datosGuardados = true;
            System.out.println("Expediente creado exitosamente con estado inicial registrado.");

        } catch (SQLException e) {
            connection.rollback();
            System.out.println("Error al crear el expediente. No se realizaron cambios.");
            e.printStackTrace();
        } finally {
            connection.setAutoCommit(true);
        }
    } catch (SQLException e) {
        e.printStackTrace();
    }

    // Crear el archivo solo si los datos fueron guardados
    if (datosGuardados) {
        try {
            archivoService.crearArchivo(numero, tipoArchivo, nombreArchivo, numero, materia, juzgado, especialista, tercero, demandado, demandante, estadoActual);
                    } catch (Exception e) {
                        System.out.println("Error al crear el archivo asociado.");
                        e.printStackTrace();
                    }
                }
            }
            
                public List<String> verExpedientes() {
                    List<String> expedientes = new ArrayList<>();
                    String query = "SELECT * FROM expedientes";
            
                    try (Connection connection = DBConnection.getConnection();
                         PreparedStatement stmt = connection.prepareStatement(query);
                         ResultSet rs = stmt.executeQuery()) {
            
                        while (rs.next()) {
                            String expediente = "Número: " + rs.getString("numero") +
                                    ", Materia: " + rs.getString("materia") +
                                    ", Estado Actual: " + rs.getString("estado_actual");
                            expedientes.add(expediente);
                        }
                    } catch (SQLException e) {
                        e.printStackTrace();
                    }
                    return expedientes;
                }
                private static ArchivoService archivoService = new ArchivoService();
    
    // Método para agregar un estado con documentos actualizados
    public void agregarEstadoExpedienteConDocumentos(String numeroExpediente, String nuevaResolucion) { 
        try {
            // Guardar el nuevo estado en la base de datos
            agregarEstadoExpediente(numeroExpediente, nuevaResolucion);
            
            // Obtener el tipo de archivo (Word o PDF)
            String tipoArchivo = archivoService.buscarTipoArchivo(numeroExpediente);
            if (tipoArchivo == null) {
                throw new Exception("El tipo de archivo no está definido para el expediente: " + numeroExpediente);
            }
    
            // Obtener el nombre del archivo asociado
            String nombreArchivo = archivoService.buscarArchivo(numeroExpediente, tipoArchivo);
            if (nombreArchivo == null) {
                throw new Exception("No se encontró un archivo para el expediente: " + numeroExpediente);
            }
    
            // Construir la ruta del archivo
            Path rutaArchivo = Paths.get(System.getProperty("user.home"), "Desktop",
                    tipoArchivo.equalsIgnoreCase("WORD") ? "ExpedientesWord" : "ExpedientesPDF",
                    nombreArchivo + (tipoArchivo.equalsIgnoreCase("WORD") ? ".docx" : ".pdf"));
    
            // Agregar resolución al archivo correspondiente
            if ("WORD".equalsIgnoreCase(tipoArchivo)) {
                WordDocumentService.agregarResolucionEnWord(
                    rutaArchivo.toString(),
                    nuevaResolucion,
                    obtenerNumeroResolucion(numeroExpediente)
                );
            } else if ("PDF".equalsIgnoreCase(tipoArchivo)) {
                // Convertir el archivo PDF a Word, actualizarlo y reconvertirlo a PDF
                PDFDocumentService.actualizarPDFDesdeWord(rutaArchivo.toString(), nuevaResolucion, obtenerNumeroResolucion(numeroExpediente));
            } else {
                throw new Exception("Tipo de archivo no reconocido: " + tipoArchivo);
            }
    
            System.out.println("Resolución agregada exitosamente al expediente: " + numeroExpediente);
    
        } catch (Exception e) {
            // Si ocurre algún error, mostrar un mensaje y no proceder con la actualización del archivo
            System.out.println("Error al agregar la resolución al expediente. No se realizaron cambios.");
            e.printStackTrace();
        }
    }
    
    
    
    // Método para calcular el próximo número de resolución
    public int obtenerNumeroResolucion(String numeroExpediente) {
        String query = "SELECT IFNULL(MAX(numero_estado), 0) + 1 AS siguiente_resolucion " +
                       "FROM historico_estados WHERE expediente_id = (SELECT id FROM expedientes WHERE numero = ?)";
        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {
    
            stmt.setString(1, numeroExpediente);
            ResultSet rs = stmt.executeQuery();
            if (rs.next()) {
                return rs.getInt("siguiente_resolucion");
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return 1; // Retorna 1 si no hay estados previos
    }
    
    // Método para agregar un estado a la base de datos
    public void agregarEstadoExpediente(String numeroExpediente, String nuevoEstado) {
        if (numeroExpediente == null || numeroExpediente.trim().isEmpty()) {
            System.out.println("Error: El número de expediente no puede estar vacío.");
            return;
        }
        if (nuevoEstado == null || nuevoEstado.trim().isEmpty()) {
            System.out.println("Error: El estado no puede estar vacío. No se realizó ningún cambio.");
            return;
        }
    
        // SQL para verificar que el expediente existe y tiene un estado actual
        String queryVerificarExpediente = "SELECT id, estado_actual FROM expedientes WHERE numero = ?";
        String queryMoverEstado = "INSERT INTO historico_estados (expediente_id, estado, numero_estado) " +
                                  "SELECT id, estado_actual, " +
                                  "(SELECT IFNULL(MAX(numero_estado), 0) + 1 FROM historico_estados WHERE expediente_id = expedientes.id) " +
                                  "FROM expedientes WHERE numero = ?";
        String queryActualizarEstado = "UPDATE expedientes SET estado_actual = ? WHERE numero = ?";
    
        try (Connection connection = DBConnection.getConnection()) {
            connection.setAutoCommit(false); // Iniciar transacción
    
            try (PreparedStatement stmtVerificarExpediente = connection.prepareStatement(queryVerificarExpediente)) {
                // Verificar que el expediente existe y tiene un estado actual
                stmtVerificarExpediente.setString(1, numeroExpediente);
                ResultSet rs = stmtVerificarExpediente.executeQuery();
    
                if (!rs.next()) {
                    throw new SQLException("Error: El número de expediente no existe o no tiene un estado actual.");
                }
    
                int expedienteId = rs.getInt("id");
                String estadoActual = rs.getString("estado_actual");
    
                if (estadoActual == null || estadoActual.trim().isEmpty()) {
                    throw new SQLException("Error: El expediente no tiene un estado actual definido.");
                }
    
                // Mover el estado actual al histórico
                try (PreparedStatement stmtMoverEstado = connection.prepareStatement(queryMoverEstado)) {
                    stmtMoverEstado.setString(1, numeroExpediente);
                    int filasInsertadas = stmtMoverEstado.executeUpdate();
    
                    if (filasInsertadas == 0) {
                        throw new SQLException("Error: No se pudo mover el estado actual al histórico.");
                    }
                }
    
                // Actualizar el nuevo estado como estado actual
                try (PreparedStatement stmtActualizarEstado = connection.prepareStatement(queryActualizarEstado)) {
                    stmtActualizarEstado.setString(1, nuevoEstado);
                    stmtActualizarEstado.setString(2, numeroExpediente);
                    int filasActualizadas = stmtActualizarEstado.executeUpdate();
    
                    if (filasActualizadas == 0) {
                        throw new SQLException("Error: No se pudo actualizar el estado actual del expediente.");
                    }
                }
    
                connection.commit(); // Confirmar la transacción
                System.out.println("Estado actualizado exitosamente en el expediente: " + numeroExpediente);
    
            } catch (SQLException e) {
                connection.rollback(); // Revertir los cambios en caso de error
                throw new SQLException("Error al mover el estado al histórico o actualizar el estado actual.", e);
            } finally {
                connection.setAutoCommit(true); // Restaurar auto-commit
            }
        } catch (SQLException e) {
            e.printStackTrace();
            throw new RuntimeException("Error al actualizar el estado en la base de datos.", e);
        }
    }
    
    
    // Método para obtener todos los estados de un expediente con numeración
    public List<String> obtenerEstadosConNumeracion(String numeroExpediente) {
        List<String> estados = new ArrayList<>();
        String query = "SELECT numero_estado, estado FROM historico_estados " +
                       "WHERE expediente_id = (SELECT id FROM expedientes WHERE numero = ?) " +
                       "ORDER BY numero_estado";

        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {

            stmt.setString(1, numeroExpediente);
            ResultSet rs = stmt.executeQuery();

            while (rs.next()) {
                int numeroEstado = rs.getInt("numero_estado");
                String estado = rs.getString("estado");
                estados.add("Resolución Nº " + numeroEstado + ": " + estado);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return estados;
    }
    public List<String> buscarExpedientes(String numero, String materia, String juzgado, String especialista) {
        List<String> resultados = new ArrayList<>();
        String query = "SELECT * FROM expedientes WHERE "
                     + "(? IS NULL OR numero LIKE ?) AND "
                     + "(? IS NULL OR materia LIKE ?) AND "
                     + "(? IS NULL OR juzgado LIKE ?) AND "
                     + "(? IS NULL OR especialista LIKE ?)";
    
        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {
    
            // Asignar valores a los parámetros (manejar nulos)
            stmt.setString(1, numero);
            stmt.setString(2, numero != null ? "%" + numero + "%" : null);
            stmt.setString(3, materia);
            stmt.setString(4, materia != null ? "%" + materia + "%" : null);
            stmt.setString(5, juzgado);
            stmt.setString(6, juzgado != null ? "%" + juzgado + "%" : null);
            stmt.setString(7, especialista);
            stmt.setString(8, especialista != null ? "%" + especialista + "%" : null);
    
            // Ejecutar la consulta
            try (ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {
                    String resultado = "Número: " + rs.getString("numero")
                                     + ", Materia: " + rs.getString("materia")
                                     + ", Juzgado: " + rs.getString("juzgado")
                                     + ", Especialista: " + rs.getString("especialista")
                                     + ", Estado Actual: " + rs.getString("estado_actual");
                    resultados.add(resultado);
                }
            }
    
        } catch (SQLException e) {
            e.printStackTrace();
            System.out.println("Error al realizar la búsqueda.");
        }
    
        return resultados;
    }
    public boolean eliminarExpediente(String numeroExpediente) {
        String queryEliminarArchivo = "DELETE FROM archivos WHERE expediente_numero = ?";
        String queryEliminarHistorico = "DELETE FROM historico_estados WHERE expediente_id = (SELECT id FROM expedientes WHERE numero = ?)";
        String queryEliminarExpediente = "DELETE FROM expedientes WHERE numero = ?";
    
        try (Connection connection = DBConnection.getConnection()) {
            connection.setAutoCommit(false); // Iniciar transacción
    
            try (PreparedStatement stmtEliminarArchivo = connection.prepareStatement(queryEliminarArchivo);
                 PreparedStatement stmtEliminarHistorico = connection.prepareStatement(queryEliminarHistorico);
                 PreparedStatement stmtEliminarExpediente = connection.prepareStatement(queryEliminarExpediente)) {
    
                // Eliminar archivos asociados
                stmtEliminarArchivo.setString(1, numeroExpediente);
                stmtEliminarArchivo.executeUpdate();
    
                // Eliminar estados históricos asociados
                stmtEliminarHistorico.setString(1, numeroExpediente);
                stmtEliminarHistorico.executeUpdate();
    
                // Eliminar el expediente principal
                stmtEliminarExpediente.setString(1, numeroExpediente);
                int filasEliminadas = stmtEliminarExpediente.executeUpdate();
    
                if (filasEliminadas == 0) {
                    throw new SQLException("No se encontró el expediente a eliminar.");
                }
    
                connection.commit();
                System.out.println("Expediente eliminado correctamente.");
                return true;
    
            } catch (SQLException e) {
                connection.rollback(); // Revertir cambios si ocurre un error
                System.out.println("Error al eliminar el expediente. No se realizaron cambios.");
                e.printStackTrace();
                return false;
            }
        } catch (SQLException e) {
            e.printStackTrace();
            return false;
        }
    }
    
}
