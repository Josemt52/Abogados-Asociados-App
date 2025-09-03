package com.abogados.backend.services;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;

import org.apache.poi.xwpf.usermodel.XWPFDocument;
import org.apache.poi.xwpf.usermodel.XWPFParagraph;
import org.apache.poi.xwpf.usermodel.XWPFRun;
import org.jsoup.Jsoup;

import com.abogados.backend.models.Expediente;

public class WordDocumentService {

    public static File crearDocumento(Expediente expediente, String nombreArchivo) throws IOException {
        String outDir = System.getProperty("user.home") + File.separator + "ExpedientesWord";
        new File(outDir).mkdirs();
        String filePath = outDir + File.separator + nombreArchivo + ".docx";

        try (XWPFDocument document = new XWPFDocument();
             FileOutputStream out = new FileOutputStream(filePath)) {

            agregarTexto(document, "EXPEDIENTE : " + expediente.getNumero(), true);
            agregarTexto(document, "MATERIA : " + expediente.getMateria(), false);
            agregarTexto(document, "JUEZ : " + expediente.getJuzgado(), false);
            agregarTexto(document, "ESPECIALISTA : " + expediente.getEspecialista(), false);
            agregarTexto(document, "TERCERO : " + expediente.getTercero(), false);
            agregarTexto(document, "DEMANDADO : " + expediente.getDemandado(), false);
            agregarTexto(document, "DEMANDANTE : " + expediente.getDemandante(), false);
            document.createParagraph().createRun().addBreak();

            agregarTexto(document, "Resolución Inicial", true);
            agregarTexto(document, convertirHtmlATexto(expediente.getEstado()), false);

            document.write(out);
        }

        return new File(filePath);
    }

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

    private static String convertirHtmlATexto(String html) {
        if (html == null) return "";
        return Jsoup.parse(html).text();
    }
}
