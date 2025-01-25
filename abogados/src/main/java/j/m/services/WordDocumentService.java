package j.m.services;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;

import org.apache.poi.xwpf.usermodel.XWPFDocument;
import org.apache.poi.xwpf.usermodel.XWPFParagraph;
import org.apache.poi.xwpf.usermodel.XWPFRun;

public class WordDocumentService {

    public static void crearDocumento(String nombreArchivo, String numero, String materia, String juzgado,
                                  String especialista, String tercero, String demandado,
                                  String demandante, String estadoActual) throws IOException {
     // Obtener ruta del archivo
     String filePath = System.getProperty("user.home") + "/Desktop/ExpedientesWord/" + nombreArchivo + ".docx";

    try (XWPFDocument document = new XWPFDocument();
         FileOutputStream out = new FileOutputStream(filePath)) {

        XWPFParagraph paragraph = document.createParagraph();
        XWPFRun run = paragraph.createRun();
        run.setText("EXPEDIENTE : " + numero);
        run.addBreak();
        run.setText("MATERIA : " + materia);
        run.addBreak();
        run.setText("JUEZ : " + juzgado);
        run.addBreak();
        run.setText("ESPECIALISTA : " + especialista);
        run.addBreak();
        run.setText("TERCERO : " + tercero);
        run.addBreak();
        run.setText("DEMANDADO : " + demandado);
        run.addBreak();
        run.setText("DEMANDANTE : " + demandante);
        run.addBreak();
        run.addBreak();
        run.setBold(true);
        run.setFontSize(14);
        run.setText("Resolución Nº 1");
        run.addBreak();
        run.addBreak();
        run.setFontSize(12);
        run.setText(estadoActual);

        document.write(out);
        System.out.println("Documento Word creado en: " + filePath);
    }
}

    public static void agregarResolucionEnWord(String filePath, String nuevaResolucion, int numeroResolucion) throws IOException {
    File file = new File(filePath);
    if (!file.exists()) {
        throw new FileNotFoundException("El archivo Word no existe.");
    }

    try (XWPFDocument document = new XWPFDocument(new FileInputStream(file));
         FileOutputStream out = new FileOutputStream(file)) {

        XWPFParagraph paragraph = document.createParagraph();
        XWPFRun run = paragraph.createRun();
        run.addBreak();
        run.setBold(true);
        run.setFontSize(14);
        run.setText("Resolución Nº " + numeroResolucion);
        run.addBreak();
        run.addBreak();
        run.setFontSize(12);
        run.setText(nuevaResolucion);
        run.addBreak();

        document.write(out);
        System.out.println("Resolución añadida al archivo Word.");
    }
}
}
