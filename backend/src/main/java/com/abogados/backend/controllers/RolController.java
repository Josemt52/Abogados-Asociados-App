package com.abogados.backend.controllers;

import com.abogados.backend.models.Rol;
import com.abogados.backend.repositories.RolRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/roles")
@CrossOrigin(origins = "*")
public class RolController {

    @Autowired
    private RolRepository rolRepository;

    /**
     * Endpoint para obtener todos los roles disponibles
     * GET /api/roles
     * 
     * @return Lista de roles con estructura {id: number, nombre: string}
     */
    @GetMapping
    public ResponseEntity<List<Rol>> getAllRoles() {
        List<Rol> roles = rolRepository.findAll();
        return ResponseEntity.ok(roles);
    }

    /**
     * Endpoint para obtener un rol específico por ID
     * GET /api/roles/{id}
     * 
     * @param id ID del rol a buscar
     * @return Rol encontrado o 404 Not Found
     */
    @GetMapping("/{id}")
    public ResponseEntity<Rol> getRolById(@PathVariable Integer id) {
        return rolRepository.findById(id)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    /**
     * Endpoint para obtener un rol específico por nombre (case-insensitive)
     * GET /api/roles/nombre/{nombre}
     * 
     * @param nombre Nombre del rol a buscar (admin, usuario, secretario)
     * @return Rol encontrado o 404 Not Found
     */
    @GetMapping("/nombre/{nombre}")
    public ResponseEntity<Rol> getRolByNombre(@PathVariable String nombre) {
        return rolRepository.findByNombreIgnoreCase(nombre)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }
}
