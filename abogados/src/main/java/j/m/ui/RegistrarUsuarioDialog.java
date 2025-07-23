package j.m.ui;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Component;
import java.awt.Dimension;
import java.awt.Font;

import javax.swing.BorderFactory;
import javax.swing.Box;
import javax.swing.BoxLayout;
import javax.swing.JButton;
import javax.swing.JComboBox;
import javax.swing.JDialog;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JPanel;
import javax.swing.JPasswordField;
import javax.swing.JTextField;

import j.m.models.Rol;
import j.m.models.Usuario;
import j.m.services.UsuarioService;
import j.m.utils.JPAUtil;
import jakarta.persistence.EntityManager;
import jakarta.persistence.TypedQuery;

public class RegistrarUsuarioDialog extends JDialog {
    private JTextField nombreField, usernameField;
    private JPasswordField passwordField;
    private JComboBox<String> rolCombo;
    private JLabel statusLabel;
    private boolean registrado = false;

    public RegistrarUsuarioDialog(JFrame parent) {
        super(parent, "Registrar Usuario", true);
        setSize(350, 320);
        setLocationRelativeTo(parent);
        setLayout(new BorderLayout());

        JPanel formPanel = new JPanel();
        formPanel.setLayout(new BoxLayout(formPanel, BoxLayout.Y_AXIS));
        formPanel.setBorder(BorderFactory.createEmptyBorder(20, 30, 20, 30));

        JLabel titleLabel = new JLabel("Nuevo Usuario");
        titleLabel.setFont(new Font("Arial", Font.BOLD, 18));
        titleLabel.setAlignmentX(Component.CENTER_ALIGNMENT);
        formPanel.add(titleLabel);
        formPanel.add(Box.createVerticalStrut(15));

        formPanel.add(new JLabel("Nombre:"));
        nombreField = new JTextField(16);
        nombreField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 28));
        formPanel.add(nombreField);
        formPanel.add(Box.createVerticalStrut(10));

        formPanel.add(new JLabel("Usuario:"));
        usernameField = new JTextField(16);
        usernameField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 28));
        formPanel.add(usernameField);
        formPanel.add(Box.createVerticalStrut(10));

        formPanel.add(new JLabel("Contraseña:"));
        passwordField = new JPasswordField(16);
        passwordField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 28));
        formPanel.add(passwordField);
        formPanel.add(Box.createVerticalStrut(10));

        formPanel.add(new JLabel("Rol:"));
        rolCombo = new JComboBox<>();
        for (String rol : getRoles()) {
            rolCombo.addItem(rol);
        }
        rolCombo.setMaximumSize(new Dimension(Integer.MAX_VALUE, 28));
        rolCombo.setSelectedIndex(-1); // No selecciona ninguno por defecto
        formPanel.add(rolCombo);
        formPanel.add(Box.createVerticalStrut(10));

        statusLabel = new JLabel("");
        statusLabel.setForeground(Color.RED);
        statusLabel.setAlignmentX(Component.CENTER_ALIGNMENT);
        formPanel.add(statusLabel);
        formPanel.add(Box.createVerticalStrut(10));

        JButton registrarButton = new JButton("Registrar");
        registrarButton.setBackground(Color.WHITE);
        registrarButton.setForeground(Color.BLACK);
        registrarButton.setFont(new Font("Arial", Font.BOLD, 14));
        registrarButton.setFocusPainted(false);
        registrarButton.setAlignmentX(Component.CENTER_ALIGNMENT);
        registrarButton.addActionListener(e -> handleRegistrar());
        formPanel.add(registrarButton);

        add(formPanel, BorderLayout.CENTER);
    }

    private String[] getRoles() {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            TypedQuery<Rol> query = em.createQuery("SELECT r FROM Rol r", Rol.class);
            java.util.List<Rol> roles = query.getResultList();
            return roles.stream().map(Rol::getNombre).toArray(String[]::new);
        } finally {
            em.close();
        }
    }

    private void handleRegistrar() {
        String nombre = nombreField.getText().trim();
        String username = usernameField.getText().trim();
        String password = new String(passwordField.getPassword());
        String rolNombre = (String) rolCombo.getSelectedItem();

        if (nombre.isEmpty() || username.isEmpty() || password.isEmpty() || rolNombre == null) {
            statusLabel.setText("Todos los campos son obligatorios.");
            return;
        }

        EntityManager em = JPAUtil.getEntityManager();
        try {
            TypedQuery<Rol> query = em.createQuery("SELECT r FROM Rol r WHERE r.nombre = :nombre", Rol.class);
            query.setParameter("nombre", rolNombre);
            Rol rol = query.getSingleResult();

            Usuario usuario = new Usuario();
            usuario.setNombre(nombre);
            usuario.setUsername(username);
            usuario.setPassword(password);
            usuario.setRol(rol);

            UsuarioService service = new UsuarioService();
            service.crearUsuario(usuario);
            registrado = true;
            statusLabel.setForeground(new Color(0, 120, 0));
            statusLabel.setText("Usuario registrado correctamente.");
            dispose();
        } catch (Exception ex) {
            statusLabel.setText("Error: " + ex.getMessage());
        } finally {
            em.close();
        }
    }

    public boolean isRegistrado() {
        return registrado;
    }
}
