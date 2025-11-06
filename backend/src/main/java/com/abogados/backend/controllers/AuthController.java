package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import java.util.Map;
import com.abogados.backend.repositories.UsuarioRepository;
import com.abogados.backend.security.JwtService;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private static final Logger logger = LoggerFactory.getLogger(AuthController.class);

    private final UsuarioRepository usuarioRepository;
    private final PasswordEncoder passwordEncoder;
    private final JwtService jwtService;

    public AuthController(UsuarioRepository usuarioRepository, PasswordEncoder passwordEncoder, JwtService jwtService) {
        this.usuarioRepository = usuarioRepository;
        this.passwordEncoder = passwordEncoder;
        this.jwtService = jwtService;
    }

    record LoginRequest(String username, String password) {}

    @PostMapping("/login")
    public ResponseEntity<?> login(@RequestBody LoginRequest req) {
        logger.info("Intento de login para usuario: {}", req.username());
        
        return usuarioRepository.findByUsername(req.username())
                .map(user -> {
                    logger.info("Usuario encontrado: {}", user.getUsername());
                    logger.info("Password en DB (primeros 20 chars): {}", user.getPassword().substring(0, Math.min(20, user.getPassword().length())));
                    logger.info("Password ingresado: {}", req.password());
                    
                    // Verificar contraseña con BCrypt
                    boolean matches = passwordEncoder.matches(req.password(), user.getPassword());
                    logger.info("Password matches: {}", matches);
                    
                    if (user.getPassword() != null && matches) {
                        // Generar JWT real
                        String token = jwtService.generateToken(user.getUsername(), user.getRol().getNombre());
                        
                        // Log de login exitoso
                        logger.info("Login exitoso para usuario: {}", user.getUsername());
                        
                        return ResponseEntity.ok(Map.of("user", user, "token", token));
                    } else {
                        // Log de intento fallido
                        logger.warn("Intento de login fallido para usuario: {} - Password no coincide", req.username());
                        return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
                                .body(Map.of("message", "Credenciales inválidas"));
                    }
                })
                .orElseGet(() -> {
                    // Log de usuario no encontrado
                    logger.warn("Intento de login con usuario inexistente: {}", req.username());
                    return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
                            .body(Map.of("message", "Credenciales inválidas"));
                });
    }
}
