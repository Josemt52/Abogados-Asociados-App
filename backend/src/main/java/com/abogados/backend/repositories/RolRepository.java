package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import com.abogados.backend.models.Rol;
import java.util.Optional;

public interface RolRepository extends JpaRepository<Rol, Integer> {
    Optional<Rol> findByNombreIgnoreCase(String nombre);
}
