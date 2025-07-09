package j.m.services;

import jakarta.persistence.EntityManager;
import j.m.models.Expediente;
import j.m.utils.JPAUtil;
import java.util.List;

public class ExpedienteService {

    public void crearOActualizar(Expediente expediente) {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            em.getTransaction().begin();
            em.merge(expediente);
            em.getTransaction().commit();
        } catch (Exception e) {
            if (em.getTransaction().isActive()) em.getTransaction().rollback();
            e.printStackTrace();
        } finally {
            em.close();
        }
    }

    public List<Expediente> verExpedientes() {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            String jpql = "SELECT e FROM Expediente e";
            return em.createQuery(jpql, Expediente.class).getResultList();
        } finally {
            em.close();
        }
    }

    // ESTE ES EL MÉTODO QUE FALTABA
    public void eliminarExpedientePorId(int id) {
        EntityManager em = JPAUtil.getEntityManager();
        try {
            em.getTransaction().begin();
            Expediente expediente = em.find(Expediente.class, id);
            if (expediente != null) {
                em.remove(expediente);
            }
            em.getTransaction().commit();
        } catch (Exception e) {
            if (em.getTransaction().isActive()) em.getTransaction().rollback();
            e.printStackTrace();
        } finally {
            em.close();
        }
    }
}