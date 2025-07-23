package j.m.ui;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Component;
import java.awt.FlowLayout;
import java.awt.Font;
import java.io.File;
import java.util.List;

import javax.swing.BorderFactory;
import javax.swing.Box;
import javax.swing.JButton;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JMenu;
import javax.swing.JMenuBar;
import javax.swing.JMenuItem;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JScrollPane;
import javax.swing.JTable;
import javax.swing.JTextField;
import javax.swing.ListSelectionModel;
import javax.swing.UIManager;
import javax.swing.table.DefaultTableModel;

import j.m.models.Expediente;
import j.m.models.Usuario;
import j.m.services.ArchivoService;
import j.m.services.ExpedienteService;

public class MainFrame extends JFrame {
    private Usuario usuarioLogueado;
    private ExpedienteService expedienteService = new ExpedienteService();
    private ArchivoService archivoService = new ArchivoService();
    private JTable expedientesTable;
    private JButton editarButton, sincronizarButton, eliminarButton, crearButton, pdfButton;
    private DefaultTableModel tableModel;

    public MainFrame(Usuario usuario) {
        this.usuarioLogueado = usuario;
        setTitle("Gestión de Expedientes - " + usuario.getNombre());
        setSize(950, 650);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setLocationRelativeTo(null);
        setLayout(new BorderLayout());

        // Barra de menú
        JMenuBar menuBar = new JMenuBar();
        JMenu usuarioMenu = new JMenu("Usuario");
        JMenuItem cerrarSesionItem = new JMenuItem("Cerrar sesión");
        cerrarSesionItem.addActionListener(e -> {
            dispose();
            new LoginFrame();
        });
        usuarioMenu.add(cerrarSesionItem);
        menuBar.add(usuarioMenu);
        JMenu ayudaMenu = new JMenu("Ayuda");
        JMenuItem acercaItem = new JMenuItem("Acerca de...");
        acercaItem.addActionListener(e -> JOptionPane.showMessageDialog(this, "Abogados Asociados App\nVersión Swing Mejorada", "Acerca de", JOptionPane.INFORMATION_MESSAGE));
        ayudaMenu.add(acercaItem);
        menuBar.add(ayudaMenu);
        setJMenuBar(menuBar);

        // Panel de búsqueda
        JPanel searchPanel = new JPanel();
        searchPanel.setLayout(new FlowLayout(FlowLayout.LEFT, 10, 10));
        searchPanel.setBorder(BorderFactory.createEmptyBorder(5, 10, 5, 10));
        
        JLabel searchLabel = new JLabel("Buscar:");
        searchLabel.setFont(new Font("Arial", Font.BOLD, 14));
        searchPanel.add(searchLabel);
        
        JTextField searchField = new JTextField(20);
        searchField.setFont(new Font("Arial", Font.PLAIN, 14));
        searchField.addKeyListener(new java.awt.event.KeyAdapter() {
            public void keyReleased(java.awt.event.KeyEvent evt) {
                filtrarTabla(searchField.getText().toLowerCase());
            }
        });
        searchPanel.add(searchField);
        
        JButton clearButton = new JButton("Limpiar");
        clearButton.setBackground(Color.WHITE);
        clearButton.setForeground(Color.BLACK);
        clearButton.setFocusPainted(false);
        clearButton.setFont(new Font("Arial", Font.PLAIN, 12));
        clearButton.addActionListener(e -> {
            searchField.setText("");
            cargarExpedientes();
        });
        searchPanel.add(clearButton);
        
        add(searchPanel, BorderLayout.NORTH);

        // Tabla de expedientes con alternancia de color y selección
        String[] columns = {"Número", "Materia", "Demandante", "Demandado", "Estado"};
        tableModel = new DefaultTableModel(columns, 0) {
            public boolean isCellEditable(int row, int column) { return false; }
        };
        expedientesTable = new JTable(tableModel);
        expedientesTable.setRowHeight(28);
        expedientesTable.setSelectionMode(ListSelectionModel.SINGLE_SELECTION);
        expedientesTable.setFont(new Font("Arial", Font.PLAIN, 15));
        expedientesTable.getTableHeader().setFont(new Font("Arial", Font.BOLD, 16));
        expedientesTable.setShowGrid(true);
        expedientesTable.setGridColor(new Color(220,220,220));
        expedientesTable.setSelectionBackground(new Color(0,120,215));
        expedientesTable.setSelectionForeground(Color.WHITE);
        expedientesTable.setDefaultRenderer(Object.class, new javax.swing.table.DefaultTableCellRenderer() {
            @Override
            public Component getTableCellRendererComponent(JTable table, Object value, boolean isSelected, boolean hasFocus, int row, int column) {
                Component c = super.getTableCellRendererComponent(table, value, isSelected, hasFocus, row, column);
                if (!isSelected) {
                    c.setBackground(row % 2 == 0 ? new Color(245,245,245) : Color.WHITE);
                }
                return c;
            }
        });
        JScrollPane scrollPane = new JScrollPane(expedientesTable);
        scrollPane.setBorder(BorderFactory.createEmptyBorder(10,10,10,10));
        add(scrollPane, BorderLayout.CENTER);

        // Panel de botones mejorado
        JPanel buttonPanel = new JPanel();
        buttonPanel.setLayout(new FlowLayout(FlowLayout.CENTER, 15, 0));
        buttonPanel.setBorder(BorderFactory.createEmptyBorder(10, 10, 10, 10));

        crearButton = new JButton("Crear");
        crearButton.setBackground(Color.WHITE);
        crearButton.setForeground(Color.BLACK);
        crearButton.setFocusPainted(false);
        crearButton.setFont(new Font("Arial", Font.BOLD, 14));
        crearButton.setIcon(UIManager.getIcon("FileView.directoryIcon"));
        buttonPanel.add(crearButton);
        buttonPanel.add(Box.createHorizontalStrut(10));

        editarButton = new JButton("Editar");
        editarButton.setBackground(Color.WHITE);
        editarButton.setForeground(Color.BLACK);
        editarButton.setFocusPainted(false);
        editarButton.setFont(new Font("Arial", Font.BOLD, 14));
        editarButton.setIcon(UIManager.getIcon("FileView.fileIcon"));
        buttonPanel.add(editarButton);
        buttonPanel.add(Box.createHorizontalStrut(10));

        sincronizarButton = new JButton("Sincronizar");
        sincronizarButton.setBackground(Color.WHITE);
        sincronizarButton.setForeground(Color.BLACK);
        sincronizarButton.setFocusPainted(false);
        sincronizarButton.setFont(new Font("Arial", Font.BOLD, 14));
        sincronizarButton.setIcon(UIManager.getIcon("FileView.hardDriveIcon"));
        buttonPanel.add(sincronizarButton);
        buttonPanel.add(Box.createHorizontalStrut(10));

        eliminarButton = new JButton("Eliminar");
        eliminarButton.setBackground(Color.WHITE);
        eliminarButton.setForeground(Color.BLACK);
        eliminarButton.setFocusPainted(false);
        eliminarButton.setFont(new Font("Arial", Font.BOLD, 14));
        buttonPanel.add(eliminarButton);
        buttonPanel.add(Box.createHorizontalStrut(10));

        pdfButton = new JButton("Convertir a PDF");
        pdfButton.setBackground(Color.WHITE);
        pdfButton.setForeground(Color.BLACK);
        pdfButton.setFocusPainted(false);
        pdfButton.setFont(new Font("Arial", Font.BOLD, 14));
        pdfButton.setIcon(UIManager.getIcon("FileView.floppyDriveIcon"));
        buttonPanel.add(pdfButton);
        buttonPanel.add(Box.createHorizontalStrut(10));


        add(buttonPanel, BorderLayout.SOUTH);

        // Listeners
        crearButton.addActionListener(e -> handleCrearExpediente());
        editarButton.addActionListener(e -> handleEditarExpediente());
        sincronizarButton.addActionListener(e -> handleSincronizar());
        eliminarButton.addActionListener(e -> handleEliminarExpediente());
        pdfButton.addActionListener(e -> handleConvertirPDF());

        // Inicializar controles
        eliminarButton.setVisible(false);
        sincronizarButton.setEnabled(false);
        cargarExpedientes();
        configurarVisibilidadPorRol();
        setVisible(true);
    }

    private void cargarExpedientes() {
        tableModel.setRowCount(0);
        List<Expediente> expedientes = expedienteService.verExpedientes();
        for (Expediente exp : expedientes) {
            tableModel.addRow(new Object[]{exp.getNumero(), exp.getMateria(), exp.getDemandante(), exp.getDemandado(), exp.getEstado()});
        }
    }

    private void configurarVisibilidadPorRol() {
        boolean esAdmin = false;
        if (usuarioLogueado != null && usuarioLogueado.getRol() != null) {
            esAdmin = "Admin".equalsIgnoreCase(usuarioLogueado.getRol().getNombre());
        }
        eliminarButton.setVisible(esAdmin);
    }

    private void handleCrearExpediente() {
        ExpedienteDialog dialog = new ExpedienteDialog(this);
        dialog.setVisible(true);
        if (dialog.isGuardado()) {
            cargarExpedientes();
        }
    }

    private void handleEditarExpediente() {
        int row = expedientesTable.getSelectedRow();
        if (row == -1) {
            mostrarAlerta("Ninguna selección", "Por favor, seleccione un expediente para editar.");
            return;
        }
        Expediente expediente = expedienteService.verExpedientes().get(row);
        try {
            File archivo = archivoService.getArchivoDelEscritorio(expediente.getArchivo());
            archivoService.abrirArchivoParaEditar(archivo);
            mostrarAlerta("Edición en Progreso", "El documento se ha abierto. Cuando termines, guarda los cambios en Word y presiona 'Sincronizar Cambios'.");
            editarButton.setEnabled(false);
            sincronizarButton.setEnabled(true);
        } catch (Exception e) {
            mostrarAlerta("Error al abrir", "No se pudo abrir el documento: " + e.getMessage());
        }
    }

    private void handleSincronizar() {
        int row = expedientesTable.getSelectedRow();
        if (row == -1) {
            mostrarAlerta("Error de Sincronización", "No hay ningún expediente seleccionado en modo de edición.");
            return;
        }
        Expediente expediente = expedienteService.verExpedientes().get(row);
        try {
            File archivo = archivoService.getArchivoDelEscritorio(expediente.getArchivo());
            byte[] nuevosDatos = archivoService.leerBytesDeArchivo(archivo);
            expediente.getArchivo().setDocumentoData(nuevosDatos);
            String nuevoEstado = JOptionPane.showInputDialog(this, "Resumen del nuevo estado:", expediente.getEstado());
            if (nuevoEstado != null && !nuevoEstado.trim().isEmpty()) {
                expediente.setEstado(nuevoEstado.trim());
            }
            expedienteService.crearOActualizar(expediente);
            editarButton.setEnabled(true);
            sincronizarButton.setEnabled(false);
            cargarExpedientes();
            mostrarAlerta("Éxito", "El documento ha sido sincronizado.");
        } catch (Exception e) {
            mostrarAlerta("Error al Sincronizar", e.getMessage());
        }
    }

    private void handleEliminarExpediente() {
        int row = expedientesTable.getSelectedRow();
        if (row == -1) {
            mostrarAlerta("Ninguna selección", "Por favor, seleccione un expediente.");
            return;
        }
        Expediente expediente = expedienteService.verExpedientes().get(row);
        int confirm = JOptionPane.showConfirmDialog(
            this,
            "¿Seguro que quieres eliminar el expediente '" + expediente.getNumero() + "'?\nEsta acción no se puede deshacer.",
            "Confirmar Eliminación",
            JOptionPane.YES_NO_OPTION,
            JOptionPane.WARNING_MESSAGE
        );
        if (confirm == JOptionPane.YES_OPTION) {
            expedienteService.eliminarExpedientePorId(expediente.getId());
            cargarExpedientes();
        }
    }

    private void handleConvertirPDF() {
        int row = expedientesTable.getSelectedRow();
        if (row == -1) {
            mostrarAlerta("Ninguna selección", "Por favor, seleccione un expediente para convertir a PDF.");
            return;
        }
        Expediente expediente = expedienteService.verExpedientes().get(row);
        try {
            j.m.services.PDFDocumentService.crearDocumento(expediente);
            String pdfPath = System.getProperty("user.home") + "/Desktop/ExpedientesPDF/";
            mostrarAlerta("Éxito", "El archivo PDF ha sido creado con éxito en la carpeta:\n" + pdfPath);
        } catch (Exception e) {
            mostrarAlerta("Error de Conversión", "No se pudo crear el archivo PDF: " + e.getMessage());
        }
    }


    private void mostrarAlerta(String titulo, String mensaje) {
        JOptionPane.showMessageDialog(this, mensaje, titulo, JOptionPane.INFORMATION_MESSAGE);
    }

    private void filtrarTabla(String textoBusqueda) {
        tableModel.setRowCount(0);
        List<Expediente> expedientes = expedienteService.verExpedientes();
        for (Expediente exp : expedientes) {
            String numero = exp.getNumero().toLowerCase();
            String materia = exp.getMateria().toLowerCase();
            String demandante = exp.getDemandante().toLowerCase();
            String demandado = exp.getDemandado().toLowerCase();
            String estado = exp.getEstado().toLowerCase();
            
            if (numero.contains(textoBusqueda) || 
                materia.contains(textoBusqueda) || 
                demandante.contains(textoBusqueda) || 
                demandado.contains(textoBusqueda) || 
                estado.contains(textoBusqueda)) {
                tableModel.addRow(new Object[]{exp.getNumero(), exp.getMateria(), exp.getDemandante(), exp.getDemandado(), exp.getEstado()});
            }
        }
    }
}
