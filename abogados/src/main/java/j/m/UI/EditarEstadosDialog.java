package j.m.UI;

import java.awt.BorderLayout;
import java.util.List;

import javax.swing.BorderFactory;
import javax.swing.DefaultListModel;
import javax.swing.JButton;
import javax.swing.JDialog;
import javax.swing.JList;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JScrollPane;
import javax.swing.JTextArea;

import j.m.services.ExpedienteService;

public class EditarEstadosDialog extends JDialog {
    private String numeroExpediente;
    private DefaultListModel<String> listModel;
    private JList<String> estadosList;
    private JTextArea txtNuevoEstado;
    private JButton btnAgregarEstado;
    private ExpedienteService expedienteService;

    public EditarEstadosDialog(String numeroExpediente) {
        this.numeroExpediente = numeroExpediente;
        this.expedienteService = new ExpedienteService();

        setTitle("Editar Estados - Expediente " + numeroExpediente);
        setSize(500, 400);
        setLocationRelativeTo(null);
        setModal(true);
        setLayout(new BorderLayout());

        // Panel superior - Lista de estados
        JPanel panelEstados = new JPanel(new BorderLayout());
        panelEstados.setBorder(BorderFactory.createTitledBorder("Estados Anteriores"));

        listModel = new DefaultListModel<>();
        estadosList = new JList<>(listModel);
        JScrollPane scrollEstados = new JScrollPane(estadosList);

        panelEstados.add(scrollEstados, BorderLayout.CENTER);

        // Panel central - Nuevo estado
        JPanel panelNuevoEstado = new JPanel(new BorderLayout());
        panelNuevoEstado.setBorder(BorderFactory.createTitledBorder("Agregar Nuevo Estado"));

        txtNuevoEstado = new JTextArea(3, 30);
        JScrollPane scrollNuevoEstado = new JScrollPane(txtNuevoEstado);

        panelNuevoEstado.add(scrollNuevoEstado, BorderLayout.CENTER);

        // Panel inferior - Botón
        JPanel panelBotones = new JPanel();
        btnAgregarEstado = new JButton("➕ Agregar Estado");

        panelBotones.add(btnAgregarEstado);

        add(panelEstados, BorderLayout.NORTH);
        add(panelNuevoEstado, BorderLayout.CENTER);
        add(panelBotones, BorderLayout.SOUTH);

        // Cargar estados actuales
        cargarEstados();

        // Evento para agregar estado
        btnAgregarEstado.addActionListener(e -> agregarEstado());

        setVisible(true);
    }

    /**
     * Carga los estados anteriores del expediente en la lista.
     */
    private void cargarEstados() {
        listModel.clear();
    
        // Obtener el estado actual del expediente (el estado base)
        String estadoActual = expedienteService.obtenerEstadoActual(numeroExpediente);
    
        // Obtener la lista de estados desde el historial
        List<String> estados = expedienteService.obtenerEstadosConNumeracion(numeroExpediente);
    
        int numeroEstado = 1; // Contador de estados
    
        // Agregar el estado inicial como el primer estado
        if (estadoActual != null && !estadoActual.isEmpty()) {
            listModel.addElement(numeroEstado + ". " + estadoActual);
            numeroEstado++; // Incrementar numeración para el siguiente estado
        }
    
        // Agregar los estados del historial con numeración continua
        for (String estado : estados) {
            listModel.addElement(numeroEstado + ". " + estado);
            numeroEstado++;
        }
    }
    

    /**
     * Agrega un nuevo estado al expediente y lo guarda en el documento.
     */
    private void agregarEstado() {
        String nuevoEstado = txtNuevoEstado.getText().trim();
        if (nuevoEstado.isEmpty()) {
            JOptionPane.showMessageDialog(this, "El estado no puede estar vacío.", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        try {
            // Usar el método que actualiza la base de datos y el documento
            expedienteService.agregarEstadoExpedienteConDocumentos(numeroExpediente, nuevoEstado);

            JOptionPane.showMessageDialog(this, "Estado agregado correctamente y actualizado en el documento.",
                    "Éxito", JOptionPane.INFORMATION_MESSAGE);

            txtNuevoEstado.setText("");
            cargarEstados(); // Recargar la lista con el nuevo estado agregado
        } catch (Exception e) {
            JOptionPane.showMessageDialog(this, "Error al actualizar el estado en la base de datos o en el documento.",
                    "Error", JOptionPane.ERROR_MESSAGE);
            e.printStackTrace();
        }
    }
}
