package j.m.ui;

import java.io.File;
import java.io.IOException;
import java.util.Optional;

import j.m.models.Expediente;
import j.m.services.ArchivoService;
import j.m.services.ExpedienteService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ButtonType;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.control.TextInputDialog;
import javafx.scene.control.ToggleButton;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.GridPane;
import javafx.stage.Modality;
import javafx.stage.Stage;

public class MainController {

    @FXML private TableView<Expediente> expedientesTable;
    @FXML private TableColumn<Expediente, String> numeroColumn;
    @FXML private TableColumn<Expediente, String> materiaColumn;
    @FXML private TableColumn<Expediente, String> demandanteColumn;
    @FXML private TableColumn<Expediente, String> demandadoColumn;
    @FXML private TableColumn<Expediente, String> estadoColumn;
    
    @FXML private Button editarButton;
    @FXML private Button sincronizarButton;
    @FXML private ToggleButton themeToggle; // Conexión para el botón de tema

    private final ExpedienteService expedienteService = new ExpedienteService();
    private final ArchivoService archivoService = new ArchivoService();
    
    private File archivoEnEdicion;
    private Expediente expedienteEnEdicion;

    @FXML
    public void initialize() {
        numeroColumn.setCellValueFactory(new PropertyValueFactory<>("numero"));
        materiaColumn.setCellValueFactory(new PropertyValueFactory<>("materia"));
        demandanteColumn.setCellValueFactory(new PropertyValueFactory<>("demandante"));
        demandadoColumn.setCellValueFactory(new PropertyValueFactory<>("demandado"));
        estadoColumn.setCellValueFactory(new PropertyValueFactory<>("estado"));
        cargarExpedientes();
    }

    private void cargarExpedientes() {
        expedientesTable.getItems().setAll(expedienteService.verExpedientes());
    }

    @FXML
    private void handleCrearExpediente() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("ExpedienteDialog.fxml"));
            GridPane page = loader.load();
            Stage dialogStage = new Stage();
            dialogStage.setTitle("Crear Nuevo Expediente");
            dialogStage.initModality(Modality.WINDOW_MODAL);
            dialogStage.initOwner(expedientesTable.getScene().getWindow());
            dialogStage.setScene(new Scene(page));
            ExpedienteDialogController controller = loader.getController();
            controller.setDialogStage(dialogStage);
            dialogStage.showAndWait();
            if (controller.isGuardado()) {
                cargarExpedientes();
            }
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    @FXML
    private void handleEditarExpediente() {
        expedienteEnEdicion = expedientesTable.getSelectionModel().getSelectedItem();
        if (expedienteEnEdicion == null) {
            mostrarAlerta("Ninguna selección", "Por favor, seleccione un expediente para editar.");
            return;
        }
        try {
            archivoEnEdicion = archivoService.getArchivoDelEscritorio(expedienteEnEdicion.getArchivo());
            archivoService.abrirArchivoParaEditar(archivoEnEdicion);
            
            editarButton.setDisable(true);
            sincronizarButton.setDisable(false);
            sincronizarButton.getStyleClass().add("pulsing-button");

            mostrarAlerta("Edición en Progreso", "El documento se ha abierto. Cuando termines, guarda los cambios en Word y presiona 'Sincronizar Cambios'.");
        } catch (Exception e) {
            mostrarError("Error al abrir", "No se pudo abrir el documento: " + e.getMessage());
            e.printStackTrace();
        }
    }

    @FXML
    private void handleSincronizar() {
        if (expedienteEnEdicion == null || archivoEnEdicion == null) {
            mostrarError("Error de Sincronización", "No hay ningún archivo en modo de edición.");
            return;
        }
        try {
            byte[] nuevosDatos = archivoService.leerBytesDeArchivo(archivoEnEdicion);
            expedienteEnEdicion.getArchivo().setDocumentoData(nuevosDatos);
            
            TextInputDialog dialog = new TextInputDialog(expedienteEnEdicion.getEstado());
            dialog.setTitle("Actualizar Estado");
            dialog.setContentText("Resumen del nuevo estado:");
            Optional<String> resultado = dialog.showAndWait();
            resultado.ifPresent(expedienteEnEdicion::setEstado);

            expedienteService.crearOActualizar(expedienteEnEdicion);

            expedienteEnEdicion = null;
            archivoEnEdicion = null;
            
            editarButton.setDisable(false);
            sincronizarButton.setDisable(true);
            sincronizarButton.getStyleClass().remove("pulsing-button");

            cargarExpedientes();
            mostrarAlerta("Éxito", "El documento ha sido sincronizado.");
        } catch (Exception e) {
            mostrarError("Error al Sincronizar", e.getMessage());
            e.printStackTrace();
        }
    }

    @FXML
    private void handleEliminarExpediente() {
        Expediente seleccionado = expedientesTable.getSelectionModel().getSelectedItem();
        if (seleccionado == null) {
            mostrarAlerta("Ninguna selección", "Por favor, seleccione un expediente.");
            return;
        }
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION, "¿Seguro que desea eliminar el expediente " + seleccionado.getNumero() + "?", ButtonType.YES, ButtonType.NO);
        alert.setTitle("Confirmar Eliminación");
        alert.setHeaderText(null);
        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.YES) {
                expedienteService.eliminarExpedientePorId(seleccionado.getId());
                cargarExpedientes();
            }
        });
    }
    
    @FXML
    private void handleConvertirPDF() {
        mostrarAlerta("Función no implementada", "La conversión a PDF se añadirá en una futura versión.");
    }

    // --- MÉTODO FINAL QUE FALTABA ---
    @FXML
    private void handleToggleTheme() {
        Scene scene = themeToggle.getScene();
        // Limpia cualquier hoja de estilo de tema anterior para evitar conflictos
        scene.getStylesheets().removeIf(css -> css.contains("theme.css"));

        if (themeToggle.isSelected()) {
            scene.getStylesheets().add(getClass().getResource("dark-theme.css").toExternalForm());
            themeToggle.setText("Modo Claro");
        } else {
            scene.getStylesheets().add(getClass().getResource("light-theme.css").toExternalForm());
            themeToggle.setText("Modo Oscuro");
        }
    }

    private void mostrarAlerta(String titulo, String mensaje) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION, mensaje);
        alert.setTitle(titulo);
        alert.setHeaderText(null);
        alert.showAndWait();
    }

    private void mostrarError(String titulo, String mensaje) {
        Alert alert = new Alert(Alert.AlertType.ERROR, mensaje);
        alert.setTitle(titulo);
        alert.setHeaderText(null);
        alert.showAndWait();
    }
}