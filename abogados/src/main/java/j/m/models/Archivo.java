package j.m.models;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.GeneratedValue;
import jakarta.persistence.GenerationType;
import jakarta.persistence.Id;
import jakarta.persistence.JoinColumn;
import jakarta.persistence.OneToOne;
import jakarta.persistence.Table;

@Entity
@Table(name = "archivos")
public class Archivo {
    
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private int id;

    @Column(name = "nombre_archivo")
    private String nombreArchivo;

    @Column(name = "tipo_archivo")
    private String tipoArchivo;

    // --- LA MODIFICACIÓN ESTÁ AQUÍ ---
    // Hemos quitado la anotación @Lob y añadido la longitud máxima
    // Esto guía a Hibernate a usar un método compatible con SQLite
    @Column(name = "documento_data", length = 10485760) // 10 MB
    private byte[] documentoData;

    @OneToOne
    @JoinColumn(name = "expediente_numero", referencedColumnName = "numero")
    private Expediente expediente;

    // Constructor vacío requerido por JPA
    public Archivo() {}

    // Getters y Setters (sin cambios)
    public int getId() { return id; }
    public void setId(int id) { this.id = id; }
    public String getNombreArchivo() { return nombreArchivo; }
    public void setNombreArchivo(String nombreArchivo) { this.nombreArchivo = nombreArchivo; }
    public String getTipoArchivo() { return tipoArchivo; }
    public void setTipoArchivo(String tipoArchivo) { this.tipoArchivo = tipoArchivo; }
    public byte[] getDocumentoData() { return documentoData; }
    public void setDocumentoData(byte[] documentoData) { this.documentoData = documentoData; }
    public Expediente getExpediente() { return expediente; }
    public void setExpediente(Expediente expediente) { this.expediente = expediente; }
}