package j.m.ui;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Component;
import java.awt.Dimension;
import java.awt.Font;
import java.awt.Frame;

import javax.swing.BorderFactory;
import javax.swing.Box;
import javax.swing.BoxLayout;
import javax.swing.JButton;
import javax.swing.JDialog;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JTextField;
import javax.swing.UIManager;

import j.m.models.Archivo;
import j.m.models.Expediente;
import j.m.services.ArchivoService;
import j.m.services.ExpedienteService;

public class ExpedienteDialog extends JDialog {
    private JTextField numeroField, materiaField, juzgadoField, especialistaField, terceroField, demandadoField, demandanteField, estadoActualField, nombreArchivoField;
    private boolean guardado = false;
    private ExpedienteService expedienteService = new ExpedienteService();
    private ArchivoService archivoService = new ArchivoService();

    public ExpedienteDialog(Frame owner) {
        super(owner, "Crear Nuevo Expediente", true);
        setSize(520, 680);
        setLocationRelativeTo(owner);
        setLayout(new BorderLayout());

        JPanel formPanel = new JPanel();
        formPanel.setLayout(new BoxLayout(formPanel, BoxLayout.Y_AXIS));
        formPanel.setBorder(BorderFactory.createCompoundBorder(
            BorderFactory.createEmptyBorder(25, 35, 25, 35),
            BorderFactory.createTitledBorder(
                BorderFactory.createLineBorder(new Color(100, 100, 100), 2),
                "Información del Expediente",
                javax.swing.border.TitledBorder.CENTER,
                javax.swing.border.TitledBorder.TOP,
                new Font("Arial", Font.BOLD, 16),
                new Color(50, 50, 50)
            )
        ));

        formPanel.add(Box.createVerticalStrut(15));

        numeroField = addField(formPanel, "Número:");
        materiaField = addField(formPanel, "Materia:");
        juzgadoField = addField(formPanel, "Juzgado:");
        especialistaField = addField(formPanel, "Especialista:");
        terceroField = addField(formPanel, "Tercero:");
        demandadoField = addField(formPanel, "Demandado:");
        demandanteField = addField(formPanel, "Demandante:");
        estadoActualField = addField(formPanel, "Estado Actual:");
        nombreArchivoField = addField(formPanel, "Nombre Archivo:");

        formPanel.add(Box.createVerticalStrut(20));

        JPanel buttonPanel = new JPanel();
        buttonPanel.setLayout(new BoxLayout(buttonPanel, BoxLayout.X_AXIS));
        buttonPanel.setAlignmentX(Component.CENTER_ALIGNMENT);

        JButton guardarButton = new JButton("Guardar");
        guardarButton.setBackground(Color.WHITE);
        guardarButton.setForeground(Color.BLACK);
        guardarButton.setFocusPainted(false);
        guardarButton.setFont(new Font("Arial", Font.BOLD, 14));
        guardarButton.setIcon(UIManager.getIcon("FileView.floppyDriveIcon"));
        guardarButton.addActionListener(e -> handleGuardar());
        buttonPanel.add(guardarButton);
        buttonPanel.add(Box.createHorizontalStrut(15));

        JButton cancelarButton = new JButton("Cancelar");
        cancelarButton.setBackground(Color.WHITE);
        cancelarButton.setForeground(Color.BLACK);
        cancelarButton.setFocusPainted(false);
        cancelarButton.setFont(new Font("Arial", Font.BOLD, 14));
        cancelarButton.addActionListener(e -> dispose());
        buttonPanel.add(cancelarButton);

        formPanel.add(buttonPanel);
        add(formPanel, BorderLayout.CENTER);
    }

    private JTextField addField(JPanel panel, String label) {
        JLabel l = new JLabel(label);
        l.setFont(new Font("Arial", Font.BOLD, 15));
        l.setForeground(new Color(60, 60, 60));
        l.setAlignmentX(Component.LEFT_ALIGNMENT);
        panel.add(l);
        
        JTextField field = new JTextField(18);
        field.setMaximumSize(new Dimension(Integer.MAX_VALUE, 35));
        field.setFont(new Font("Arial", Font.PLAIN, 14));
        field.setBorder(BorderFactory.createCompoundBorder(
            BorderFactory.createLineBorder(new Color(150, 150, 150), 1),
            BorderFactory.createEmptyBorder(5, 8, 5, 8)
        ));
        panel.add(field);
        panel.add(Box.createVerticalStrut(12));
        return field;
    }

    private void handleGuardar() {
        try {
            Expediente nuevoExpediente = new Expediente();
            nuevoExpediente.setNumero(numeroField.getText());
            nuevoExpediente.setMateria(materiaField.getText());
            nuevoExpediente.setJuzgado(juzgadoField.getText());
            nuevoExpediente.setEspecialista(especialistaField.getText());
            nuevoExpediente.setTercero(terceroField.getText());
            nuevoExpediente.setDemandado(demandadoField.getText());
            nuevoExpediente.setDemandante(demandanteField.getText());
            nuevoExpediente.setEstado(estadoActualField.getText());

            Archivo nuevoArchivo = archivoService.prepararArchivoInicial(
                nuevoExpediente,
                nombreArchivoField.getText()
            );
            nuevoExpediente.setArchivo(nuevoArchivo);
            nuevoArchivo.setExpediente(nuevoExpediente);

            expedienteService.crearOActualizar(nuevoExpediente);
            guardado = true;
            dispose();
        } catch (Exception e) {
            JOptionPane.showMessageDialog(this, "Error al guardar expediente: " + e.getMessage(), "Error", JOptionPane.ERROR_MESSAGE);
        }
    }

    public boolean isGuardado() {
        return guardado;
    }
}
