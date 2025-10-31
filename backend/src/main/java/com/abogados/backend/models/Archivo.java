package com.abogados.backend.models;

import com.fasterxml.jackson.annotation.JsonBackReference;
import com.fasterxml.jackson.annotation.JsonIgnore;
import jakarta.persistence.*;

@Entity
@Table(name = "archivos")
public class Archivo {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Integer id;

    @Column(name = "nombre_archivo")
    private String nombreArchivo;

    @Column(name = "tipo_archivo")
    private String tipoArchivo;

    // No incluir los datos binarios en las respuestas JSON
    @JsonIgnore
    @Lob
    @Column(name = "documento_data", columnDefinition = "LONGBLOB")
    private byte[] documentoData;

    @OneToOne
    @JoinColumn(name = "expediente_id", referencedColumnName = "id")
    @JsonBackReference
    private Expediente expediente;

    public Archivo() {}

    public Integer getId() { return id; }
    public void setId(Integer id) { this.id = id; }
    public String getNombreArchivo() { return nombreArchivo; }
    public void setNombreArchivo(String nombreArchivo) { this.nombreArchivo = nombreArchivo; }
    public String getTipoArchivo() { return tipoArchivo; }
    public void setTipoArchivo(String tipoArchivo) { this.tipoArchivo = tipoArchivo; }
    public byte[] getDocumentoData() { return documentoData; }
    public void setDocumentoData(byte[] documentoData) { this.documentoData = documentoData; }
    public Expediente getExpediente() { return expediente; }
    public void setExpediente(Expediente expediente) { this.expediente = expediente; }
}
