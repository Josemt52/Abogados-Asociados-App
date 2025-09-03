package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.core.io.ByteArrayResource;
import org.springframework.core.io.Resource;
import java.util.List;
import java.util.Optional;
import java.io.IOException;
import com.abogados.backend.models.Expediente;
import com.abogados.backend.models.Archivo;
import com.abogados.backend.repositories.ExpedienteRepository;
import com.abogados.backend.repositories.ArchivoRepository;

@RestController
@RequestMapping("/api/expedientes")
public class ExpedienteController {

    private final ExpedienteRepository expedienteRepository;
    private final ArchivoRepository archivoRepository;

    public ExpedienteController(ExpedienteRepository expedienteRepository, ArchivoRepository archivoRepository) {
        this.expedienteRepository = expedienteRepository;
        this.archivoRepository = archivoRepository;
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

    @PostMapping("/{id}/archivo")
    public ResponseEntity<Archivo> uploadFile(@PathVariable Integer id, @RequestParam("file") MultipartFile file) {
        Optional<Expediente> expedienteOpt = expedienteRepository.findById(id);
        if (expedienteOpt.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        try {
            Expediente expediente = expedienteOpt.get();
            
            // Buscar archivo existente o crear nuevo
            Archivo archivo = archivoRepository.findByExpedienteId(id).orElse(new Archivo());
            archivo.setNombreArchivo(file.getOriginalFilename());
            archivo.setTipoArchivo(file.getContentType());
            archivo.setDocumentoData(file.getBytes());
            archivo.setExpediente(expediente);
            
            Archivo savedArchivo = archivoRepository.save(archivo);
            return ResponseEntity.ok(savedArchivo);
            
        } catch (IOException e) {
            return ResponseEntity.internalServerError().build();
        }
    }

    @GetMapping("/{id}/archivo/{archivoId}/download")
    public ResponseEntity<Resource> downloadFile(@PathVariable Integer id, @PathVariable Integer archivoId) {
        Optional<Archivo> archivoOpt = archivoRepository.findById(archivoId);
        if (archivoOpt.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        Archivo archivo = archivoOpt.get();
        if (!archivo.getExpediente().getId().equals(id)) {
            return ResponseEntity.badRequest().build();
        }

        ByteArrayResource resource = new ByteArrayResource(archivo.getDocumentoData());
        
        return ResponseEntity.ok()
            .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=\"" + archivo.getNombreArchivo() + "\"")
            .contentType(MediaType.APPLICATION_OCTET_STREAM)
            .body(resource);
    }
}
