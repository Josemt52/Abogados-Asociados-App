package j.m.ui;

import j.m.models.Archivo;
import j.m.models.Expediente;
import j.m.services.ArchivoService;
import j.m.services.ExpedienteService;
import javafx.fxml.FXML;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

public class ExpedienteDialogController {

    @FXML private TextField numeroField;
    @FXML private TextField materiaField;
    @FXML private TextField juzgadoField;
    @FXML private TextField especialistaField;
    @FXML private TextField terceroField;
    @FXML private TextField demandadoField;
    @FXML private TextField demandanteField;
    @FXML private TextField estadoActualField;
    @FXML private TextField nombreArchivoField;

    private final ExpedienteService expedienteService = new ExpedienteService();
    private final ArchivoService archivoService = new ArchivoService();
    private Stage dialogStage;
    private boolean guardado = false;

    public void setDialogStage(Stage stage) { this.dialogStage = stage; }
    public boolean isGuardado() { return guardado; }

    @FXML
private void handleGuardar() {
    try {
        Expediente nuevoExpediente = new Expediente();
        nuevoExpediente.setNumero(numeroField.getText());
        nuevoExpediente.setMateria(materiaField.getText());
        nuevoExpediente.setJuzgado(juzgadoField.getText());
        nuevoExpediente.setEspecialista(especialistaField.getText());
        nuevoExpediente.setTercero(terceroField.getText());
        nuevoExpediente.setDemandado(demandadoField.getText());
        nuevoExpediente.setDemandante(demandanteField.getText());
        nuevoExpediente.setEstado(estadoActualField.getText());
        
        // La llamada ahora solo necesita el expediente y el nombre del archivo
        Archivo nuevoArchivo = archivoService.prepararArchivoInicial(
            nuevoExpediente,
            nombreArchivoField.getText()
        );
        
        nuevoExpediente.setArchivo(nuevoArchivo);
        nuevoArchivo.setExpediente(nuevoExpediente);

        expedienteService.crearOActualizar(nuevoExpediente);

        guardado = true;
        dialogStage.close();
    } catch (Exception e) {
        e.printStackTrace();
    }
}

    @FXML
    private void handleCancelar() {
        dialogStage.close();
    }
}