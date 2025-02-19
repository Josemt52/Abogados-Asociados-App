package j.m.UI;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Desktop;
import java.awt.FlowLayout;
import java.awt.event.KeyAdapter;
import java.awt.event.KeyEvent;
import java.io.File;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

import javax.swing.JButton;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JScrollPane;
import javax.swing.JTable;
import javax.swing.JTextField;
import javax.swing.table.DefaultTableModel;

import j.m.utils.DBConnection;

public class VerExpedientesPanel extends JPanel {
    private JTable table;
    private DefaultTableModel tableModel;
    private JTextField txtBuscar;
    private JButton btnAbrirExpediente; // <--- Botón para abrir

    public VerExpedientesPanel() {
        setLayout(new BorderLayout());
        setBackground(Color.WHITE);

        // Panel superior con campo de búsqueda, actualizar y abrir
        JPanel panelSuperior = new JPanel(new FlowLayout(FlowLayout.LEFT));
        panelSuperior.setBackground(Color.WHITE);
        
        JLabel lblBuscar = new JLabel("Buscar: ");
        txtBuscar = new JTextField(20);
        JButton btnActualizar = new JButton("🔄 Actualizar");
        btnAbrirExpediente = new JButton("Abrir Expediente"); // <--- Botón para abrir

        panelSuperior.add(lblBuscar);
        panelSuperior.add(txtBuscar);
        panelSuperior.add(btnActualizar);
        panelSuperior.add(btnAbrirExpediente);

        // Tabla con modelo de datos
        tableModel = new DefaultTableModel(
            new String[]{"Número", "Materia", "Juzgado", "Especialista", "Tercero", "Demandado", "Demandante", "Tipo Archivo"}, 0
        );
        table = new JTable(tableModel);
        table.setAutoResizeMode(JTable.AUTO_RESIZE_ALL_COLUMNS);
        JScrollPane scrollPane = new JScrollPane(table);

        add(panelSuperior, BorderLayout.NORTH);
        add(scrollPane, BorderLayout.CENTER);

        // Cargar datos en la tabla
        cargarExpedientes("");

        // Evento para filtrar al escribir en el campo de búsqueda
        txtBuscar.addKeyListener(new KeyAdapter() {
            @Override
            public void keyReleased(KeyEvent e) {
                cargarExpedientes(txtBuscar.getText().trim());
            }
        });

        // Evento para actualizar la tabla
        btnActualizar.addActionListener(e -> cargarExpedientes(""));

        // Evento para abrir el expediente seleccionado
        btnAbrirExpediente.addActionListener(e -> abrirExpedienteSeleccionado());
    }

    /**
     * Carga los expedientes desde la base de datos y los muestra en la tabla.
     * Si se proporciona un texto, filtra los resultados.
     */
    private void cargarExpedientes(String filtro) {
        tableModel.setRowCount(0); // Limpiar la tabla antes de cargar nuevos datos

        // Ajusta la consulta según tu estructura. 
        // Suponemos que en la tabla 'expedientes' hay 'numero' y en 'archivos' hay 'tipo_archivo'
        // y que queremos filtrar por numero.
        String query = "SELECT e.numero, e.materia, e.juzgado, e.especialista, e.tercero, e.demandado, e.demandante, a.tipo_archivo " +
                       "FROM expedientes e " +
                       "LEFT JOIN archivos a ON e.numero = a.expediente_numero " +
                       "WHERE e.numero LIKE ? OR a.tipo_archivo LIKE ?";

        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {

            stmt.setString(1, "%" + filtro + "%");
            stmt.setString(2, "%" + filtro + "%");

            ResultSet rs = stmt.executeQuery();
            while (rs.next()) {
                tableModel.addRow(new Object[]{
                    rs.getString("numero"),
                    rs.getString("materia"),
                    rs.getString("juzgado"),
                    rs.getString("especialista"),
                    rs.getString("tercero"),
                    rs.getString("demandado"),
                    rs.getString("demandante"),
                    rs.getString("tipo_archivo") // WORD, PDF, etc.
                });
            }
        } catch (SQLException e) {
            JOptionPane.showMessageDialog(this, "Error al cargar expedientes: " + e.getMessage(), "Error", JOptionPane.ERROR_MESSAGE);
            e.printStackTrace();
        }
    }

    /**
     * Abre el expediente seleccionado en la tabla
     */
    private void abrirExpedienteSeleccionado() {
        int fila = table.getSelectedRow();
        if (fila == -1) {
            JOptionPane.showMessageDialog(this, "Seleccione un expediente para abrir.", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        // Obtenemos el número y el tipo de archivo
        String numeroExpediente = (String) tableModel.getValueAt(fila, 0);
        String tipoArchivo = (String) tableModel.getValueAt(fila, 7);

        // Buscar el nombre de archivo en la BD, ya que no lo mostramos en la tabla
        String nombreArchivo = obtenerNombreArchivo(numeroExpediente, tipoArchivo);
        if (nombreArchivo == null || nombreArchivo.isEmpty()) {
            JOptionPane.showMessageDialog(this, "No se encontró el archivo en la BD.", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        // Construir la ruta y abrir el archivo
        abrirArchivo(numeroExpediente, tipoArchivo, nombreArchivo);
    }

    /**
     * Consulta en la tabla 'archivos' para obtener el 'nombre_archivo' usando numeroExpediente y tipoArchivo.
     */
    private String obtenerNombreArchivo(String numeroExpediente, String tipoArchivo) {
        String query = "SELECT nombre_archivo FROM archivos WHERE expediente_numero = ? AND tipo_archivo = ?";
        try (Connection connection = DBConnection.getConnection();
             PreparedStatement stmt = connection.prepareStatement(query)) {

            stmt.setString(1, numeroExpediente);
            stmt.setString(2, tipoArchivo);
            ResultSet rs = stmt.executeQuery();
            if (rs.next()) {
                return rs.getString("nombre_archivo");
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return null;
    }

    /**
     * Construye la ruta del archivo y lo abre con Desktop.
     */
    private void abrirArchivo(String numeroExpediente, String tipoArchivo, String nombreArchivo) {
        try {
            // Ajusta la ruta real donde guardas tus documentos
            String ruta = "";
            if (tipoArchivo != null && tipoArchivo.equalsIgnoreCase("WORD")) {
                ruta = System.getProperty("user.home") + "/Desktop/ExpedientesWord/" + nombreArchivo + ".docx";
            } else {
                ruta = System.getProperty("user.home") + "/Desktop/ExpedientesPDF/" + nombreArchivo + ".pdf";
            }

            File file = new File(ruta);
            if (!file.exists()) {
                JOptionPane.showMessageDialog(this, "El archivo no existe: " + ruta, "Error", JOptionPane.ERROR_MESSAGE);
                return;
            }
            Desktop.getDesktop().open(file);

        } catch (Exception ex) {
            JOptionPane.showMessageDialog(this, "Error al abrir el archivo: " + ex.getMessage(), "Error", JOptionPane.ERROR_MESSAGE);
            ex.printStackTrace();
        }
    }
}
