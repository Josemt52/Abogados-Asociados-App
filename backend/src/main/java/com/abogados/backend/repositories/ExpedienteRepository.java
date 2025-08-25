package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import java.util.Optional;
import com.abogados.backend.models.Expediente;

public interface ExpedienteRepository extends JpaRepository<Expediente, Integer> {
    Optional<Expediente> findByNumero(String numero);
}
