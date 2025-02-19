package j.m.UI;

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Font;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.awt.Insets;
import java.awt.event.ActionEvent;

import javax.swing.BorderFactory;
import javax.swing.JButton;
import javax.swing.JDialog;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JPasswordField;
import javax.swing.JTextField;

import j.m.models.Usuario;
import j.m.services.UsuarioService;

public class LoginForm extends JDialog {
    private JTextField txtUsername;
    private JPasswordField txtPassword;
    private JButton btnLogin, btnCancelar;
    private UsuarioService usuarioService;

    public LoginForm(JFrame parent) {
        super(parent, "Iniciar Sesión", true);
        usuarioService = new UsuarioService();

        setSize(350, 250); // Tamaño compacto
        setLocationRelativeTo(parent);
        setUndecorated(true); // Sin bordes para un diseño más limpio
        setLayout(new BorderLayout());

        // Panel principal con padding
        JPanel panel = new JPanel(new GridBagLayout());
        panel.setBackground(Color.WHITE);
        panel.setBorder(BorderFactory.createEmptyBorder(15, 15, 15, 15));

        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(5, 5, 5, 5);
        gbc.fill = GridBagConstraints.HORIZONTAL;
        
        // Título estilizado
        JLabel lblTitle = new JLabel("Abogados & Asociados", JLabel.CENTER);
        lblTitle.setFont(new Font("Serif", Font.BOLD, 18));
        lblTitle.setForeground(new Color(0, 70, 160));
        gbc.gridwidth = 2;
        gbc.gridx = 0;
        gbc.gridy = 0;
        panel.add(lblTitle, gbc);

        // Usuario
        gbc.gridwidth = 1;
        gbc.gridy = 1;
        gbc.gridx = 0;
        panel.add(new JLabel("Usuario:"), gbc);
        
        txtUsername = new JTextField(15);
        txtUsername.setBorder(BorderFactory.createLineBorder(new Color(0, 70, 160), 1));
        gbc.gridx = 1;
        panel.add(txtUsername, gbc);

        // Contraseña
        gbc.gridy = 2;
        gbc.gridx = 0;
        panel.add(new JLabel("Contraseña:"), gbc);

        txtPassword = new JPasswordField(15);
        txtPassword.setBorder(BorderFactory.createLineBorder(new Color(0, 70, 160), 1));
        gbc.gridx = 1;
        panel.add(txtPassword, gbc);

        // Botón de Login
        btnLogin = new JButton("Ingresar");
        btnLogin.setBackground(new Color(0, 70, 160));
        btnLogin.setForeground(Color.WHITE);
        btnLogin.setBorder(BorderFactory.createEmptyBorder(5, 10, 5, 10));
        btnLogin.addActionListener(this::validarLogin);

        // Botón Cancelar
        btnCancelar = new JButton("Cancelar");
        btnCancelar.setBackground(new Color(200, 0, 0));
        btnCancelar.setForeground(Color.WHITE);
        btnCancelar.setBorder(BorderFactory.createEmptyBorder(5, 10, 5, 10));
        btnCancelar.addActionListener(e -> dispose());

        // Panel de botones
        JPanel panelBotones = new JPanel();
        panelBotones.setBackground(Color.WHITE);
        panelBotones.add(btnLogin);
        panelBotones.add(btnCancelar);

        gbc.gridy = 3;
        gbc.gridx = 0;
        gbc.gridwidth = 2;
        panel.add(panelBotones, gbc);

        add(panel);
    }

    // Método para validar login
    private void validarLogin(ActionEvent e) {
        String username = txtUsername.getText().trim();
        String password = new String(txtPassword.getPassword()).trim();

        Usuario usuario = usuarioService.validarUsuario(username, password);

        if (usuario != null) {
            JOptionPane.showMessageDialog(this, "Bienvenido, " + usuario.getNombre(), "Éxito", JOptionPane.INFORMATION_MESSAGE);
            dispose(); // Cierra el formulario
            new MainPanelFrame().setVisible(true); // Abre la ventana principal
        } else {
            JOptionPane.showMessageDialog(this, "Usuario o contraseña incorrectos.", "Error", JOptionPane.ERROR_MESSAGE);
        }
    }
}
