package j.m.services;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.nio.file.Paths;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

import org.apache.poi.xwpf.usermodel.XWPFDocument;
import org.apache.poi.xwpf.usermodel.XWPFParagraph;
import org.apache.poi.xwpf.usermodel.XWPFRun;

import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfReader;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.kernel.pdf.canvas.parser.PdfTextExtractor;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.Paragraph;

import j.m.utils.DBConnection;

public class ArchivoService {

    private static final String DESKTOP_PATH = System.getProperty("user.home") + "/Desktop";
    private static final String WORD_FOLDER = DESKTOP_PATH + "/ExpedientesWord";
    private static final String PDF_FOLDER = DESKTOP_PATH + "/ExpedientesPDF";

    static {
        new File(WORD_FOLDER).mkdirs();
        new File(PDF_FOLDER).mkdirs();
    }

    public void crearArchivo(String numeroExpediente, String tipoArchivo, String nombreArchivo,
                         String numero, String materia, String juzgado, String especialista,
                         String tercero, String demandado, String demandante, String estadoActual) throws IOException {
    String rutaArchivo;
    if (tipoArchivo.equals("WORD")) {
        rutaArchivo = Paths.get(WORD_FOLDER, nombreArchivo + ".docx").toString();
        WordDocumentService.crearDocumento(nombreArchivo, numero, materia, juzgado, especialista, tercero, demandado, demandante, estadoActual);
    } else {
        rutaArchivo = Paths.get(PDF_FOLDER, nombreArchivo + ".pdf").toString();
        PDFDocumentService.crearDocumento(nombreArchivo, numero, materia, juzgado, especialista, tercero, demandado, demandante, estadoActual);
    }

    // Registrar el archivo en la base de datos
    String query = "INSERT INTO archivos (expediente_numero, nombre_archivo, tipo_archivo) VALUES (?, ?, ?)";
    try (Connection connection = DBConnection.getConnection();
         PreparedStatement stmt = connection.prepareStatement(query)) {

        stmt.setString(1, numeroExpediente);
        stmt.setString(2, nombreArchivo);
        stmt.setString(3, tipoArchivo);
        stmt.executeUpdate();
        System.out.println("Archivo " + tipoArchivo + " creado y registrado: " + nombreArchivo);
    } catch (SQLException e) {
        e.printStackTrace();
    }
}


    public void agregarEstado(String numero, String nuevaResolucion) throws Exception {
        String queryArchivo = "SELECT nombre_archivo, tipo_archivo FROM archivos WHERE expediente_numero = ?";
        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(queryArchivo)) {

            stmt.setString(1, numero);
            ResultSet rs = stmt.executeQuery();

            if (!rs.next()) {
                throw new Exception("El archivo asociado al expediente no se encontró.");
            }
            ExpedienteService expedienteService = new ExpedienteService();
            int numeroResolucion = expedienteService.obtenerNumeroResolucion(numero);

            String nombreArchivo = rs.getString("nombre_archivo");
            String tipoArchivo = rs.getString("tipo_archivo");

            if (tipoArchivo.equalsIgnoreCase("WORD")) {
                WordDocumentService.agregarResolucionEnWord(Paths.get(WORD_FOLDER, nombreArchivo + ".docx").toString(), nuevaResolucion, numeroResolucion);
            } else if (tipoArchivo.equalsIgnoreCase("PDF")) {
                PDFDocumentService.agregarResolucionEnPDF(Paths.get(PDF_FOLDER, nombreArchivo + ".pdf").toString(), nuevaResolucion,numeroResolucion);
            }
        }
    }
    public String buscarTipoArchivo(String numeroExpediente) {
        String query = "SELECT tipo_archivo FROM archivos WHERE expediente_numero = ?";
        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {
    
            stmt.setString(1, numeroExpediente);
            ResultSet rs = stmt.executeQuery();
    
            if (rs.next()) {
                return rs.getString("tipo_archivo");
            } else {
                throw new Exception("No se encontró el tipo de archivo para el expediente: " + numeroExpediente);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        } catch (Exception e) {
            System.err.println(e.getMessage());
        }
        return null; // Retorna null si no encuentra el archivo
    }

    public String buscarArchivo(String numeroExpediente, String tipoArchivo) {
    String query = "SELECT nombre_archivo FROM archivos WHERE expediente_numero = ? AND tipo_archivo = ?";
    try (Connection connection = DBConnection.getConnection();
         PreparedStatement stmt = connection.prepareStatement(query)) {

        stmt.setString(1, numeroExpediente);
        stmt.setString(2, tipoArchivo);
        ResultSet rs = stmt.executeQuery();

        if (rs.next()) {
            return rs.getString("nombre_archivo");
        } else {
            throw new Exception("No se encontró un archivo del tipo " + tipoArchivo + " para el expediente: " + numeroExpediente);
        }
    } catch (SQLException e) {
        e.printStackTrace();
    } catch (Exception e) {
        System.err.println(e.getMessage());
    }
    return null; // Retorna null si no encuentra el archivo
}
public void convertirWordAPDF(String numeroExpediente) throws Exception { 
    // Buscar el nombre del archivo asociado al expediente
    String nombreArchivo = buscarArchivo(numeroExpediente, "WORD");
    if (nombreArchivo == null) {
        throw new Exception("No se encontró un archivo asociado al expediente: " + numeroExpediente);
    }

    // Construir las rutas del archivo Word y PDF
    String wordFilePath = Paths.get(System.getProperty("user.home"), "Desktop", "ExpedientesWord", nombreArchivo + ".docx").toString();
    String pdfFilePath = Paths.get(System.getProperty("user.home"), "Desktop", "ExpedientesPDF", nombreArchivo + ".pdf").toString();

    File wordFile = new File(wordFilePath);
    if (!wordFile.exists()) {
        throw new Exception("El archivo Word del expediente no existe: " + wordFilePath);
    }

    // Leer el archivo Word y crear el PDF
    try (FileInputStream fis = new FileInputStream(wordFile);
         XWPFDocument wordDocument = new XWPFDocument(fis);
         PdfWriter pdfWriter = new PdfWriter(new FileOutputStream(pdfFilePath));
         PdfDocument pdfDocument = new PdfDocument(pdfWriter);
         Document pdfLayoutDocument = new Document(pdfDocument)) {

        // Convertir cada párrafo del Word en un párrafo del PDF
        wordDocument.getParagraphs().forEach(paragraph -> {
            String text = paragraph.getText();
            if (!text.isEmpty()) {
                pdfLayoutDocument.add(new Paragraph(text));
            }
        });

        System.out.println("Expediente convertido exitosamente a PDF: " + pdfFilePath);
    }
}

    public static void convertirPDFaWord(String pdfPath, String wordPath) throws IOException {
        try (PdfDocument pdfDocument = new PdfDocument(new PdfReader(pdfPath));
             XWPFDocument wordDocument = new XWPFDocument()) {
    
            // Crear un archivo de salida para Word
            try (FileOutputStream fos = new FileOutputStream(wordPath)) {
                for (int i = 1; i <= pdfDocument.getNumberOfPages(); i++) {
                    // Extraer texto de cada página del PDF
                    String text = PdfTextExtractor.getTextFromPage(pdfDocument.getPage(i));
    
                    // Crear un párrafo en el archivo Word
                    XWPFParagraph paragraph = wordDocument.createParagraph();
                    XWPFRun run = paragraph.createRun();
                    run.setText(text);
                    run.addBreak();
                }
    
                // Escribir el contenido al archivo Word
                wordDocument.write(fos);
                System.out.println("PDF convertido a Word exitosamente: " + wordPath);
            }
        } catch (Exception e) {
            throw new IOException("Error al convertir PDF a Word: " + e.getMessage(), e);
        }
    }
    
}

    


