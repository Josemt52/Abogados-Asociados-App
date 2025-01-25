package j.m.utils;

import java.io.IOException;
import java.util.List;
import java.util.Scanner;

import j.m.models.Usuario;
import j.m.services.ArchivoService;
import j.m.services.ExpedienteService;
import j.m.services.UsuarioService;

public class ExpedientesManager {

    public static void main(String[] args) throws IOException {
        ExpedienteService expedienteService = new ExpedienteService();
        ArchivoService archivoService = new ArchivoService();
        UsuarioService usuarioService = new UsuarioService(); // Servicio para gestionar usuarios
        Scanner scanner = new Scanner(System.in);

        // Proceso de login
        System.out.println("--- Sistema de Gestión de Expedientes ---");
        System.out.print("Ingrese el nombre de usuario: ");
        String username = scanner.nextLine();

        System.out.print("Ingrese la contraseña: ");
        String password = scanner.nextLine();

        Usuario usuario = usuarioService.validarUsuario(username, password);

        if (usuario == null) {
            System.out.println("Credenciales inválidas. Saliendo del sistema...");
            System.exit(0);
        }

        String role = usuario.getRol(); // Obtener el rol del usuario
        System.out.println("Bienvenido, " + usuario.getNombre() + ". Su rol es: " + role);

        while (true) {
            System.out.println("\n--- Menú de Gestión de Expedientes ---");
            System.out.println("1. Crear un nuevo expediente");
            System.out.println("2. Ver todos los expedientes");
            System.out.println("3. Agregar estado a un expediente");
            System.out.println("4. Convertir expediente Word a PDF");
            System.out.println("5. Buscar expedientes");
            if (role.equals("ADMIN")) {
                System.out.println("6. Eliminar expediente");
            }
            System.out.println("7. Salir");
            System.out.print("Seleccione una opción: ");

            int opcion = scanner.nextInt();
            scanner.nextLine(); // Limpiar buffer

            switch (opcion) {
                case 1: // Crear un nuevo expediente
                    if (!role.equals("ADMIN") && !role.equals("CREADOR")) {
                        System.out.println("No tiene permisos para crear un expediente.");
                        break;
                    }
                    System.out.print("Ingrese el número del expediente: ");
                    String numero = scanner.nextLine();
                    System.out.print("Ingrese la materia: ");
                    String materia = scanner.nextLine();
                    System.out.print("Ingrese el juzgado: ");
                    String juzgado = scanner.nextLine();
                    System.out.print("Ingrese el especialista: ");
                    String especialista = scanner.nextLine();
                    System.out.print("Ingrese el tercero: ");
                    String tercero = scanner.nextLine();
                    System.out.print("Ingrese el demandado: ");
                    String demandado = scanner.nextLine();
                    System.out.print("Ingrese el demandante: ");
                    String demandante = scanner.nextLine();
                    System.out.print("Ingrese el estado actual: ");
                    String estadoActual = scanner.nextLine();

                    System.out.print("Ingrese el nombre del archivo para guardar el expediente en Word: ");
                    String nombreArchivo = scanner.nextLine();

                    try {
                        expedienteService.crearExpediente(
                            numero, materia, juzgado, especialista, tercero,
                            demandado, demandante, estadoActual, "WORD", nombreArchivo
                        );
                        System.out.println("Expediente creado exitosamente.");
                    } catch (Exception e) {
                        System.out.println("Error al crear el expediente.");
                        e.printStackTrace();
                    }
                    break;

                case 2: // Ver todos los expedientes
                    List<String> expedientes = expedienteService.verExpedientes();
                    System.out.println("\n--- Lista de Expedientes ---");
                    for (String exp : expedientes) {
                        System.out.println(exp);
                    }
                    break;

                case 3: // Agregar estado a un expediente
                    if (!role.equals("ADMIN") && !role.equals("CREADOR")) {
                        System.out.println("No tiene permisos para agregar un estado.");
                        break;
                    }
                    System.out.print("Ingrese el número del expediente: ");
                    String numeroModificar = scanner.nextLine();
                    System.out.print("Ingrese la nueva resolución: ");
                    String nuevaResolucion = scanner.nextLine();

                    try {
                        expedienteService.agregarEstadoExpedienteConDocumentos(numeroModificar, nuevaResolucion);
                        System.out.println("Estado agregado exitosamente al expediente.");
                    } catch (Exception e) {
                        System.out.println("Error al agregar el estado al expediente.");
                        e.printStackTrace();
                    }
                    break;

                case 4: // Convertir expediente Word a PDF
                    System.out.print("Ingrese el número del expediente a convertir: ");
                    String numeroConvertir = scanner.nextLine();

                    try {
                        archivoService.convertirWordAPDF(numeroConvertir);
                        System.out.println("Expediente convertido a PDF exitosamente.");
                    } catch (Exception e) {
                        System.out.println("Error al convertir el expediente a PDF.");
                        e.printStackTrace();
                    }
                    break;

                case 5: // Buscar expedientes
                    System.out.println("Ingrese los criterios de búsqueda (deje en blanco para ignorar):");
                    System.out.print("Número del expediente: ");
                    String criterioNumero = scanner.nextLine().trim();
                    System.out.print("Materia: ");
                    String criterioMateria = scanner.nextLine().trim();
                    System.out.print("Juzgado: ");
                    String criterioJuzgado = scanner.nextLine().trim();
                    System.out.print("Especialista: ");
                    String criterioEspecialista = scanner.nextLine().trim();

                    criterioNumero = criterioNumero.isEmpty() ? null : criterioNumero;
                    criterioMateria = criterioMateria.isEmpty() ? null : criterioMateria;
                    criterioJuzgado = criterioJuzgado.isEmpty() ? null : criterioJuzgado;
                    criterioEspecialista = criterioEspecialista.isEmpty() ? null : criterioEspecialista;

                    List<String> resultadosBusqueda = expedienteService.buscarExpedientes(
                        criterioNumero, criterioMateria, criterioJuzgado, criterioEspecialista
                    );

                    if (resultadosBusqueda.isEmpty()) {
                        System.out.println("No se encontraron expedientes con los criterios proporcionados.");
                    } else {
                        System.out.println("\n--- Resultados de la Búsqueda ---");
                        for (String resultado : resultadosBusqueda) {
                            System.out.println(resultado);
                        }
                    }
                    break;

                case 6: // Eliminar expediente (solo para ADMIN)
                    if (!role.equals("ADMIN")) {
                        System.out.println("No tiene permisos para eliminar un expediente.");
                        break;
                    }
                    System.out.print("Ingrese el número del expediente a eliminar: ");
                    String numeroEliminar = scanner.nextLine();

                    try {
                        expedienteService.eliminarExpediente(numeroEliminar);
                        System.out.println("Expediente eliminado exitosamente.");
                    } catch (Exception e) {
                        System.out.println("Error al eliminar el expediente.");
                        e.printStackTrace();
                    }
                    break;

                case 7: // Salir
                    System.out.println("Saliendo del programa...");
                    scanner.close();
                    return;

                default:
                    System.out.println("Opción no válida. Intente de nuevo.");
            }
        }
    }
}
