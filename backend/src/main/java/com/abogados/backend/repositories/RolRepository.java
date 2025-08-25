package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import com.abogados.backend.models.Rol;

public interface RolRepository extends JpaRepository<Rol, Integer> {
}
