package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import com.abogados.backend.repositories.ExpedienteRepository;
import com.abogados.backend.repositories.UsuarioRepository;
import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/api/estadisticas")
@CrossOrigin(origins = "http://localhost:5173")
public class EstadisticasController {

    private final ExpedienteRepository expedienteRepository;
    private final UsuarioRepository usuarioRepository;

    public EstadisticasController(ExpedienteRepository expedienteRepository, UsuarioRepository usuarioRepository) {
        this.expedienteRepository = expedienteRepository;
        this.usuarioRepository = usuarioRepository;
    }

    @GetMapping("/dashboard")
    public ResponseEntity<Map<String, Object>> getDashboardStats() {
        Map<String, Object> stats = new HashMap<>();
        
        // Contar total de expedientes activos (todos los que no están cerrados)
        long expedientesActivos = expedienteRepository.countByEstadoNot("CERRADO");
        
        // Contar expedientes en progreso
        long enProgreso = expedienteRepository.countByEstado("EN_PROGRESO");
        
        // Contar expedientes finalizados
        long finalizados = expedienteRepository.countByEstado("CERRADO");
        
        // Contar expedientes urgentes (asumimos que son los que tienen prioridad alta o están en estado URGENTE)
        long urgentes = expedienteRepository.countByEstadoOrEstado("URGENTE", "CRITICO");
        
        // Total de usuarios en el sistema
        long totalUsuarios = usuarioRepository.count();
        
        stats.put("expedientesActivos", expedientesActivos);
        stats.put("enProgreso", enProgreso);
        stats.put("finalizados", finalizados);
        stats.put("urgentes", urgentes);
        stats.put("totalUsuarios", totalUsuarios);
        
        return ResponseEntity.ok(stats);
    }

    @GetMapping("/expedientes-por-estado")
    public ResponseEntity<Map<String, Long>> getExpedientesPorEstado() {
        Map<String, Long> estadisticas = new HashMap<>();
        
        // Obtener todos los estados posibles y contar
        estadisticas.put("NUEVO", expedienteRepository.countByEstado("NUEVO"));
        estadisticas.put("EN_PROGRESO", expedienteRepository.countByEstado("EN_PROGRESO"));
        estadisticas.put("PENDIENTE", expedienteRepository.countByEstado("PENDIENTE"));
        estadisticas.put("URGENTE", expedienteRepository.countByEstado("URGENTE"));
        estadisticas.put("CERRADO", expedienteRepository.countByEstado("CERRADO"));
        
        return ResponseEntity.ok(estadisticas);
    }

    @GetMapping("/actividad-reciente")
    public ResponseEntity<Map<String, Object>> getActividadReciente() {
        Map<String, Object> actividad = new HashMap<>();
        
        // Por ahora, devolvemos datos mock, pero se puede implementar un sistema de auditoría
        actividad.put("ultimosExpedientes", expedienteRepository.findTop5ByOrderByIdDesc());
        actividad.put("totalHoy", expedienteRepository.count()); // Se puede filtrar por fecha de creación
        
        return ResponseEntity.ok(actividad);
    }
}
