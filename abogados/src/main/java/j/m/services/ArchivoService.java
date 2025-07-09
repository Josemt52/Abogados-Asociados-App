package j.m.services;

import java.awt.Desktop;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;

import org.apache.poi.xwpf.usermodel.XWPFDocument;
import org.apache.poi.xwpf.usermodel.XWPFParagraph;
import org.apache.poi.xwpf.usermodel.XWPFRun;

import j.m.models.Archivo;
import j.m.models.Expediente;

public class ArchivoService {

    private static final String WORD_FOLDER_PATH = System.getProperty("user.home") + "/Desktop/ExpedientesWord";

    static {
        new File(WORD_FOLDER_PATH).mkdirs(); // Asegura que la carpeta exista
    }

    public Archivo prepararArchivoInicial(Expediente expediente, String nombreArchivo) throws IOException {
        Path filePath = Paths.get(WORD_FOLDER_PATH, nombreArchivo + ".docx");

        // 1. Creamos el documento con la estructura completa
        try (XWPFDocument document = new XWPFDocument(); FileOutputStream out = new FileOutputStream(filePath.toFile())) {
            
            agregarTexto(document, "EXPEDIENTE : " + expediente.getNumero(), true);
            agregarTexto(document, "MATERIA : " + expediente.getMateria(), false);
            agregarTexto(document, "JUEZ : " + expediente.getJuzgado(), false);
            agregarTexto(document, "ESPECIALISTA : " + expediente.getEspecialista(), false);
            agregarTexto(document, "TERCERO : " + expediente.getTercero(), false);
            agregarTexto(document, "DEMANDADO : " + expediente.getDemandado(), false);
            agregarTexto(document, "DEMANDANTE : " + expediente.getDemandante(), false);
            document.createParagraph().createRun().addBreak(); 

            agregarTexto(document, "Resolución N° 1", true);
            agregarTexto(document, expediente.getEstado(), false); // Estado inicial

            // 2. Lo guardamos físicamente en el escritorio
            document.write(out);
        }

        // 3. Leemos ese mismo archivo para guardarlo en la base de datos
        byte[] documentoBytes = Files.readAllBytes(filePath);

        Archivo archivo = new Archivo();
        archivo.setNombreArchivo(nombreArchivo);
        archivo.setTipoArchivo("WORD");
        archivo.setDocumentoData(documentoBytes);
        archivo.setExpediente(expediente);

        return archivo;
    }
    
    // Obtiene el archivo desde el escritorio para ser editado
    public File getArchivoDelEscritorio(Archivo archivo) throws IOException {
        if (archivo == null) {
            throw new IOException("El expediente no tiene un archivo asociado.");
        }
        File file = new File(WORD_FOLDER_PATH, archivo.getNombreArchivo() + ".docx");
        
        // Si el archivo no está en el escritorio (ej. se borró), lo restauramos desde la BD
        if (!file.exists()) {
            try (FileOutputStream fos = new FileOutputStream(file)) {
                fos.write(archivo.getDocumentoData());
            }
        }
        return file;
    }

    public void abrirArchivoParaEditar(File archivo) throws IOException {
        Desktop.getDesktop().open(archivo);
    }
    
    public byte[] leerBytesDeArchivo(File archivo) throws IOException {
        return Files.readAllBytes(archivo.toPath());
    }

    // Método de ayuda para dar formato al texto en Word
    private void agregarTexto(XWPFDocument document, String texto, boolean esTitulo) {
        XWPFParagraph paragraph = document.createParagraph();
        XWPFRun run = paragraph.createRun();
        if (esTitulo) {
            run.setBold(true);
            run.setFontSize(14);
        } else {
            run.setFontSize(12);
        }
        run.setText(texto);
    }
}