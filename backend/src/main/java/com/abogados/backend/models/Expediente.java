package com.abogados.backend.models;

import com.fasterxml.jackson.annotation.JsonManagedReference;
import jakarta.persistence.*;

@Entity
@Table(name = "expedientes")
public class Expediente {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Integer id;

    @Column(unique = true, nullable = false)
    private String numero;
    private String materia;
    private String juzgado;
    private String especialista;
    private String tercero;
    private String demandado;
    private String demandante;

    @Column(name = "estado_actual", columnDefinition = "TEXT")
    private String estado;

    // Campo booleano que indica si tiene archivo adjunto
    @Column(name = "archivo")
    private Boolean archivo;

    // Campo para almacenar el nombre del archivo
    @Column(name = "nombre_archivo")
    private String nombreArchivo;

    // Relación opcional con la entidad Archivo (para datos binarios)
    @OneToOne(mappedBy = "expediente", cascade = CascadeType.ALL, fetch = FetchType.LAZY)
    @JsonManagedReference
    private Archivo archivoData;

    public Expediente() {}

    public Integer getId() { return id; }
    public void setId(Integer id) { this.id = id; }
    public String getNumero() { return numero; }
    public void setNumero(String numero) { this.numero = numero; }
    public String getMateria() { return materia; }
    public void setMateria(String materia) { this.materia = materia; }
    public String getJuzgado() { return juzgado; }
    public void setJuzgado(String juzgado) { this.juzgado = juzgado; }
    public String getEspecialista() { return especialista; }
    public void setEspecialista(String especialista) { this.especialista = especialista; }
    public String getTercero() { return tercero; }
    public void setTercero(String tercero) { this.tercero = tercero; }
    public String getDemandado() { return demandado; }
    public void setDemandado(String demandado) { this.demandado = demandado; }
    public String getDemandante() { return demandante; }
    public void setDemandante(String demandante) { this.demandante = demandante; }
    public String getEstado() { return estado; }
    public void setEstado(String estado) { this.estado = estado; }
    public Boolean getArchivo() { return archivo; }
    public void setArchivo(Boolean archivo) { this.archivo = archivo; }
    public String getNombreArchivo() { return nombreArchivo; }
    public void setNombreArchivo(String nombreArchivo) { this.nombreArchivo = nombreArchivo; }
    public Archivo getArchivoData() { return archivoData; }
    public void setArchivoData(Archivo archivoData) { this.archivoData = archivoData; }
}
