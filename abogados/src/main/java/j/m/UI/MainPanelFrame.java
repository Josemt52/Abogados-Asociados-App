package j.m.UI;

import java.awt.BorderLayout;
import java.awt.CardLayout;
import java.awt.Color;
import java.awt.Cursor;
import java.awt.Dimension;
import java.awt.Font;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

import javax.swing.BorderFactory;
import javax.swing.Box;
import javax.swing.BoxLayout;
import javax.swing.JButton;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JPanel;
import javax.swing.SwingConstants;
import javax.swing.SwingUtilities;

public class MainPanelFrame extends JFrame {
    private JPanel contentPanel;
    private CardLayout contentLayout;
    private ExpedienteForm expedienteForm;
    private VerExpedientesPanel verExpedientesPanel;
    private BuscarExpedientesPanel buscarExpedientesPanel;
    private JLabel dateText;

    public MainPanelFrame() {
        setTitle("Abogados & Asociados");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(1100, 700);
        setLocationRelativeTo(null);
        setLayout(new BorderLayout());

        // 🔹 ENCABEZADO SUPERIOR
        JPanel headerPanel = new JPanel(new BorderLayout());
        headerPanel.setBackground(new Color(25, 118, 210));
        headerPanel.setBorder(BorderFactory.createEmptyBorder(15, 20, 15, 20));

        JLabel lblTitulo = new JLabel("Abogados & Asociados", SwingConstants.CENTER);
        lblTitulo.setFont(new Font("SansSerif", Font.BOLD, 28));
        lblTitulo.setForeground(Color.WHITE);
        headerPanel.add(lblTitulo, BorderLayout.CENTER);

        // 🔹 BARRA DE INFORMACIÓN (Descripción y Fecha)
        JPanel infoPanel = new JPanel(new BorderLayout());
        infoPanel.setBackground(new Color(30, 136, 229));
        infoPanel.setBorder(BorderFactory.createEmptyBorder(10, 20, 10, 20));

        JLabel lblDescripcion = new JLabel("Administración / Control / Expedientes");
        lblDescripcion.setFont(new Font("Arial", Font.BOLD, 14));
        lblDescripcion.setForeground(Color.WHITE);

        dateText = new JLabel();
        dateText.setFont(new Font("Arial", Font.PLAIN, 14));
        dateText.setForeground(Color.WHITE);
        setDate();

        infoPanel.add(lblDescripcion, BorderLayout.WEST);
        infoPanel.add(dateText, BorderLayout.EAST);

        // 🔹 WRAPPER PARA ENCABEZADO E INFO PANEL
        JPanel topWrapper = new JPanel(new BorderLayout());
        topWrapper.add(headerPanel, BorderLayout.NORTH);
        topWrapper.add(infoPanel, BorderLayout.SOUTH);
        add(topWrapper, BorderLayout.NORTH);

        // 🔹 MENÚ LATERAL (Corregido)
        JPanel menuPanel = new JPanel();
        menuPanel.setLayout(new BoxLayout(menuPanel, BoxLayout.Y_AXIS));
        menuPanel.setBackground(new Color(13, 71, 161));
        menuPanel.setPreferredSize(new Dimension(220, getHeight()));

        menuPanel.setBorder(BorderFactory.createEmptyBorder(10, 10, 10, 10));

        // 🔹 Botones del menú lateral
        JButton btnPrincipal = createMenuButton("🏠 Principal");
        JButton btnCrear = createMenuButton("📂 Crear Expediente");
        JButton btnVer = createMenuButton("📑 Ver Expedientes");
        JButton btnBuscar = createMenuButton("🔍 Buscar y Editar");
        JButton btnPDF = createMenuButton("📄 Convertir en PDF");
        JButton btnEliminar = createMenuButton("🗑 Eliminar Expediente");

        menuPanel.add(btnPrincipal);
        menuPanel.add(Box.createRigidArea(new Dimension(0, 5)));
        menuPanel.add(btnCrear);
        menuPanel.add(Box.createRigidArea(new Dimension(0, 5)));
        menuPanel.add(btnVer);
        menuPanel.add(Box.createRigidArea(new Dimension(0, 5)));
        menuPanel.add(btnBuscar);
        menuPanel.add(Box.createRigidArea(new Dimension(0, 5)));
        menuPanel.add(btnPDF);
        menuPanel.add(Box.createRigidArea(new Dimension(0, 5)));
        menuPanel.add(btnEliminar);

        add(menuPanel, BorderLayout.WEST);

        // 🔹 PANEL PRINCIPAL DE CONTENIDO (Corrección: Ahora en CENTER)
        contentPanel = new JPanel(new CardLayout());
        contentLayout = (CardLayout) contentPanel.getLayout();
        contentPanel.setBackground(Color.WHITE);

        JPanel panelPrincipal = new JPanel(new BorderLayout());
        panelPrincipal.setBackground(Color.WHITE);

        JLabel lblBienvenida = new JLabel("Bienvenido a Abogados & Asociados", SwingConstants.CENTER);
        lblBienvenida.setFont(new Font("SansSerif", Font.BOLD, 22));
        lblBienvenida.setForeground(new Color(13, 71, 161));
        panelPrincipal.add(lblBienvenida, BorderLayout.CENTER);

        expedienteForm = new ExpedienteForm();
        verExpedientesPanel = new VerExpedientesPanel();
        buscarExpedientesPanel = new BuscarExpedientesPanel();

        contentPanel.add(panelPrincipal, "Principal");
        contentPanel.add(expedienteForm, "Crear");
        contentPanel.add(verExpedientesPanel, "Ver");
        contentPanel.add(buscarExpedientesPanel, "buscar");

        add(contentPanel, BorderLayout.CENTER); // ✅ Se corrigió para que ocupe el espacio restante

        // 🔹 EVENTOS DE LOS BOTONES
        btnPrincipal.addActionListener(e -> mostrarPanel("Principal"));
        btnCrear.addActionListener(e -> mostrarPanel("Crear"));
        btnVer.addActionListener(e -> mostrarPanel("Ver"));
        btnBuscar.addActionListener(e -> mostrarPanel("buscar"));

        setVisible(true);
    }

    // 🔹 Método para actualizar la fecha
    private void setDate() {
        LocalDate now = LocalDate.now();
        Locale spanishLocale = new Locale("es", "ES");
        dateText.setText(now.format(DateTimeFormatter.ofPattern("'Hoy es' EEEE dd 'de' MMMM 'de' yyyy", spanishLocale)));
    }

    // 🔹 Método para crear botones estilizados del menú
    private JButton createMenuButton(String text) {
        JButton button = new JButton(text);
        button.setFont(new Font("Arial", Font.BOLD, 14));
        button.setForeground(Color.WHITE);
        button.setBackground(new Color(21, 101, 192));
        button.setFocusPainted(false);
        button.setBorderPainted(false);
        button.setOpaque(true);
        button.setCursor(new Cursor(Cursor.HAND_CURSOR));

        // Efecto hover
        button.addMouseListener(new java.awt.event.MouseAdapter() {
            public void mouseEntered(java.awt.event.MouseEvent evt) {
                button.setBackground(new Color(30, 136, 229));
            }

            public void mouseExited(java.awt.event.MouseEvent evt) {
                button.setBackground(new Color(21, 101, 192));
            }
        });

        return button;
    }

    // 🔹 Método para cambiar entre paneles
    private void mostrarPanel(String panelName) {
        contentLayout.show(contentPanel, panelName);
    }

    public static void main(String[] args) {
        SwingUtilities.invokeLater(() -> {
            LoginForm login = new LoginForm(null);
            login.setVisible(true);
        });
    }
}
