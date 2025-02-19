package j.m.services;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Paths;

import org.jsoup.Jsoup;

import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfReader;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.Paragraph;

public class PDFDocumentService {

    public static void crearDocumento(String nombreArchivo, String numero, String materia, String juzgado,
                                      String especialista, String tercero, String demandado,
                                      String demandante, String estadoActual) throws IOException {

        // Obtener ruta del archivo
        String filePath = System.getProperty("user.home") + "/Desktop/ExpedientesPDF/" + nombreArchivo + ".pdf";           

        try (PdfWriter writer = new PdfWriter(new FileOutputStream(filePath));
             PdfDocument pdf = new PdfDocument(writer);
             Document document = new Document(pdf)) {

            document.add(new Paragraph("EXPEDIENTE : " + numero).setBold());
            document.add(new Paragraph("MATERIA : " + materia));
            document.add(new Paragraph("JUEZ : " + juzgado));
            document.add(new Paragraph("ESPECIALISTA : " + especialista));
            document.add(new Paragraph("TERCERO : " + tercero));
            document.add(new Paragraph("DEMANDADO : " + demandado));
            document.add(new Paragraph("DEMANDANTE : " + demandante));
            document.add(new Paragraph("\n"));

            document.add(new Paragraph("Resolución Nº 1").setBold().setFontSize(14));
            document.add(new Paragraph(convertirHtmlATexto(estadoActual)).setFontSize(12));

            System.out.println("Documento PDF creado en: " + filePath);
        }
    }

    public static void agregarResolucionEnPDF(String filePath, String nuevaResolucion, int numeroResolucion) {
        String tempFilePath = filePath.replace(".pdf", "_temp.pdf");

        try {
            // Abrir documento PDF existente
            PdfDocument pdfDoc = new PdfDocument(new PdfReader(filePath), new PdfWriter(tempFilePath));
            Document document = new Document(pdfDoc);

            // Agregar nueva página para la resolución
            pdfDoc.addNewPage();
            document.add(new Paragraph("\n"));
            document.add(new Paragraph("Resolución Nº " + numeroResolucion).setBold().setFontSize(14));
            document.add(new Paragraph(convertirHtmlATexto(nuevaResolucion)).setFontSize(12));

            // Cerrar documentos
            document.close();
            pdfDoc.close();

            // Reemplazar el archivo original con el archivo temporal
            File tempFile = new File(tempFilePath);
            File originalFile = new File(filePath);
            if (originalFile.delete() && tempFile.renameTo(originalFile)) {
                System.out.println("Resolución añadida exitosamente al archivo PDF.");
            } else {
                System.out.println("Error al reemplazar el archivo PDF.");
            }
        } catch (IOException e) {
            e.printStackTrace();
            System.out.println("Error al agregar la resolución al PDF.");
        }
    }

    public static void actualizarPDFDesdeWord(String pdfPath, String nuevaResolucion, int numeroResolucion) throws IOException {
        // Ruta temporal para convertir el PDF a Word
        String wordTempPath = pdfPath.replace(".pdf", ".docx");

        // Convertir PDF a Word
        ArchivoService.convertirPDFaWord(pdfPath, wordTempPath);

        // Actualizar el archivo Word con la nueva resolución
        WordDocumentService.agregarResolucionEnWord(wordTempPath, nuevaResolucion, numeroResolucion);

        // Convertir el Word actualizado de nuevo a PDF
        
        // ArchivoService.convertirWordAPDF(wordTempPath, pdfPath);

        // Eliminar el archivo Word temporal
        Files.delete(Paths.get(wordTempPath));
    }

    private static String convertirHtmlATexto(String html) {
        return Jsoup.parse(html).text();
    }
}
