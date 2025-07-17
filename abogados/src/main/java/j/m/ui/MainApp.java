package j.m.ui;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class MainApp extends Application {

    @Override
    public void start(Stage primaryStage) throws Exception {
        FXMLLoader loader = new FXMLLoader(getClass().getResource("/j/m/ui/LoginView.fxml"));
        Parent root = loader.load();

        Scene scene = new Scene(root, 400, 300);
        scene.getStylesheets().add(getClass().getResource("/j/m/ui/light-theme.css").toExternalForm());
        scene.getStylesheets().add(getClass().getResource("/j/m/ui/styles.css").toExternalForm());

        primaryStage.setTitle("Inicio de Sesión");
        primaryStage.setScene(scene);
        primaryStage.show();
    }
}