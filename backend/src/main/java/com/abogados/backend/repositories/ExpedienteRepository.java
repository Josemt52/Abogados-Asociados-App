package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import java.util.Optional;
import java.util.List;
import com.abogados.backend.models.Expediente;

public interface ExpedienteRepository extends JpaRepository<Expediente, Integer> {
    Optional<Expediente> findByNumero(String numero);
    
    // Métodos para estadísticas
    long countByEstado(String estado);
    long countByEstadoNot(String estado);
    long countByEstadoOrEstado(String estado1, String estado2);
    
    // Para actividad reciente
    List<Expediente> findTop5ByOrderByIdDesc();
}
