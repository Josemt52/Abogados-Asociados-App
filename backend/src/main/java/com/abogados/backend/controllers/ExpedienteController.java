package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import java.util.List;
import com.abogados.backend.models.Expediente;
import com.abogados.backend.repositories.ExpedienteRepository;

@RestController
@RequestMapping("/api/expedientes")
public class ExpedienteController {

    private final ExpedienteRepository expedienteRepository;

    public ExpedienteController(ExpedienteRepository expedienteRepository) {
        this.expedienteRepository = expedienteRepository;
    }

    @GetMapping
    public List<Expediente> list() {
        return expedienteRepository.findAll();
    }

    @GetMapping("/{id}")
    public ResponseEntity<Expediente> get(@PathVariable Integer id) {
        return expedienteRepository.findById(id)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ResponseEntity<Expediente> create(@RequestBody Expediente expediente) {
        Expediente saved = expedienteRepository.save(expediente);
        return ResponseEntity.status(HttpStatus.CREATED).body(saved);
    }

    @PutMapping("/{id}")
    public ResponseEntity<Expediente> update(@PathVariable Integer id, @RequestBody Expediente expediente) {
        return expedienteRepository.findById(id)
                .map(existing -> {
                    existing.setNumero(expediente.getNumero());
                    existing.setMateria(expediente.getMateria());
                    existing.setJuzgado(expediente.getJuzgado());
                    existing.setEspecialista(expediente.getEspecialista());
                    existing.setTercero(expediente.getTercero());
                    existing.setDemandado(expediente.getDemandado());
                    existing.setDemandante(expediente.getDemandante());
                    existing.setEstado(expediente.getEstado());
                    existing.setArchivo(expediente.getArchivo());
                    Expediente s = expedienteRepository.save(existing);
                    return ResponseEntity.ok(s);
                }).orElse(ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Integer id) {
        if (!expedienteRepository.existsById(id)) return ResponseEntity.notFound().build();
        expedienteRepository.deleteById(id);
        return ResponseEntity.noContent().build();
    }
}
