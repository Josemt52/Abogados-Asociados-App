package com.abogados.backend.config;

import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import com.abogados.backend.models.Rol;
import com.abogados.backend.repositories.RolRepository;

@Configuration
public class DataInitializer {

    @Bean
    public CommandLineRunner initRoles(RolRepository rolRepository) {
        return args -> {
            // Insertar rol 'admin' si no existe
            if (rolRepository.findByNombreIgnoreCase("admin").isEmpty()) {
                Rol admin = new Rol();
                admin.setNombre("admin");
                rolRepository.save(admin);
                System.out.println("✓ Rol 'admin' creado automáticamente");
            }

            // Insertar rol 'usuario' si no existe
            if (rolRepository.findByNombreIgnoreCase("usuario").isEmpty()) {
                Rol usuario = new Rol();
                usuario.setNombre("usuario");
                rolRepository.save(usuario);
                System.out.println("✓ Rol 'usuario' creado automáticamente");
            }

            System.out.println("✓ Inicialización de roles completada");
        };
    }
}
