package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import com.abogados.backend.models.Service;
import java.util.List;

public interface ServiceRepository extends JpaRepository<Service, Integer> {
    List<Service> findByIsActiveTrueOrderByIdAsc();
    List<Service> findByServiceTypeAndIsActiveTrueOrderByIdAsc(String serviceType);
}
