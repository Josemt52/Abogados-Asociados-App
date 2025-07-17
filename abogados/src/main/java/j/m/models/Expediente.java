package j.m.models;

import jakarta.persistence.CascadeType;
import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.FetchType;
import jakarta.persistence.GeneratedValue;
import jakarta.persistence.GenerationType;
import jakarta.persistence.Id;
import jakarta.persistence.OneToOne;
import jakarta.persistence.Table;

@Entity
@Table(name = "expedientes")
public class Expediente {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private int id;

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
    
    @OneToOne(mappedBy = "expediente", cascade = CascadeType.ALL, fetch = FetchType.LAZY)
    private Archivo archivo;

    // Getters, setters y constructores...
    public Expediente() {}

    // Getters y Setters...
    public int getId() { return id; }
    public void setId(int id) { this.id = id; }
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
    public Archivo getArchivo() { return archivo; }
    public void setArchivo(Archivo archivo) { this.archivo = archivo; }
} 