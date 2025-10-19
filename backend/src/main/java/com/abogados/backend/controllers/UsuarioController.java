package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import java.util.List;
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

    public UsuarioController(UsuarioRepository usuarioRepository, RolRepository rolRepository) {
        this.usuarioRepository = usuarioRepository;
        this.rolRepository = rolRepository;
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
        // Buscar el rol por nombre (case-insensitive)
        Rol rol = rolRepository.findByNombreIgnoreCase(request.getRol())
                .orElseThrow(() -> new RuntimeException("Rol no encontrado: " + request.getRol()));
        
        // Crear el usuario
        Usuario usuario = new Usuario();
        usuario.setUsername(request.getUsername());
        usuario.setEmail(request.getEmail());
        usuario.setPassword(request.getPassword()); // TODO: Encriptar password
        usuario.setRol(rol);
        
        Usuario saved = usuarioRepository.save(usuario);
        return ResponseEntity.status(HttpStatus.CREATED).body(saved);
    }

    @PutMapping("/{id}")
    public ResponseEntity<Usuario> update(@PathVariable Integer id, @RequestBody Usuario usuario) {
        return usuarioRepository.findById(id)
                .map(existing -> {
                    existing.setNombre(usuario.getNombre());
                    existing.setUsername(usuario.getUsername());
                    existing.setPassword(usuario.getPassword());
                    existing.setRol(usuario.getRol());
                    Usuario s = usuarioRepository.save(existing);
                    return ResponseEntity.ok(s);
                }).orElse(ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Integer id) {
        if (!usuarioRepository.existsById(id)) return ResponseEntity.notFound().build();
        usuarioRepository.deleteById(id);
        return ResponseEntity.noContent().build();
    }
}
