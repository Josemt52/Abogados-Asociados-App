package j.m.services;

import java.io.FileOutputStream;
import java.io.IOException;

import com.itextpdf.kernel.pdf.PdfDocument;
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

    private static String convertirHtmlATexto(String estadoActual) {
        // TODO Auto-generated method stub
        throw new UnsupportedOperationException("Unimplemented method 'convertirHtmlATexto'");
    }

    
}
