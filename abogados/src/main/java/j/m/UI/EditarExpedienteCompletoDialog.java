package j.m.UI;

import java.awt.BorderLayout;
import java.io.File;

import javax.swing.JButton;
import javax.swing.JDialog;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JScrollPane;
import javax.swing.JTextPane;

import j.m.services.ExpedienteService;
import j.m.utils.WordEditorUtil;

public class EditarExpedienteCompletoDialog extends JDialog {
    private String numeroExpediente;
    private JTextPane editorTexto;
    private JButton btnGuardar;
    private ExpedienteService expedienteService;
    private String rutaArchivo; // Ruta del archivo Word asociado

    public EditarExpedienteCompletoDialog(String numeroExpediente) {
        this.numeroExpediente = numeroExpediente;
        this.expedienteService = new ExpedienteService();

        setTitle("Editar Expediente - " + numeroExpediente);
        setSize(600, 500);
        setLocationRelativeTo(null);
        setModal(true);
        setLayout(new BorderLayout());

        // Panel editor de texto
        editorTexto = new JTextPane();
        editorTexto.setContentType("text/rtf"); // Permitir formato enriquecido
        JScrollPane scrollEditor = new JScrollPane(editorTexto);

        // Botón Guardar
        JPanel panelBotones = new JPanel();
        btnGuardar = new JButton("💾 Guardar Cambios");
        panelBotones.add(btnGuardar);

        add(scrollEditor, BorderLayout.CENTER);
        add(panelBotones, BorderLayout.SOUTH);

        // Cargar el expediente en el editor
        cargarExpediente();

        // Evento para guardar
        btnGuardar.addActionListener(e -> guardarCambios());

        setVisible(true);
    }

    /**
     * Carga el contenido del expediente en el editor de texto.
     */
    private void cargarExpediente() {
        rutaArchivo = expedienteService.obtenerRutaArchivo(numeroExpediente, "WORD");
        if (rutaArchivo == null || rutaArchivo.isEmpty()) {
            JOptionPane.showMessageDialog(this, "No se encontró el archivo del expediente.", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        File archivo = new File(rutaArchivo);
        if (archivo.exists()) {
            String contenido = WordEditorUtil.leerContenidoWord(rutaArchivo);
            editorTexto.setText(contenido);
        } else {
            JOptionPane.showMessageDialog(this, "El archivo no existe en la ruta esperada.", "Error", JOptionPane.ERROR_MESSAGE);
        }
    }
    /**
     * Guarda los cambios realizados en el expediente.
     */
    private void guardarCambios() {
        if (rutaArchivo == null || rutaArchivo.isEmpty()) {
            JOptionPane.showMessageDialog(this, "No se puede guardar porque no se encontró el archivo.", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        String nuevoContenido = editorTexto.getText();
        boolean exito = WordEditorUtil.guardarContenidoWord(rutaArchivo, nuevoContenido);

        if (exito) {
            JOptionPane.showMessageDialog(this, "Expediente actualizado correctamente.", "Éxito", JOptionPane.INFORMATION_MESSAGE);
        } else {
            JOptionPane.showMessageDialog(this, "Error al guardar el expediente.", "Error", JOptionPane.ERROR_MESSAGE);
        }
    }
}
