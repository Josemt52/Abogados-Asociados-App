package j.m.UI;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Dimension;
import java.awt.Font;
import java.awt.Frame;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.awt.Insets;
import java.io.IOException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.SQLException;

import javax.swing.Box;
import javax.swing.JButton;
import javax.swing.JDialog;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JTextField;
import javax.swing.SwingConstants;
import javax.swing.SwingUtilities;

import j.m.services.ArchivoService;
import j.m.utils.DBConnection;

public class ExpedienteForm extends JPanel {
    private JTextField txtNumero, txtMateria, txtJuzgado, txtEspecialista, txtTercero, txtDemandado, txtDemandante, txtNombreArchivo;
    private String estadoHTML = "";
    private ArchivoService archivoService;

    public ExpedienteForm() {
        setLayout(new GridBagLayout());
        setBackground(Color.WHITE);

        archivoService = new ArchivoService();
        
        // Panel contenedor con tamaño fijo para evitar expansión excesiva
        JPanel formPanel = new JPanel(new GridBagLayout());
        formPanel.setPreferredSize(new Dimension(600, 500)); // Tamaño máximo
        formPanel.setBackground(Color.WHITE);

        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(8, 8, 8, 8);
        gbc.fill = GridBagConstraints.HORIZONTAL; 
        gbc.anchor = GridBagConstraints.CENTER;
        gbc.weightx = 1.0;

        int row = 0;

        // Título
        JLabel lblTitulo = new JLabel("Crear Expediente");
        lblTitulo.setFont(new Font("Arial", Font.BOLD, 20));
        lblTitulo.setHorizontalAlignment(SwingConstants.CENTER);
        gbc.gridx = 0; 
        gbc.gridy = row++;
        gbc.gridwidth = 2;
        formPanel.add(lblTitulo, gbc);

        gbc.gridwidth = 1;

        // Campos del formulario
        txtNumero = agregarFila(formPanel, "Número:", row++);
        txtMateria = agregarFila(formPanel, "Materia:", row++);
        txtJuzgado = agregarFila(formPanel, "Juzgado:", row++);
        txtEspecialista = agregarFila(formPanel, "Especialista:", row++);
        txtTercero = agregarFila(formPanel, "Tercero:", row++);
        txtDemandado = agregarFila(formPanel, "Demandado:", row++);
        txtDemandante = agregarFila(formPanel, "Demandante:", row++);
        txtNombreArchivo = agregarFila(formPanel, "Nombre de Archivo:", row++);

        // Botón Editar Estado
        JLabel lblEstado = new JLabel("Estado del Expediente:");
        lblEstado.setFont(new Font("Arial", Font.BOLD, 14));
        gbc.gridx = 0;
        gbc.gridy = row;
        formPanel.add(lblEstado, gbc);

        JButton btnEditarEstado = new JButton("✏ Editar Estado");
        btnEditarEstado.setBackground(new Color(135, 206, 235));
        btnEditarEstado.setForeground(Color.WHITE);
        btnEditarEstado.addActionListener(e -> abrirEditorEstado());
        gbc.gridx = 1;
        formPanel.add(btnEditarEstado, gbc);
        row++;

        // Botón Guardar
        JButton btnGuardar = new JButton("📂 Guardar");
        btnGuardar.setBackground(new Color(72, 209, 204));
        btnGuardar.setForeground(Color.WHITE);
        btnGuardar.addActionListener(e -> guardarExpediente());
        gbc.gridx = 1;
        gbc.gridy = row++;
        formPanel.add(btnGuardar, gbc);

        // Espacio flexible
        gbc.gridx = 0;
        gbc.gridy = row;
        gbc.gridwidth = 2;
        gbc.weighty = 1.0;
        formPanel.add(Box.createVerticalGlue(), gbc);

        // Agregar formPanel centrado
        GridBagConstraints gbcMain = new GridBagConstraints();
        gbcMain.gridy = 0;
        gbcMain.weighty = 1.0;
        add(formPanel, gbcMain);
    }

    /**
     * Método auxiliar para agregar campos con etiquetas
     */
    private JTextField agregarFila(JPanel panel, String texto, int row) {
        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(5, 5, 5, 5);
        gbc.fill = GridBagConstraints.HORIZONTAL;
        gbc.gridx = 0;
        gbc.gridy = row;
        gbc.weightx = 0.0;

        JLabel label = new JLabel(texto);
        label.setFont(new Font("Arial", Font.BOLD, 14));
        panel.add(label, gbc);

        gbc.gridx = 1;
        gbc.weightx = 1.0;
        JTextField campo = new JTextField(15);
        panel.add(campo, gbc);

        return campo;
    }

    /**
     * Abre el editor de estado en un JDialog
     */
    private void abrirEditorEstado() {
        JDialog editorDialog = new JDialog((Frame) SwingUtilities.getWindowAncestor(this), "Editor de Estado", true);
        editorDialog.setSize(700, 500);
        editorDialog.setLocationRelativeTo(this);

        EditorEstadoDialog editorPanel = new EditorEstadoDialog(estadoHTML);

        JButton btnGuardarEstado = new JButton("Guardar Estado");
        btnGuardarEstado.setBackground(new Color(100, 149, 237)); // Azul
        btnGuardarEstado.setForeground(Color.WHITE);
        btnGuardarEstado.addActionListener(e -> {
            estadoHTML = editorPanel.getHTML();
            editorDialog.dispose();
        });

        editorDialog.setLayout(new BorderLayout());
        editorDialog.add(editorPanel, BorderLayout.CENTER);
        editorDialog.add(btnGuardarEstado, BorderLayout.SOUTH);
        editorDialog.setVisible(true);
    }

    /**
     * Guarda el expediente en la base de datos
     */
    private void guardarExpediente() {
        String numero = txtNumero.getText().trim();
        String materia = txtMateria.getText().trim();
        String juzgado = txtJuzgado.getText().trim();
        String especialista = txtEspecialista.getText().trim();
        String tercero = txtTercero.getText().trim();
        String demandado = txtDemandado.getText().trim();
        String demandante = txtDemandante.getText().trim();
        String nombreArchivo = txtNombreArchivo.getText().trim();
        String estadoActual = estadoHTML.trim(); // Texto del editor (HTML o como lo manejes)
    
        if (numero.isEmpty() || estadoActual.isEmpty() || nombreArchivo.isEmpty()) {
            JOptionPane.showMessageDialog(this, 
                "Número, Estado y Nombre del archivo son obligatorios.", 
                "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }
    
        // Sentencias SQL
        String queryExpediente = "INSERT INTO expedientes " +
            "(numero, materia, juzgado, especialista, tercero, demandado, demandante, estado_actual) " +
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
        String queryArchivo = "INSERT INTO archivos " +
            "(expediente_numero, nombre_archivo, tipo_archivo) " +
            "VALUES (?, ?, ?)";
    
        try (Connection connection = DBConnection.getConnection()) {
            connection.setAutoCommit(false); // Iniciar transacción
    
            try (PreparedStatement stmtExpediente = connection.prepareStatement(queryExpediente);
                 PreparedStatement stmtArchivo = connection.prepareStatement(queryArchivo)) {
    
                // 1) Insertar en 'expedientes'
                stmtExpediente.setString(1, numero);
                stmtExpediente.setString(2, materia);
                stmtExpediente.setString(3, juzgado);
                stmtExpediente.setString(4, especialista);
                stmtExpediente.setString(5, tercero);
                stmtExpediente.setString(6, demandado);
                stmtExpediente.setString(7, demandante);
                stmtExpediente.setString(8, estadoActual);
                stmtExpediente.executeUpdate();
    
                // 2) Insertar en 'archivos'
                stmtArchivo.setString(1, numero);
                stmtArchivo.setString(2, nombreArchivo);
                stmtArchivo.setString(3, "WORD"); // Asumiendo que siempre es WORD
                stmtArchivo.executeUpdate();
    
                connection.commit(); // Confirmar transacción
    
                // 3) Crear el archivo Word físicamente
                try {
                    archivoService.crearArchivo(
                        numero,            // númeroExpediente
                        "WORD",            // tipoArchivo
                        nombreArchivo,     // nombreArchivo
                        numero,            // número (de expediente)
                        materia, 
                        juzgado, 
                        especialista, 
                        tercero, 
                        demandado, 
                        demandante, 
                        estadoActual
                    );
    
                    JOptionPane.showMessageDialog(this, 
                        "Expediente guardado y archivo Word creado correctamente.", 
                        "Éxito", JOptionPane.INFORMATION_MESSAGE);
    
                    limpiarCampos();
    
                } catch (IOException e) {
                    JOptionPane.showMessageDialog(this, 
                        "Error al crear el archivo Word: " + e.getMessage(), 
                        "Error", JOptionPane.ERROR_MESSAGE);
                    e.printStackTrace();
                }
    
            } catch (SQLException e) {
                connection.rollback(); // Deshacer si hay error en la BD
                JOptionPane.showMessageDialog(this, 
                    "Error al guardar en la BD: " + e.getMessage(), 
                    "Error", JOptionPane.ERROR_MESSAGE);
                e.printStackTrace();
            } finally {
                connection.setAutoCommit(true);
            }
        } catch (SQLException e) {
            JOptionPane.showMessageDialog(this, 
                "Error de conexión a la BD: " + e.getMessage(), 
                "Error", JOptionPane.ERROR_MESSAGE);
            e.printStackTrace();
        }
    }

    private void limpiarCampos() {
        txtNumero.setText("");
        txtMateria.setText("");
        txtJuzgado.setText("");
        txtEspecialista.setText("");
        txtTercero.setText("");
        txtDemandado.setText("");
        txtDemandante.setText("");
        txtNombreArchivo.setText("");
        estadoHTML = "";
    }
}
