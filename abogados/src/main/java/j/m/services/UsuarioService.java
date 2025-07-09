package j.m.services;

import jakarta.persistence.EntityManager;
import jakarta.persistence.NoResultException;
import jakarta.persistence.TypedQuery;
import j.m.models.Usuario;
import j.m.utils.JPAUtil;

public class UsuarioService {
    
    public Usuario validarUsuario(String username, String password) {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            // Usamos JPQL para la consulta
            String jpql = "SELECT u FROM Usuario u WHERE u.username = :user AND u.password = :pass";
            TypedQuery<Usuario> query = em.createQuery(jpql, Usuario.class);
            query.setParameter("user", username);
            query.setParameter("pass", password); // Aún sin hashear, pero funciona con JPA
            
            return query.getSingleResult();
        } catch (NoResultException e) {
            return null; // Usuario no encontrado
        } finally {
            em.close();
        }
    }
}