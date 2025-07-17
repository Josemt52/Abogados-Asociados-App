package j.m.ui;

import java.io.IOException;

import j.m.models.Usuario;
import j.m.services.UsuarioService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

public class LoginController {

    @FXML private TextField usernameField;
    @FXML private PasswordField passwordField;
    @FXML private Label statusLabel;

    private final UsuarioService usuarioService = new UsuarioService();

    @FXML
    private void handleLogin() {
        String username = usernameField.getText();
        String password = passwordField.getText();

        if (username.isEmpty() || password.isEmpty()) {
            statusLabel.setText("Por favor, ingrese usuario y contraseña.");
            return;
        }

        Usuario usuario = usuarioService.validarUsuario(username, password);

        if (usuario != null) {
            // Cierra la ventana de login
            Stage loginStage = (Stage) usernameField.getScene().getWindow();
            loginStage.close();

            // Abre la ventana principal
            launchMainApp(usuario);
        } else {
            statusLabel.setText("Usuario o contraseña incorrectos.");
        }
    }

    private void launchMainApp(Usuario usuario) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("MainView.fxml"));
            Parent root = loader.load();

            // Pasa el usuario logueado al MainController
            MainController mainController = loader.getController();
            mainController.setUsuarioLogueado(usuario);

            Stage primaryStage = new Stage();
            primaryStage.setTitle("Gestión de Expedientes - " + usuario.getNombre());
            
            Scene scene = new Scene(root, 900, 600);
            scene.getStylesheets().add(getClass().getResource("light-theme.css").toExternalForm());
            scene.getStylesheets().add(getClass().getResource("styles.css").toExternalForm());
            
            primaryStage.setScene(scene);
            primaryStage.show();

        } catch (IOException e) {
            e.printStackTrace();
        }
    }
}
