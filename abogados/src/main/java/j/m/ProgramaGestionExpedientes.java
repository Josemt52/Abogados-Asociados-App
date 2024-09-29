package j.m;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import java.util.Scanner;

import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.text.PDFTextStripper;
import org.apache.poi.xwpf.usermodel.XWPFDocument;
import org.apache.poi.xwpf.usermodel.XWPFParagraph;

import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.Paragraph;

public class ProgramaGestionExpedientes {

    // Ruta donde se guardarán los archivos PDF
    private static final String RUTA_PDFS = "C:\\Users\\jose\\Desktop\\guardar pdfs\\";
    private static final String RUTA_WORDS = "C:\\Users\\jose\\Desktop\\guardar words\\";
    private static Scanner scanner = new Scanner(System.in);

    public static void main(String[] args) {
        ejecutarGestionExpedientes();
    }

    public static void ejecutarGestionExpedientes() {
        while (true) {
            System.out.println("1. Crear Expediente");
            System.out.println("2. Ver Expedientes");
            System.out.println("3. Actualizar Expedientes");
            System.out.println("4. Buscar Expedientes");
            System.out.println("5. Salir");

            System.out.print("Seleccione una opción: ");
            int opcion = scanner.nextInt();
            scanner.nextLine();

            switch (opcion) {
                case 1:
                    crearExpediente();
                    break;
                case 2:
                    verExpedientes();
                    break;
                case 3:
                    actualizarExpedientes();
                    break;
                case 4:
                    buscarExpedientes();
                    break;
                case 5:
                    System.out.println("Saliendo del programa de gestión de expedientes. ¡Hasta luego!");
                    System.exit(0);
                default:
                    System.out.println("Opción no válida. Inténtelo de nuevo.");
            }
        }
    }

    private static void crearExpediente() {
        Expediente nuevoExpediente = Expediente.crearExpediente();
        Scanner scanner = new Scanner(System.in);
    
        System.out.println("¿Desea guardar el expediente en PDF o Word? (pdf/word): ");
        String formato = scanner.nextLine().trim().toLowerCase();
    
        try (Connection connection = DBConnection.getConnection()) {
            // Insertar materia
            int materiaId = obtenerIdOInsertarMateria(connection, nuevoExpediente.getMateria().getNombre());
            
            // Insertar o verificar personas (juez, especialista, etc.)
            int juezId = obtenerIdOInsertarPersona(connection, nuevoExpediente.getJuez().getNombre());
            int especialistaId = obtenerIdOInsertarPersona(connection, nuevoExpediente.getEspecialista().getNombre());
            int terceroId = obtenerIdOInsertarPersona(connection, nuevoExpediente.getTercero().getNombre());
            int demandadoId = obtenerIdOInsertarPersona(connection, nuevoExpediente.getDemandado().getNombre());
            int demandanteId = obtenerIdOInsertarPersona(connection, nuevoExpediente.getDemandante().getNombre());
    
            // Insertar estado
            int estadoId = obtenerIdOInsertarEstado(connection, nuevoExpediente.getEstado().getDescripcion());
    
            // Insertar el expediente con las IDs obtenidas
            String sql = "INSERT INTO expedientes (numero, materia_id, juez_id, especialista_id, tercero_id, demandado_id, demandante_id, estado_id) VALUES (?,?,?,?,?,?,?,?)";
            try (PreparedStatement statement = connection.prepareStatement(sql)) {
                statement.setString(1, nuevoExpediente.getNumero());
                statement.setInt(2, materiaId);
                statement.setInt(3, juezId);
                statement.setInt(4, especialistaId);
                statement.setInt(5, terceroId);
                statement.setInt(6, demandadoId);
                statement.setInt(7, demandanteId);
                statement.setInt(8, estadoId);
                statement.executeUpdate();
                System.out.println("Expediente creado correctamente");
    
                // Llamar al método para guardar en PDF o Word
                if ("pdf".equals(formato)) {
                    guardarExpedienteEnPDF(nuevoExpediente);
                } else if ("word".equals(formato)) {
                    guardarExpedienteEnWord(nuevoExpediente);
                } else {
                    System.out.println("Formato no reconocido. El expediente no se guardó.");
                }
            } catch (SQLException e) {
                System.err.println("Error al ejecutar la consulta SQL:");
                e.printStackTrace();
            }
        } catch (Exception e) {
            System.err.println("Error al conectar con la base de datos o al cerrar la conexión:");
            e.printStackTrace();
        }
    }
    
    private static void guardarExpedienteEnPDF(Expediente expediente) {
        String pdfPath = RUTA_PDFS + expediente.getNumero() + ".pdf";
    
        // Crear el directorio si no existe
        File directory = new File(RUTA_PDFS);
        if (!directory.exists()) {
            if (!directory.mkdirs()) {
                System.err.println("No se pudo crear el directorio para guardar los PDFs.");
                return;
            }
        }
    
        try (PdfWriter writer = new PdfWriter(new FileOutputStream(pdfPath));
             PdfDocument pdf = new PdfDocument(writer);
             Document document = new Document(pdf)) {
    
            // Agregar contenido al PDF
            document.add(new Paragraph("EXPEDIENTE: " + expediente.getNumero()));
            document.add(new Paragraph("MATERIA: " + expediente.getMateria().getNombre()));  // Cambiado aquí
            document.add(new Paragraph("JUZGADO: " + expediente.getJuez().getNombre()));     // Cambiado aquí
            document.add(new Paragraph("ESPECIALISTA: " + expediente.getEspecialista().getNombre())); // Cambiado aquí
            document.add(new Paragraph("TERCERO: " + expediente.getTercero().getNombre()));  // Cambiado aquí
            document.add(new Paragraph("DEMANDADO: " + expediente.getDemandado().getNombre())); // Cambiado aquí
            document.add(new Paragraph("DEMANDANTE: " + expediente.getDemandante().getNombre())); // Cambiado aquí
            document.add(new Paragraph("ESTADO DEL EXPEDIENTE: " + expediente.getEstado().getDescripcion()));  // Cambiado aquí
    
            System.out.println("Expediente guardado correctamente en: " + pdfPath);
    
        } catch (FileNotFoundException e) {
            System.err.println("Error: Archivo no encontrado al intentar guardar el PDF.");
            e.printStackTrace();
        } catch (IOException e) {
            System.err.println("Error al intentar guardar el PDF:");
            e.printStackTrace();
        } catch (Exception e) {
            System.err.println("Error al intentar guardar el PDF:");
            e.printStackTrace();
        }
    }
    
    private static void guardarExpedienteEnWord(Expediente expediente) {
        String wordPath = RUTA_WORDS + expediente.getNumero() + ".docx";
    
        // Crear el directorio si no existe
        File directory = new File(RUTA_WORDS);
        if (!directory.exists()) {
            if (!directory.mkdirs()) {
                System.err.println("No se pudo crear el directorio para guardar los documentos Word.");
                return;
            }
        }
    
        try (XWPFDocument document = new XWPFDocument()) {
            XWPFParagraph para = document.createParagraph();
            para.createRun().setText("EXPEDIENTE: " + expediente.getNumero());
            para.createRun().addBreak();
            para.createRun().setText("MATERIA: " + expediente.getMateria().getNombre());  // Cambiado aquí
            para.createRun().addBreak();
            para.createRun().setText("JUZGADO: " + expediente.getJuez().getNombre());    // Cambiado aquí
            para.createRun().addBreak();
            para.createRun().setText("ESPECIALISTA: " + expediente.getEspecialista().getNombre()); // Cambiado aquí
            para.createRun().addBreak();
            para.createRun().setText("TERCERO: " + expediente.getTercero().getNombre());  // Cambiado aquí
            para.createRun().addBreak();
            para.createRun().setText("DEMANDADO: " + expediente.getDemandado().getNombre()); // Cambiado aquí
            para.createRun().addBreak();
            para.createRun().setText("DEMANDANTE: " + expediente.getDemandante().getNombre()); // Cambiado aquí
            para.createRun().addBreak();
            para.createRun().setText("ESTADO DEL EXPEDIENTE: " + expediente.getEstado().getDescripcion());  // Cambiado aquí
    
            try (FileOutputStream fos = new FileOutputStream(wordPath)) {
                document.write(fos);
            }
    
            System.out.println("Expediente guardado correctamente en: " + wordPath);
    
        } catch (IOException e) {
            System.err.println("Error al intentar guardar el documento Word:");
            e.printStackTrace();
        }
    }
    

    private static void verExpedientes() {
        String sql = "SELECT e.numero, m.nombre AS materia, p1.nombre AS juez, p2.nombre AS especialista, " +
                     "p3.nombre AS tercero, p4.nombre AS demandado, p5.nombre AS demandante, es.descripcion AS estado " +
                     "FROM expedientes e " +
                     "JOIN materias m ON e.materia_id = m.id " +
                     "JOIN personas p1 ON e.juez_id = p1.id " +
                     "JOIN personas p2 ON e.especialista_id = p2.id " +
                     "JOIN personas p3 ON e.tercero_id = p3.id " +
                     "JOIN personas p4 ON e.demandado_id = p4.id " +
                     "JOIN personas p5 ON e.demandante_id = p5.id " +
                     "JOIN estados es ON e.estado_id = es.id";
    
        try (Connection connection = DBConnection.getConnection();
             Statement statement = connection.createStatement();
             ResultSet resultSet = statement.executeQuery(sql)) {
    
            List<Expediente> expedientes = new ArrayList<>();
            while (resultSet.next()) {
                String numero = resultSet.getString("numero");
                Materia materia = new Materia(resultSet.getString("materia"));
                Persona juez = new Persona(resultSet.getString("juez"));
                Persona especialista = new Persona(resultSet.getString("especialista"));
                Persona tercero = new Persona(resultSet.getString("tercero"));
                Persona demandado = new Persona(resultSet.getString("demandado"));
                Persona demandante = new Persona(resultSet.getString("demandante"));
                Estado estado = new Estado(resultSet.getString("estado"));
    
                Expediente expediente = new Expediente(numero, materia, juez, especialista, tercero, demandado, demandante, estado);
                expedientes.add(expediente);
            }
    
            for (Expediente expediente : expedientes) {
                expediente.mostrarExpediente();
                System.out.println("-------------------");
            }
        } catch (SQLException e) {
            System.err.println("Error al ejecutar la consulta SQL:");
            e.printStackTrace();
        }
    }
    

    private static void actualizarExpedientes() {
        Scanner scanner = new Scanner(System.in);

        System.out.print("Ingrese el número del expediente a actualizar: ");
        String numero = scanner.nextLine();

        System.out.print("Ingrese la nueva actualización: ");
        String nuevaActualizacion = scanner.nextLine();

        try (Connection connection = DBConnection.getConnection()) {
            String sql = "UPDATE expedientes SET estado = ? WHERE numero = ?";
            try (PreparedStatement statement = connection.prepareStatement(sql)) {
                statement.setString(1, nuevaActualizacion);
                statement.setString(2, numero);
                int rowsUpdated = statement.executeUpdate();
                if (rowsUpdated > 0) {
                    System.out.println("Expediente actualizado correctamente.");

                    // Abrir el archivo PDF en Word para edición manual
                    abrirEnWord(numero);

                } else {
                    System.out.println("Expediente no encontrado.");
                }
            }
        } catch (SQLException e) {
            System.err.println("Error al ejecutar la consulta SQL:");
            e.printStackTrace();
        }
    }

    private static void abrirEnWord(String numeroExpediente) {
        String pdfPath = RUTA_PDFS + numeroExpediente + ".pdf";
        File file = new File(pdfPath);

        if (!file.exists()) {
            System.out.println("El archivo PDF del expediente no existe.");
            return;
        }

        try {
            // Abre el archivo PDF con la aplicación predeterminada (Word en caso de archivos DOCX)
            Runtime.getRuntime().exec(new String[]{"cmd", "/c", "start", file.getAbsolutePath()});
            System.out.println("El archivo se ha abierto en Word para su edición.");
        } catch (IOException e) {
            System.err.println("Error al intentar abrir el archivo en Word:");
            e.printStackTrace();
        }
    }

    private static void buscarExpedientes() {
        System.out.print("Ingrese el número de expediente: ");
        String numeroExpediente = scanner.nextLine();

        File folder = new File(RUTA_PDFS);
        File[] listOfFiles = folder.listFiles((dir, name) -> name.endsWith(".pdf"));

        boolean encontrado = false;
        for (File file : listOfFiles) {
            if (buscarEnPDF(file, numeroExpediente)) {
                System.out.println("Expediente encontrado en: " + file.getName());
                encontrado = true;
            }
        }

        if (!encontrado) {
            System.out.println("Expediente no encontrado en la carpeta de PDFs.");
        }
    }

    private static boolean buscarEnPDF(File file, String numeroExpediente) {
        try (PDDocument document = PDDocument.load(file)) {
            if (!document.isEncrypted()) {
                PDFTextStripper stripper = new PDFTextStripper();
                String text = stripper.getText(document);
                return text.contains("EXPEDIENTE: " + numeroExpediente);
            } else {
                System.err.println("El documento PDF está encriptado y no se puede procesar.");
                return false;
            }
            
        } catch (IOException e) {
            System.err.println("Error al cargar el documento PDF:");
            e.printStackTrace();
            return false;
        }
    }
    private static void buscarEnWord(String numeroExpediente) {
        File folder = new File(RUTA_WORDS);
        File[] listOfFiles = folder.listFiles((dir, name) -> name.endsWith(".docx"));

        boolean encontrado = false;
        for (File file : listOfFiles) {
            if (buscarEnWordFile(file, numeroExpediente)) {
                System.out.println("Expediente encontrado en: " + file.getName());
                encontrado = true;
            }
        }

        if (!encontrado) {
            System.out.println("Expediente no encontrado en la carpeta de documentos Word.");
        }
    }

    private static boolean buscarEnWordFile(File file, String numeroExpediente) {
        try (FileInputStream fis = new FileInputStream(file);
             XWPFDocument document = new XWPFDocument(fis)) {

            for (XWPFParagraph paragraph : document.getParagraphs()) {
                String text = paragraph.getText();
                if (text.contains("EXPEDIENTE: " + numeroExpediente)) {
                    return true;
                }
            }

            return false;
        } catch (IOException e) {
            System.err.println("Error al cargar el documento Word:");
            e.printStackTrace();
            return false;
        }
    }
    private static int obtenerIdOInsertarPersona(Connection connection, String nombre) throws SQLException {
        String selectSql = "SELECT id FROM personas WHERE nombre = ?";
        try (PreparedStatement selectStmt = connection.prepareStatement(selectSql)) {
            selectStmt.setString(1, nombre);
            try (ResultSet rs = selectStmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("id"); // Si la persona existe, devolver su ID
                }
            }
        }
    
        // Si la persona no existe, insertarla y devolver el nuevo ID
        String insertSql = "INSERT INTO personas (nombre) VALUES (?)";
        try (PreparedStatement insertStmt = connection.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
            insertStmt.setString(1, nombre);
            insertStmt.executeUpdate();
            try (ResultSet generatedKeys = insertStmt.getGeneratedKeys()) {
                if (generatedKeys.next()) {
                    return generatedKeys.getInt(1); // Retornar el nuevo ID
                }
            }
        }
        throw new SQLException("No se pudo obtener el ID para la persona: " + nombre);
    }
    
    private static int obtenerIdOInsertarMateria(Connection connection, String nombre) throws SQLException {
        String selectSql = "SELECT id FROM materias WHERE nombre = ?";
        try (PreparedStatement selectStmt = connection.prepareStatement(selectSql)) {
            selectStmt.setString(1, nombre);
            try (ResultSet rs = selectStmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("id"); // Si la materia existe, devolver su ID
                }
            }
        }
    
        // Si la materia no existe, insertarla y devolver el nuevo ID
        String insertSql = "INSERT INTO materias (nombre) VALUES (?)";
        try (PreparedStatement insertStmt = connection.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
            insertStmt.setString(1, nombre);
            insertStmt.executeUpdate();
            try (ResultSet generatedKeys = insertStmt.getGeneratedKeys()) {
                if (generatedKeys.next()) {
                    return generatedKeys.getInt(1); // Retornar el nuevo ID
                }
            }
        }
        throw new SQLException("No se pudo obtener el ID para la materia: " + nombre);
    }
    
    private static int obtenerIdOInsertarEstado(Connection connection, String descripcion) throws SQLException {
        String selectSql = "SELECT id FROM estados WHERE descripcion = ?";
        try (PreparedStatement selectStmt = connection.prepareStatement(selectSql)) {
            selectStmt.setString(1, descripcion);
            try (ResultSet rs = selectStmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("id"); // Si el estado existe, devolver su ID
                }
            }
        }
    
        // Si el estado no existe, insertarlo y devolver el nuevo ID
        String insertSql = "INSERT INTO estados (descripcion) VALUES (?)";
        try (PreparedStatement insertStmt = connection.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
            insertStmt.setString(1, descripcion);
            insertStmt.executeUpdate();
            try (ResultSet generatedKeys = insertStmt.getGeneratedKeys()) {
                if (generatedKeys.next()) {
                    return generatedKeys.getInt(1); // Retornar el nuevo ID
                }
            }
        }
        throw new SQLException("No se pudo obtener el ID para el estado: " + descripcion);
    }
    
}