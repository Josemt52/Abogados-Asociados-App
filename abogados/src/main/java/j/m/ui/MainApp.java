package j.m.ui;

import java.net.URL;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class MainApp extends Application {

    @Override
public void start(Stage primaryStage) throws Exception {
    FXMLLoader loader = new FXMLLoader();
    URL fxmlLocation = getClass().getResource("MainView.fxml");

    if (fxmlLocation == null) {
        throw new IllegalStateException("No se pudo encontrar 'MainView.fxml'. Asegúrate de que esté en la carpeta 'src/main/resources/j/m/ui/'.");
    }

    loader.setLocation(fxmlLocation);
    Parent root = loader.load();
    
    // 1. Creamos la escena y la guardamos en la variable 'scene'
    Scene scene = new Scene(root, 900, 600);
    
    scene.getStylesheets().add(getClass().getResource("light-theme.css").toExternalForm());
    
    // 2. Usamos la variable 'scene' para añadirle los estilos
    scene.getStylesheets().add(getClass().getResource("styles.css").toExternalForm());
    
    // 3. Establecemos la escena ya modificada en la ventana
    primaryStage.setTitle("Gestión de Expedientes");
    primaryStage.setScene(scene);
    primaryStage.show();
}}