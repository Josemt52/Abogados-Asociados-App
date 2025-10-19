package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import java.util.Map;
import com.abogados.backend.repositories.UsuarioRepository;
import com.abogados.backend.models.Usuario;

@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final UsuarioRepository usuarioRepository;

    public AuthController(UsuarioRepository usuarioRepository) {
        this.usuarioRepository = usuarioRepository;
    }

    record LoginRequest(String username, String password) {}

    @PostMapping("/login")
    public ResponseEntity<?> login(@RequestBody LoginRequest req) {
        return usuarioRepository.findByUsername(req.username())
                .map(user -> {
                    // NOTE: passwords are stored in plaintext in this project (TODO: hash)
                    if (user.getPassword() != null && user.getPassword().equals(req.password())) {
                        // Return user (password ignored by Json) and a mock token
                        return ResponseEntity.ok(Map.of("user", user, "token", "mock-jwt-token"));
                    } else {
                        return ResponseEntity.status(HttpStatus.UNAUTHORIZED).body(Map.of("message", "Credenciales inválidas"));
                    }
                })
                .orElse(ResponseEntity.status(HttpStatus.UNAUTHORIZED).body(Map.of("message", "Credenciales inválidas")));
    }
}
