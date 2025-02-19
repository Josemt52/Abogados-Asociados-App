package j.m.models;

public class Expediente {
    private int id;
    private String numero;
    private String materia;
    private String juzgado;
    private String especialista;
    private String tercero;
    private String demandado;
    private String demandante;
    private String estado;
    private String tipoArchivo;
    private String nombreArchivo;

    // Constructor completo
    public Expediente(int id, String numero, String materia, String juzgado, String especialista, 
                      String tercero, String demandado, String demandante, String estado, 
                      String tipoArchivo, String nombreArchivo) {
        this.id = id;
        this.numero = numero;
        this.materia = materia;
        this.juzgado = juzgado;
        this.especialista = especialista;
        this.tercero = tercero;
        this.demandado = demandado;
        this.demandante = demandante;
        this.estado = estado;
        this.tipoArchivo = tipoArchivo;
        this.nombreArchivo = nombreArchivo;
    }

    // Constructor sin ID (para nuevos expedientes)
    public Expediente(String numero, String materia, String juzgado, String especialista, 
                      String tercero, String demandado, String demandante, String estado, 
                      String tipoArchivo, String nombreArchivo) {
        this.numero = numero;
        this.materia = materia;
        this.juzgado = juzgado;
        this.especialista = especialista;
        this.tercero = tercero;
        this.demandado = demandado;
        this.demandante = demandante;
        this.estado = estado;
        this.tipoArchivo = tipoArchivo;
        this.nombreArchivo = nombreArchivo;
    }

    // Getters y Setters
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

    public String getTipoArchivo() { return tipoArchivo; }
    public void setTipoArchivo(String tipoArchivo) { this.tipoArchivo = tipoArchivo; }

    public String getNombreArchivo() { return nombreArchivo; }
    public void setNombreArchivo(String nombreArchivo) { this.nombreArchivo = nombreArchivo; }
}
