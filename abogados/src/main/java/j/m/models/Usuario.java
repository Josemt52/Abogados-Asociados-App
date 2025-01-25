package j.m.models;

public class Usuario {
    private int id;
    private String nombre;
    private String username;
    private String password;
    private String rol;

    public Usuario(int id, String nombre, String username, String password, String rol) {
        this.id = id;
        this.nombre = nombre;
        this.username = username;
        this.password = password;
        this.rol = rol;
    }

    public int getId() {
        return id;
    }

    public String getNombre() {
        return nombre;
    }

    public String getUsername() {
        return username;
    }

    public String getRol() {
        return rol;
    }

    public void setRol(String rol) {
        this.rol = rol;
    }
}