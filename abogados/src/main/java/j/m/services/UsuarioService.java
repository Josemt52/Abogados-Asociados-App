package j.m.services;

import j.m.models.Usuario;
import j.m.utils.JPAUtil;
import jakarta.persistence.EntityManager;
import jakarta.persistence.NoResultException;
import jakarta.persistence.TypedQuery;

public class UsuarioService {

    public Usuario validarUsuario(String username, String password) {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            String jpql = "SELECT u FROM Usuario u JOIN FETCH u.rol WHERE u.username = :user";
            TypedQuery<Usuario> query = em.createQuery(jpql, Usuario.class);
            query.setParameter("user", username);

            Usuario usuario = query.getSingleResult();

            // Verifica la contraseña en texto plano
            if (password.equals(usuario.getPassword())) {
                return usuario; // Contraseña correcta
            }
            return null; // Contraseña incorrecta

        } catch (NoResultException e) {
            return null; // Usuario no encontrado
        } finally {
            em.close();
        }
    }

    public void crearUsuario(Usuario usuario) {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            em.getTransaction().begin();
            // Guarda la contraseña en texto plano
            em.persist(usuario);
            em.getTransaction().commit();
        } catch (Exception e) {
            if (em.getTransaction().isActive()) em.getTransaction().rollback();
            e.printStackTrace();
        } finally {
            em.close();
        }
    }
}
