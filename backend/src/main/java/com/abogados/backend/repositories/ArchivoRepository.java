package com.abogados.backend.repositories;

import com.abogados.backend.models.Archivo;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

import java.util.Optional;

@Repository
public interface ArchivoRepository extends JpaRepository<Archivo, Integer> {
    Optional<Archivo> findByExpedienteId(Integer expedienteId);
    Optional<Archivo> findByExpedienteNumero(String numeroExpediente);
}
