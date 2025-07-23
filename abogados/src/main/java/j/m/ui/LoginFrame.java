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
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JPasswordField;
import javax.swing.JTextField;
import javax.swing.UIManager;

import j.m.models.Usuario;
import j.m.services.UsuarioService;

public class LoginFrame extends JFrame {
    private JTextField usernameField;
    private JPasswordField passwordField;
    private JLabel statusLabel;
    private UsuarioService usuarioService = new UsuarioService();

    public LoginFrame() {
        setTitle("Inicio de Sesión");
        setSize(400, 320);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setLocationRelativeTo(null);
        setLayout(new BorderLayout());

        JPanel formPanel = new JPanel();
        formPanel.setLayout(new BoxLayout(formPanel, BoxLayout.Y_AXIS));
        formPanel.setBorder(BorderFactory.createCompoundBorder(
            BorderFactory.createEmptyBorder(20, 40, 20, 40),
            BorderFactory.createLineBorder(new Color(180, 180, 180), 1)
        ));

        JLabel titleLabel = new JLabel("Bienvenido");
        titleLabel.setFont(new Font("Arial", Font.BOLD, 22));
        titleLabel.setAlignmentX(Component.CENTER_ALIGNMENT);
        formPanel.add(titleLabel);
        formPanel.add(Box.createVerticalStrut(15));

        JLabel userLabel = new JLabel("Usuario:");
        userLabel.setAlignmentX(Component.CENTER_ALIGNMENT);
        formPanel.add(userLabel);
        usernameField = new JTextField(16);
        usernameField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 28));
        formPanel.add(usernameField);
        formPanel.add(Box.createVerticalStrut(10));

        JLabel passLabel = new JLabel("Contraseña:");
        passLabel.setAlignmentX(Component.CENTER_ALIGNMENT);
        formPanel.add(passLabel);
        passwordField = new JPasswordField(16);
        passwordField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 28));
        formPanel.add(passwordField);
        formPanel.add(Box.createVerticalStrut(10));

        statusLabel = new JLabel("");
        statusLabel.setForeground(Color.RED);
        statusLabel.setAlignmentX(Component.CENTER_ALIGNMENT);
        formPanel.add(statusLabel);
        formPanel.add(Box.createVerticalStrut(10));

        JButton loginButton = new JButton("Ingresar");
        loginButton.setBackground(new Color(0, 120, 215));
        loginButton.setForeground(Color.WHITE);
        loginButton.setFocusPainted(false);
        loginButton.setFont(new Font("Arial", Font.BOLD, 14));
        loginButton.setAlignmentX(Component.CENTER_ALIGNMENT);
        loginButton.setIcon(UIManager.getIcon("FileView.fileIcon"));
        loginButton.addActionListener(e -> handleLogin());
        formPanel.add(loginButton);

        formPanel.add(Box.createVerticalStrut(8));
        JButton registrarButton = new JButton("Registrar");
        registrarButton.setBackground(Color.WHITE);
        registrarButton.setForeground(Color.BLACK);
        registrarButton.setFocusPainted(false);
        registrarButton.setFont(new Font("Arial", Font.BOLD, 14));
        registrarButton.setAlignmentX(Component.CENTER_ALIGNMENT);
        registrarButton.setIcon(UIManager.getIcon("FileView.directoryIcon"));
        registrarButton.addActionListener(e -> mostrarRegistrarUsuario());
        formPanel.add(registrarButton);

        add(formPanel, BorderLayout.CENTER);
        setVisible(true);
    }

    private void handleLogin() {
        String username = usernameField.getText();
        String password = new String(passwordField.getPassword());

        if (username.isEmpty() || password.isEmpty()) {
            statusLabel.setText("Por favor, ingrese usuario y contraseña.");
            return;
        }

        Usuario usuario = usuarioService.validarUsuario(username, password);
        if (usuario != null) {
            dispose();
            new MainFrame(usuario);
        } else {
            statusLabel.setText("Usuario o contraseña incorrectos.");
        }
    }

    private void mostrarRegistrarUsuario() {
        String masterPassword = JOptionPane.showInputDialog(this, "Ingrese la contraseña de administrador para registrar usuarios:", "Seguridad", JOptionPane.WARNING_MESSAGE);
        if (masterPassword == null) return;
        // Cambia aquí la contraseña maestra según tu preferencia
        if (!masterPassword.equals("admin2025")) {
            JOptionPane.showMessageDialog(this, "Contraseña incorrecta.", "Acceso denegado", JOptionPane.ERROR_MESSAGE);
            return;
        }
        RegistrarUsuarioDialog dialog = new RegistrarUsuarioDialog(this);
        dialog.setVisible(true);
        if (dialog.isRegistrado()) {
            statusLabel.setForeground(new Color(0, 120, 0));
            statusLabel.setText("Usuario registrado correctamente. Ahora puede iniciar sesión.");
        }
    }
}
