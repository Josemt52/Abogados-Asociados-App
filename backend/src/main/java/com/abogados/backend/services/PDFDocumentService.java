package com.abogados.backend.services;

import com.abogados.backend.models.Expediente;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.PDPage;
import org.apache.pdfbox.pdmodel.PDPageContentStream;
import org.apache.pdfbox.pdmodel.font.PDType1Font;
import org.jsoup.Jsoup;

import java.io.File;
import java.io.IOException;

public class PDFDocumentService {

    public static File crearDocumento(Expediente expediente, String nombreArchivo) throws IOException {
        String outDir = System.getProperty("user.home") + File.separator + "ExpedientesPDF";
        new File(outDir).mkdirs();
        String filePath = outDir + File.separator + nombreArchivo + ".pdf";

        try (PDDocument document = new PDDocument()) {
            PDPage page = new PDPage();
            document.addPage(page);

            try (PDPageContentStream contentStream = new PDPageContentStream(document, page)) {
                contentStream.beginText();
                contentStream.setFont(PDType1Font.HELVETICA_BOLD, 14);
                contentStream.newLineAtOffset(100, 750);
                contentStream.showText("EXPEDIENTE: " + expediente.getNumero());
                
                contentStream.setFont(PDType1Font.HELVETICA, 12);
                contentStream.newLineAtOffset(0, -30);
                contentStream.showText("MATERIA: " + (expediente.getMateria() != null ? expediente.getMateria() : ""));
                
                contentStream.newLineAtOffset(0, -20);
                contentStream.showText("JUEZ: " + (expediente.getJuzgado() != null ? expediente.getJuzgado() : ""));
                
                contentStream.newLineAtOffset(0, -20);
                contentStream.showText("ESPECIALISTA: " + (expediente.getEspecialista() != null ? expediente.getEspecialista() : ""));
                
                contentStream.newLineAtOffset(0, -20);
                contentStream.showText("TERCERO: " + (expediente.getTercero() != null ? expediente.getTercero() : ""));
                
                contentStream.newLineAtOffset(0, -20);
                contentStream.showText("DEMANDADO: " + (expediente.getDemandado() != null ? expediente.getDemandado() : ""));
                
                contentStream.newLineAtOffset(0, -20);
                contentStream.showText("DEMANDANTE: " + (expediente.getDemandante() != null ? expediente.getDemandante() : ""));
                
                // Estado actual
                contentStream.newLineAtOffset(0, -40);
                contentStream.setFont(PDType1Font.HELVETICA_BOLD, 12);
                contentStream.showText("Estado Actual del Expediente:");
                
                contentStream.newLineAtOffset(0, -20);
                contentStream.setFont(PDType1Font.HELVETICA, 10);
                String estadoTexto = convertirHtmlATexto(expediente.getEstado());
                if (estadoTexto != null && !estadoTexto.isEmpty()) {
                    // Dividir texto largo en múltiples líneas
                    String[] lineas = dividirTexto(estadoTexto, 80);
                    for (String linea : lineas) {
                        contentStream.showText(linea);
                        contentStream.newLineAtOffset(0, -15);
                    }
                }
                
                contentStream.endText();
            }

            document.save(filePath);
        }

        return new File(filePath);
    }

    private static String convertirHtmlATexto(String html) {
        if (html == null) return "";
        return Jsoup.parse(html).text();
    }

    private static String[] dividirTexto(String texto, int longitudMaxima) {
        if (texto.length() <= longitudMaxima) {
            return new String[]{texto};
        }

        java.util.List<String> lineas = new java.util.ArrayList<>();
        int inicio = 0;
        
        while (inicio < texto.length()) {
            int fin = Math.min(inicio + longitudMaxima, texto.length());
            
            // Buscar el último espacio antes del límite para no cortar palabras
            if (fin < texto.length()) {
                int ultimoEspacio = texto.lastIndexOf(' ', fin);
                if (ultimoEspacio > inicio) {
                    fin = ultimoEspacio;
                }
            }
            
            lineas.add(texto.substring(inicio, fin).trim());
            inicio = fin + 1;
        }
        
        return lineas.toArray(new String[0]);
    }
}
