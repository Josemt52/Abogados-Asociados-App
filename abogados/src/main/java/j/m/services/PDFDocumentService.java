package j.m.services;

import java.io.File;
import java.io.IOException;

import org.jsoup.Jsoup;

import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.Paragraph;
import com.itextpdf.layout.properties.TextAlignment;

import j.m.models.Expediente;

public class PDFDocumentService {

    // Define la ruta base para los PDFs
    private static final String PDF_FOLDER_PATH = System.getProperty("user.home") + "/Desktop/ExpedientesPDF";

    public static void crearDocumento(Expediente expediente) throws IOException {
        // Asegura que el directorio de salida exista
        new File(PDF_FOLDER_PATH).mkdirs();

        // Define el nombre del archivo de salida
        String filePath = PDF_FOLDER_PATH + "/" + expediente.getArchivo().getNombreArchivo() + ".pdf";

        try (PdfWriter writer = new PdfWriter(filePath);
             PdfDocument pdf = new PdfDocument(writer);
             Document document = new Document(pdf)) {

            // Añade los datos del expediente con formato
            document.add(new Paragraph("EXPEDIENTE : " + expediente.getNumero()).setBold());
            document.add(new Paragraph("MATERIA : " + expediente.getMateria()));
            document.add(new Paragraph("JUEZ : " + expediente.getJuzgado()));
            document.add(new Paragraph("ESPECIALISTA : " + expediente.getEspecialista()));
            document.add(new Paragraph("TERCERO : " + expediente.getTercero()));
            document.add(new Paragraph("DEMANDADO : " + expediente.getDemandado()));
            document.add(new Paragraph("DEMANDANTE : " + expediente.getDemandante()));
            
            // Añade un espacio
            document.add(new Paragraph("\n")); 

            // Añade el estado actual o la resolución
            document.add(new Paragraph("Estado Actual del Expediente")
                .setBold()
                .setTextAlignment(TextAlignment.CENTER));
                
            // Limpia cualquier posible HTML del campo 'estado' antes de añadirlo
            String estadoLimpio = convertirHtmlATexto(expediente.getEstado());
            document.add(new Paragraph(estadoLimpio));

            System.out.println("Documento PDF creado en: " + filePath);
        }
    }

    /**
     * Utiliza Jsoup para eliminar etiquetas HTML de una cadena de texto.
     * @param html El texto que puede contener HTML.
     * @return El texto plano sin etiquetas HTML.
     */
    private static String convertirHtmlATexto(String html) {
        if (html == null) {
            return "";
        }
        return Jsoup.parse(html).text();
    }
}