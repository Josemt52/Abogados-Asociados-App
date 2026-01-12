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
import java.util.stream.Collectors;
import java.io.IOException;
import com.abogados.backend.models.Expediente;
import com.abogados.backend.models.Archivo;
import com.abogados.backend.dto.ExpedienteDTO;
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

    // Helper method to convert entity to DTO
    private ExpedienteDTO toDTO(Expediente expediente) {
        ExpedienteDTO dto = new ExpedienteDTO();
        dto.setId(expediente.getId());
        dto.setNumero(expediente.getNumero());
        dto.setMateria(expediente.getMateria());
        dto.setJuzgado(expediente.getJuzgado());
        dto.setEspecialista(expediente.getEspecialista());
        dto.setTercero(expediente.getTercero());
        dto.setDemandado(expediente.getDemandado());
        dto.setDemandante(expediente.getDemandante());
        dto.setEstado(expediente.getEstado());
        dto.setArchivo(expediente.getArchivo());
        dto.setNombreArchivo(expediente.getNombreArchivo());
        return dto;
    }

    @GetMapping
    public List<ExpedienteDTO> list() {
        return expedienteRepository.findAll()
                .stream()
                .map(this::toDTO)
                .collect(Collectors.toList());
    }

    @GetMapping("/{id}")
    public ResponseEntity<ExpedienteDTO> get(@PathVariable Integer id) {
        return expedienteRepository.findById(id)
                .map(this::toDTO)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ResponseEntity<ExpedienteDTO> create(@RequestBody Expediente expediente) {
        Expediente saved = expedienteRepository.save(expediente);
        return ResponseEntity.status(HttpStatus.CREATED).body(toDTO(saved));
    }

    @PutMapping("/{id}")
    public ResponseEntity<ExpedienteDTO> update(@PathVariable Integer id, @RequestBody Expediente expediente) {
        return expedienteRepository.findById(id)
                .map(existing -> {
                    // Only overwrite fields that are present in the request body (non-null)
                    if (expediente.getNumero() != null) existing.setNumero(expediente.getNumero());
                    if (expediente.getMateria() != null) existing.setMateria(expediente.getMateria());
                    if (expediente.getJuzgado() != null) existing.setJuzgado(expediente.getJuzgado());
                    if (expediente.getEspecialista() != null) existing.setEspecialista(expediente.getEspecialista());
                    if (expediente.getTercero() != null) existing.setTercero(expediente.getTercero());
                    if (expediente.getDemandado() != null) existing.setDemandado(expediente.getDemandado());
                    if (expediente.getDemandante() != null) existing.setDemandante(expediente.getDemandante());
                    if (expediente.getEstado() != null) existing.setEstado(expediente.getEstado());
                    if (expediente.getArchivo() != null) existing.setArchivo(expediente.getArchivo());
                    // NO actualizar 'archivo' ni 'nombreArchivo' desde el formulario
                    // Estos campos se actualizan automáticamente al subir archivo
                    Expediente s = expedienteRepository.save(existing);
                    return ResponseEntity.ok(toDTO(s));
                }).orElse(ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Integer id) {
        if (!expedienteRepository.existsById(id)) return ResponseEntity.notFound().build();
        expedienteRepository.deleteById(id);
        return ResponseEntity.noContent().build();
    }

    @PostMapping("/{id}/archivo")
    public ResponseEntity<ExpedienteDTO> uploadFile(@PathVariable Integer id, @RequestParam("file") MultipartFile file) {
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
            
            archivoRepository.save(archivo);
            
            // ACTUALIZAR EL ESTADO DEL EXPEDIENTE: marcar que tiene archivo
            expediente.setArchivo(true);
            expediente.setNombreArchivo(file.getOriginalFilename());
            Expediente updatedExpediente = expedienteRepository.save(expediente);
            
            return ResponseEntity.ok(toDTO(updatedExpediente));
            
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

    // Nuevo endpoint: descargar archivo del expediente directamente
    @GetMapping("/{id}/archivo/download")
    public ResponseEntity<Resource> downloadExpedienteFile(@PathVariable Integer id) {
        Optional<Expediente> expedienteOpt = expedienteRepository.findById(id);
        if (expedienteOpt.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        Optional<Archivo> archivoOpt = archivoRepository.findByExpedienteId(id);
        if (archivoOpt.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        Archivo archivo = archivoOpt.get();
        ByteArrayResource resource = new ByteArrayResource(archivo.getDocumentoData());
        
        return ResponseEntity.ok()
            .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=\"" + archivo.getNombreArchivo() + "\"")
            .contentType(MediaType.APPLICATION_OCTET_STREAM)
            .body(resource);
    }
}
