package j.m.services;

import java.util.List;

import org.mindrot.jbcrypt.BCrypt;

import j.m.models.Rol;
import j.m.models.Usuario;
import j.m.utils.JPAUtil;
import jakarta.persistence.EntityManager;
import jakarta.persistence.NoResultException;
import jakarta.persistence.TypedQuery;

public class DataLoader {

    public void loadInitialData() {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            em.getTransaction().begin();

            // --- Creación de Roles (si no existen) ---
            Rol adminRol;
            try {
                adminRol = em.createQuery("SELECT r FROM Rol r WHERE r.nombre = 'Admin'", Rol.class).getSingleResult();
                System.out.println("El rol 'Admin' ya existe.");
            } catch (NoResultException e) {
                System.out.println("Creando rol 'Admin'...");
                adminRol = new Rol();
                adminRol.setNombre("Admin");
                em.persist(adminRol);
            }

            try {
                em.createQuery("SELECT r FROM Rol r WHERE r.nombre = 'User'", Rol.class).getSingleResult();
                System.out.println("El rol 'User' ya existe.");
            } catch (NoResultException e) {
                System.out.println("Creando rol 'User'...");
                Rol userRol = new Rol();
                userRol.setNombre("User");
                em.persist(userRol);
            }
            
            // Forzamos la ejecución para asegurar que los roles tengan ID antes de usarlos
            em.flush();

            // --- Creación o corrección del Usuario Administrador ---
            TypedQuery<Usuario> query = em.createQuery("SELECT u FROM Usuario u WHERE u.username = 'admin'", Usuario.class);
            List<Usuario> admins = query.getResultList();

            if (admins.isEmpty()) {
                // Si el usuario admin no existe, lo creamos
                System.out.println("Creando nuevo usuario 'admin'...");
                Usuario adminUser = new Usuario();
                adminUser.setNombre("Administrador");
                adminUser.setUsername("admin");
                adminUser.setPassword(BCrypt.hashpw("admin", BCrypt.gensalt()));
                adminUser.setRol(adminRol); // Enlazamos el rol que ya buscamos/creamos
                em.persist(adminUser);
            } else {
                // Si el usuario ya existe, verificamos si su contraseña está hasheada
                Usuario adminUser = admins.get(0);
                String currentPassword = adminUser.getPassword();
                
                if (currentPassword == null || !currentPassword.startsWith("$2")) {
                    System.out.println("La contraseña del usuario 'admin' no estaba cifrada. Actualizando ahora...");
                    adminUser.setPassword(BCrypt.hashpw("admin", BCrypt.gensalt()));
                    em.merge(adminUser);
                } else {
                    System.out.println("El usuario 'admin' ya tiene una contraseña cifrada.");
                }
            }

            em.getTransaction().commit();
            System.out.println("Proceso de carga de datos iniciales finalizado.");

        } catch (Exception e) {
            System.err.println("Error crítico durante la inicialización de la base de datos.");
            if (em.getTransaction().isActive()) {
                em.getTransaction().rollback();
            }
            e.printStackTrace();
        } finally {
            em.close();
        }
    }
}
