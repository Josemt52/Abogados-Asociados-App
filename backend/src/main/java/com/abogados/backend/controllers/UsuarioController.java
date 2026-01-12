package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import java.util.List;
import java.util.Map;
import com.abogados.backend.models.Usuario;
import com.abogados.backend.models.Rol;
import com.abogados.backend.dto.UserRegistrationRequest;
import com.abogados.backend.repositories.UsuarioRepository;
import com.abogados.backend.repositories.RolRepository;

@RestController
@RequestMapping("/api/usuarios")
public class UsuarioController {

    private final UsuarioRepository usuarioRepository;
    private final RolRepository rolRepository;
    private final org.springframework.security.crypto.password.PasswordEncoder passwordEncoder;

    public UsuarioController(UsuarioRepository usuarioRepository, RolRepository rolRepository, 
                            org.springframework.security.crypto.password.PasswordEncoder passwordEncoder) {
        this.usuarioRepository = usuarioRepository;
        this.rolRepository = rolRepository;
        this.passwordEncoder = passwordEncoder;
    }

    @GetMapping
    public List<Usuario> list() {
        return usuarioRepository.findAll();
    }

    @GetMapping("/{id}")
    public ResponseEntity<Usuario> get(@PathVariable Integer id) {
        return usuarioRepository.findById(id)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ResponseEntity<?> create(@RequestBody UserRegistrationRequest request) {
        try {
            // Validar que el request tenga los datos necesarios
            if (request.getUsername() == null || request.getUsername().trim().isEmpty()) {
                return ResponseEntity.badRequest()
                    .body(Map.of("error", "El nombre de usuario es obligatorio"));
            }
            
            if (request.getNombre() == null || request.getNombre().trim().isEmpty()) {
                return ResponseEntity.badRequest()
                    .body(Map.of("error", "El nombre es obligatorio"));
            }
            
            if (request.getPassword() == null || request.getPassword().trim().isEmpty()) {
                return ResponseEntity.badRequest()
                    .body(Map.of("error", "La contraseña es obligatoria"));
            }
            
            if (request.getRol() == null || request.getRol().trim().isEmpty()) {
                return ResponseEntity.badRequest()
                    .body(Map.of("error", "El rol es obligatorio"));
            }

            // Verificar si el username ya existe
            if (usuarioRepository.findByUsername(request.getUsername()).isPresent()) {
                return ResponseEntity.badRequest()
                    .body(Map.of("error", "El nombre de usuario ya existe"));
            }

            // Buscar el rol por nombre (case-insensitive)
            Rol rol = rolRepository.findByNombreIgnoreCase(request.getRol())
                    .orElseThrow(() -> new RuntimeException(
                        "Rol no encontrado: '" + request.getRol() + "'. " +
                        "Roles disponibles: admin, usuario. " +
                        "Por favor ejecute el script de inicialización de roles."
                    ));
            
            // Crear el usuario
            Usuario usuario = new Usuario();
            usuario.setNombre(request.getNombre());
            usuario.setUsername(request.getUsername());
            // Hashear contraseña con BCrypt
            usuario.setPassword(passwordEncoder.encode(request.getPassword()));
            usuario.setRol(rol);
            
            Usuario saved = usuarioRepository.save(usuario);
            return ResponseEntity.status(HttpStatus.CREATED).body(saved);
            
        } catch (RuntimeException e) {
            return ResponseEntity.badRequest()
                .body(Map.of("error", e.getMessage()));
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(Map.of("error", "Error al crear el usuario: " + e.getMessage()));
        }
    }

    @PutMapping("/{id}")
    public ResponseEntity<?> update(@PathVariable Integer id, @RequestBody Map<String, Object> updateData) {
        try {
            return usuarioRepository.findById(id)
                    .map(existing -> {
                        // Actualizar nombre si viene en el request
                        if (updateData.containsKey("nombre")) {
                            existing.setNombre((String) updateData.get("nombre"));
                        }
                        
                        // Actualizar username si viene en el request
                        if (updateData.containsKey("username")) {
                            existing.setUsername((String) updateData.get("username"));
                        }
                        
                        // Actualizar password solo si viene en el request y no está vacío
                        if (updateData.containsKey("password")) {
                            String password = (String) updateData.get("password");
                            if (password != null && !password.trim().isEmpty()) {
                                // Hashear nueva contraseña con BCrypt
                                existing.setPassword(passwordEncoder.encode(password));
                            }
                        }
                        
                        // Actualizar rol si viene en el request
                        if (updateData.containsKey("rol")) {
                            String rolNombre = (String) updateData.get("rol");
                            Rol rol = rolRepository.findByNombreIgnoreCase(rolNombre)
                                    .orElseThrow(() -> new RuntimeException("Rol no encontrado: " + rolNombre));
                            existing.setRol(rol);
                        }
                        
                        Usuario saved = usuarioRepository.save(existing);
                        return ResponseEntity.ok(saved);
                    }).orElse(ResponseEntity.notFound().build());
        } catch (RuntimeException e) {
            return ResponseEntity.badRequest()
                .body(Map.of("error", e.getMessage()));
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(Map.of("error", "Error al actualizar el usuario: " + e.getMessage()));
        }
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Integer id) {
        if (!usuarioRepository.existsById(id)) return ResponseEntity.notFound().build();
        usuarioRepository.deleteById(id);
        return ResponseEntity.noContent().build();
    }
}
