package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import com.abogados.backend.models.Service;
import com.abogados.backend.repositories.ServiceRepository;
import java.util.List;

@RestController
@RequestMapping("/api/services")
@CrossOrigin(origins = {"http://localhost:5173", "http://localhost:5174", "http://localhost:3000"})
public class ServiceController {

    private final ServiceRepository serviceRepository;

    public ServiceController(ServiceRepository serviceRepository) {
        this.serviceRepository = serviceRepository;
    }

    @GetMapping("/public")
    public ResponseEntity<List<Service>> getPublicServices() {
        List<Service> services = serviceRepository.findByIsActiveTrueOrderByIdAsc();
        return ResponseEntity.ok(services);
    }

    @GetMapping
    public List<Service> getAllServices() {
        return serviceRepository.findAll();
    }

    @GetMapping("/{id}")
    public ResponseEntity<Service> getService(@PathVariable Integer id) {
        return serviceRepository.findById(id)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ResponseEntity<Service> createService(@RequestBody Service service) {
        Service savedService = serviceRepository.save(service);
        return ResponseEntity.status(HttpStatus.CREATED).body(savedService);
    }

    @PutMapping("/{id}")
    public ResponseEntity<Service> updateService(@PathVariable Integer id, @RequestBody Service service) {
        return serviceRepository.findById(id)
                .map(existing -> {
                    existing.setTitle(service.getTitle());
                    existing.setDescription(service.getDescription());
                    existing.setServiceType(service.getServiceType());
                    existing.setFeatures(service.getFeatures());
                    existing.setIcon(service.getIcon());
                    existing.setIsActive(service.getIsActive());
                    Service updated = serviceRepository.save(existing);
                    return ResponseEntity.ok(updated);
                }).orElse(ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> deleteService(@PathVariable Integer id) {
        if (!serviceRepository.existsById(id)) {
            return ResponseEntity.notFound().build();
        }
        serviceRepository.deleteById(id);
        return ResponseEntity.noContent().build();
    }

    @GetMapping("/type/{serviceType}")
    public ResponseEntity<List<Service>> getServicesByType(@PathVariable String serviceType) {
        List<Service> services = serviceRepository.findByServiceTypeAndIsActiveTrueOrderByIdAsc(serviceType);
        return ResponseEntity.ok(services);
    }
}
