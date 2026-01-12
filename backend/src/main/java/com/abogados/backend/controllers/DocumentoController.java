package com.abogados.backend.controllers;

import com.abogados.backend.models.Expediente;
import com.abogados.backend.repositories.ExpedienteRepository;
import com.abogados.backend.services.WordDocumentService;
import com.abogados.backend.services.PDFDocumentService;
import org.springframework.core.io.FileSystemResource;
import org.springframework.core.io.Resource;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;

import java.io.File;
import java.io.IOException;
import java.util.Optional;

@RestController
@RequestMapping("/api/expedientes")
public class DocumentoController {

    private final ExpedienteRepository expedienteRepository;

    public DocumentoController(ExpedienteRepository expedienteRepository) {
        this.expedienteRepository = expedienteRepository;
    }

    @PostMapping("/{id}/word")
    public ResponseEntity<Resource> generateWord(@PathVariable Integer id, @RequestBody(required = false) WordRequest request) {
        Optional<Expediente> expedienteOpt = expedienteRepository.findById(id);
        if (expedienteOpt.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        try {
            Expediente expediente = expedienteOpt.get();
            String nombreArchivo = (request != null && request.getNombreArchivo() != null) 
                ? request.getNombreArchivo() 
                : expediente.getNumero();
            
            File wordFile = WordDocumentService.crearDocumento(expediente, nombreArchivo);
            Resource resource = new FileSystemResource(wordFile);
            
            return ResponseEntity.ok()
                .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=\"" + nombreArchivo + ".docx\"")
                .contentType(MediaType.APPLICATION_OCTET_STREAM)
                .body(resource);
        } catch (IOException e) {
            return ResponseEntity.internalServerError().build();
        }
    }

    @PostMapping("/{id}/pdf")
    public ResponseEntity<Resource> generatePDF(@PathVariable Integer id, @RequestBody(required = false) PDFRequest request) {
        Optional<Expediente> expedienteOpt = expedienteRepository.findById(id);
        if (expedienteOpt.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        try {
            Expediente expediente = expedienteOpt.get();
            String nombreArchivo = (request != null && request.getNombreArchivo() != null) 
                ? request.getNombreArchivo() 
                : expediente.getNumero();
            
            File pdfFile = PDFDocumentService.crearDocumento(expediente, nombreArchivo);
            Resource resource = new FileSystemResource(pdfFile);
            
            return ResponseEntity.ok()
                .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=\"" + nombreArchivo + ".pdf\"")
                .contentType(MediaType.APPLICATION_PDF)
                .body(resource);
        } catch (Exception e) {
            return ResponseEntity.internalServerError().build();
        }
    }

    // Request DTOs
    public static class WordRequest {
        private String nombreArchivo;
        public String getNombreArchivo() { return nombreArchivo; }
        public void setNombreArchivo(String nombreArchivo) { this.nombreArchivo = nombreArchivo; }
    }

    public static class PDFRequest {
        private String nombreArchivo;
        public String getNombreArchivo() { return nombreArchivo; }
        public void setNombreArchivo(String nombreArchivo) { this.nombreArchivo = nombreArchivo; }
    }

    public static class ResolutionRequest {
        private String contenidoHtml;
        private String numeroResolucion;
        
        public String getContenidoHtml() { return contenidoHtml; }
        public void setContenidoHtml(String contenidoHtml) { this.contenidoHtml = contenidoHtml; }
        public String getNumeroResolucion() { return numeroResolucion; }
        public void setNumeroResolucion(String numeroResolucion) { this.numeroResolucion = numeroResolucion; }
    }
}
