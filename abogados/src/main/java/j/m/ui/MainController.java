package j.m.ui;

import java.io.File;
import java.io.IOException;
import java.util.Optional;
import java.util.logging.Level;
import java.util.logging.Logger;

import j.m.models.Expediente;
import j.m.models.Usuario; // <-- Asegúrate de importar la clase Usuario
import j.m.services.ArchivoService;
import j.m.services.ExpedienteService;
import j.m.services.PDFDocumentService;
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
    private static final Logger LOGGER = Logger.getLogger(MainController.class.getName());

    @FXML private TableView<Expediente> expedientesTable;
    @FXML private TableColumn<Expediente, String> numeroColumn;
    @FXML private TableColumn<Expediente, String> materiaColumn;
    @FXML private TableColumn<Expediente, String> demandanteColumn;
    @FXML private TableColumn<Expediente, String> demandadoColumn;
    @FXML private TableColumn<Expediente, String> estadoColumn;

    @FXML private Button editarButton;
    @FXML private Button sincronizarButton;
    @FXML private ToggleButton themeToggle;
    @FXML private Button eliminarButton; // <-- Conexión para el botón de eliminar
    @FXML private javafx.scene.control.TextField busquedaField;
    @FXML
    private void handleBuscarExpediente() {
        String texto = busquedaField.getText().trim();
        if (texto.isEmpty()) {
            cargarExpedientes();
            return;
        }
        // Ahora el filtrado se hace en la base de datos
        expedientesTable.getItems().setAll(expedienteService.buscarPorNumero(texto));
    }

    private final ExpedienteService expedienteService = new ExpedienteService();
    private final ArchivoService archivoService = new ArchivoService();
    
    private File archivoEnEdicion;
    private Expediente expedienteEnEdicion;
    private Usuario usuarioLogueado; // <-- Variable para guardar el usuario actual

    // --- INICIO DE LA MODIFICACIÓN ---
    /**
     * Este método es llamado por el LoginController para pasar el usuario
     * que ha iniciado sesión.
     */
    public void setUsuarioLogueado(Usuario usuario) {
        this.usuarioLogueado = usuario;
        configurarVisibilidadPorRol();
    }

    /**
     * Ajusta la visibilidad de los controles según el rol del usuario.
     */
    private void configurarVisibilidadPorRol() {
        // Por defecto, el botón de eliminar no es visible
        boolean esAdmin = false;
        if (usuarioLogueado != null && usuarioLogueado.getRol() != null) {
            esAdmin = "Admin".equalsIgnoreCase(usuarioLogueado.getRol().getNombre());
        }
        eliminarButton.setVisible(esAdmin);
    }
    // --- FIN DE LA MODIFICACIÓN ---

    @FXML
    public void initialize() {
        numeroColumn.setCellValueFactory(new PropertyValueFactory<>("numero"));
        materiaColumn.setCellValueFactory(new PropertyValueFactory<>("materia"));
        demandanteColumn.setCellValueFactory(new PropertyValueFactory<>("demandante"));
        demandadoColumn.setCellValueFactory(new PropertyValueFactory<>("demandado"));
        estadoColumn.setCellValueFactory(new PropertyValueFactory<>("estado"));
        cargarExpedientes();
        
        // Ocultar el botón de eliminar por defecto al iniciar.
        // Se hará visible si el usuario es Admin.
        eliminarButton.setVisible(false);
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
            Scene dialogScene = new Scene(page);
            // Copia los estilos activos de la ventana principal
            dialogScene.getStylesheets().addAll(themeToggle.getScene().getStylesheets());
            dialogStage.setScene(dialogScene);
            ExpedienteDialogController controller = loader.getController();
            controller.setDialogStage(dialogStage);
            dialogStage.showAndWait();
            if (controller.isGuardado()) {
                cargarExpedientes();
            }
        } catch (IOException e) {
            LOGGER.log(Level.SEVERE, "Error al abrir el diálogo de expediente", e);
            mostrarError("Error inesperado", "No se pudo abrir el diálogo de expediente.");
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
            LOGGER.log(Level.SEVERE, "Error al abrir el documento", e);
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
            // Sincroniza el tema
            Scene ownerScene = themeToggle.getScene();
            if (ownerScene != null) {
                dialog.getDialogPane().getStylesheets().setAll(ownerScene.getStylesheets());
            }
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
            LOGGER.log(Level.SEVERE, "Error al sincronizar el expediente", e);
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
        // Sincroniza el tema
        Scene ownerScene = themeToggle.getScene();
        if (ownerScene != null) {
            alert.getDialogPane().getStylesheets().setAll(ownerScene.getStylesheets());
        }
        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.YES) {
                expedienteService.eliminarExpedientePorId(seleccionado.getId());
                cargarExpedientes();
            }
        });
    }
    
    @FXML
    private void handleConvertirPDF() {
        Expediente seleccionado = expedientesTable.getSelectionModel().getSelectedItem();
        if (seleccionado == null) {
            mostrarAlerta("Ninguna selección", "Por favor, seleccione un expediente para convertir a PDF.");
            return;
        }
        try {
            PDFDocumentService.crearDocumento(seleccionado);
            String pdfPath = System.getProperty("user.home") + "/Desktop/ExpedientesPDF/";
            mostrarAlerta("Éxito", "El archivo PDF ha sido creado con éxito en la carpeta:\n" + pdfPath);
        } catch (IOException e) {
            mostrarError("Error de Conversión", "No se pudo crear el archivo PDF: " + e.getMessage());
            LOGGER.log(Level.SEVERE, "Error al convertir a PDF", e);
        }
    }

    @FXML
    private void handleToggleTheme() {
        Scene scene = themeToggle.getScene();
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
        // Sincroniza el tema
        Scene ownerScene = themeToggle.getScene();
        if (ownerScene != null) {
            alert.getDialogPane().getStylesheets().setAll(ownerScene.getStylesheets());
        }
        alert.showAndWait();
    }

    private void mostrarError(String titulo, String mensaje) {
        Alert alert = new Alert(Alert.AlertType.ERROR, mensaje);
        alert.setTitle(titulo);
        alert.setHeaderText(null);
        // Sincroniza el tema
        Scene ownerScene = themeToggle.getScene();
        if (ownerScene != null) {
            alert.getDialogPane().getStylesheets().setAll(ownerScene.getStylesheets());
        }
        alert.showAndWait();
    }
}
