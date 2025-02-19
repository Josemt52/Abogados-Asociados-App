package j.m.utils;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import org.apache.poi.xwpf.usermodel.*;

public class WordEditorUtil {

    /**
     * Lee el contenido de un archivo Word (.docx).
     */
    public static String leerContenidoWord(String rutaArchivo) {
        StringBuilder contenido = new StringBuilder();
        try (FileInputStream fis = new FileInputStream(new File(rutaArchivo));
             XWPFDocument documento = new XWPFDocument(fis)) {

            for (XWPFParagraph parrafo : documento.getParagraphs()) {
                contenido.append(parrafo.getText()).append("\n");
            }
        } catch (IOException e) {
            e.printStackTrace();
        }
        return contenido.toString();
    }

    /**
     * Guarda contenido en un archivo Word (.docx).
     */
    public static boolean guardarContenidoWord(String rutaArchivo, String contenido) {
        try (XWPFDocument documento = new XWPFDocument();
             FileOutputStream fos = new FileOutputStream(new File(rutaArchivo))) {

            XWPFParagraph parrafo = documento.createParagraph();
            XWPFRun run = parrafo.createRun();
            run.setText(contenido);

            documento.write(fos);
            return true;
        } catch (IOException e) {
            e.printStackTrace();
            return false;
        }
    }
}
