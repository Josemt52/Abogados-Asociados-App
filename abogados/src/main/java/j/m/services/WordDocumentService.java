package j.m.services;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;

import org.apache.poi.xwpf.usermodel.XWPFDocument;
import org.apache.poi.xwpf.usermodel.XWPFParagraph;
import org.apache.poi.xwpf.usermodel.XWPFRun;
import org.jsoup.Jsoup;

public class WordDocumentService {

    public static void crearDocumento(String nombreArchivo, String numero, String materia, String juzgado,
                                      String especialista, String tercero, String demandado,
                                      String demandante, String estadoActual) throws IOException {

        // Obtener ruta del archivo
        String filePath = System.getProperty("user.home") + "/Desktop/ExpedientesWord/" + nombreArchivo + ".docx";

        try (XWPFDocument document = new XWPFDocument();
             FileOutputStream out = new FileOutputStream(filePath)) {

            // Agregar los datos del expediente
            agregarTexto(document, "EXPEDIENTE : " + numero, true);
            agregarTexto(document, "MATERIA : " + materia, false);
            agregarTexto(document, "JUEZ : " + juzgado, false);
            agregarTexto(document, "ESPECIALISTA : " + especialista, false);
            agregarTexto(document, "TERCERO : " + tercero, false);
            agregarTexto(document, "DEMANDADO : " + demandado, false);
            agregarTexto(document, "DEMANDANTE : " + demandante, false);
            document.createParagraph().createRun().addBreak(); // Espacio antes de la resolución

            // Agregar Resolución Inicial
            agregarTexto(document, "Resolución Nº 1", true);
            agregarTexto(document, convertirHtmlATexto(estadoActual), false);

            document.write(out);
            System.out.println("Documento Word creado en: " + filePath);
        }
    }

    // Método para agregar resoluciones adicionales sin sobrescribir el archivo
    public static void agregarResolucionEnWord(String filePath, String nuevaResolucion, int numeroResolucion) throws IOException {
        File file = new File(filePath);
        if (!file.exists()) {
            throw new FileNotFoundException("El archivo Word no existe.");
        }

        try (FileInputStream fis = new FileInputStream(file);
             XWPFDocument document = new XWPFDocument(fis);
             FileOutputStream out = new FileOutputStream(file)) {

            // Agregar nueva resolución al final del documento
            agregarTexto(document, "Resolución Nº " + numeroResolucion, true);
            agregarTexto(document, convertirHtmlATexto(nuevaResolucion), false);

            document.write(out);
            System.out.println("Resolución Nº " + numeroResolucion + " añadida al archivo Word.");
        }
    }

    // Método para agregar un párrafo con formato
    private static void agregarTexto(XWPFDocument document, String texto, boolean esTitulo) {
        XWPFParagraph paragraph = document.createParagraph();
        XWPFRun run = paragraph.createRun();
        if (esTitulo) {
            run.setBold(true);
            run.setFontSize(14);
        } else {
            run.setFontSize(12);
        }
        run.setText(texto);
        run.addBreak();
    }

    // Método para convertir HTML a texto con formato básico
    private static String convertirHtmlATexto(String html) {
        return Jsoup.parse(html).text();
    }
}
