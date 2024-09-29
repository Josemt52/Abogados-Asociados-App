package j.m;

import java.util.Scanner;

public class Expediente {
    private String numero;
    private Materia materia;
    private Persona juez;
    private Persona especialista;
    private Persona tercero;
    private Persona demandado;
    private Persona demandante;
    private Estado estado;

    public Expediente(String numero, Materia materia, Persona juez, Persona especialista, Persona tercero,
                      Persona demandado, Persona demandante, Estado estado) {
        this.numero = numero;
        this.materia = materia;
        this.juez = juez;
        this.especialista = especialista;
        this.tercero = tercero;
        this.demandado = demandado;
        this.demandante = demandante;
        this.estado = estado;
    }

    public static Expediente crearExpediente() {
        Scanner scanner = new Scanner(System.in);

        System.out.print("Ingrese el número de expediente: ");
        String numero = scanner.nextLine();

        System.out.print("Ingrese la materia: ");
        String nombreMateria = scanner.nextLine();
        Materia materia = new Materia(nombreMateria);  // Crear objeto Materia

        System.out.print("Ingrese el nombre del Juzgado: ");
        String nombreJuez = scanner.nextLine();
        Persona juez = new Persona(nombreJuez);  // Crear objeto Persona para juez

        System.out.print("Ingrese el nombre del especialista: ");
        String nombreEspecialista = scanner.nextLine();
        Persona especialista = new Persona(nombreEspecialista);  // Crear objeto Persona para especialista

        System.out.print("Ingrese el nombre del tercero: ");
        String nombreTercero = scanner.nextLine();
        Persona tercero = new Persona(nombreTercero);  // Crear objeto Persona para tercero

        System.out.print("Ingrese el nombre del demandado: ");
        String nombreDemandado = scanner.nextLine();
        Persona demandado = new Persona(nombreDemandado);  // Crear objeto Persona para demandado

        System.out.print("Ingrese el nombre del demandante: ");
        String nombreDemandante = scanner.nextLine();
        Persona demandante = new Persona(nombreDemandante);  // Crear objeto Persona para demandante

        System.out.print("Ingrese el estado del expediente: ");
        String descripcionEstado = scanner.nextLine();
        Estado estado = new Estado(descripcionEstado);  // Crear objeto Estado

        return new Expediente(numero, materia, juez, especialista, tercero, demandado, demandante, estado);
    }

    public void mostrarExpediente() {
        System.out.println("EXPEDIENTE: " + numero);
        System.out.println("MATERIA: " + materia.getNombre());
        System.out.println("JUZGADO: " + juez.getNombre());
        System.out.println("ESPECIALISTA: " + especialista.getNombre());
        System.out.println("TERCERO: " + tercero.getNombre());
        System.out.println("DEMANDADO: " + demandado.getNombre());
        System.out.println("DEMANDANTE: " + demandante.getNombre());
        System.out.println("ESTADO: " + estado.getDescripcion());
    }

    public String getNumero() {
        return numero;
    }

    public Materia getMateria() {
        return materia;
    }

    public Persona getJuez() {
        return juez;
    }

    public Persona getEspecialista() {
        return especialista;
    }

    public Persona getTercero() {
        return tercero;
    }

    public Persona getDemandado() {
        return demandado;
    }

    public Persona getDemandante() {
        return demandante;
    }

    public Estado getEstado() {
        return estado;
    }
}
