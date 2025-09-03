package com.abogados.backend.config;

import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import com.abogados.backend.models.Expediente;
import com.abogados.backend.models.Rol;
import com.abogados.backend.models.Usuario;
import com.abogados.backend.repositories.ExpedienteRepository;
import com.abogados.backend.repositories.RolRepository;
import com.abogados.backend.repositories.UsuarioRepository;

@Configuration
public class DataLoader {

    @Bean
    CommandLineRunner init(RolRepository rolRepo, UsuarioRepository userRepo, ExpedienteRepository expRepo) {
        return args -> {
            if (rolRepo.count() == 0) {
                Rol admin = new Rol(); admin.setNombre("ADMIN");
                Rol user = new Rol(); user.setNombre("USER");
                rolRepo.save(admin);
                rolRepo.save(user);

                Usuario u1 = new Usuario(); u1.setNombre("Admin"); u1.setUsername("admin"); u1.setPassword("admin"); u1.setRol(admin);
                userRepo.save(u1);

                Expediente e1 = new Expediente(); e1.setNumero("EXP-001"); e1.setMateria("Civil"); e1.setJuzgado("Juzgado 1"); e1.setEstado("Activo");
                Expediente e2 = new Expediente(); e2.setNumero("EXP-002"); e2.setMateria("Penal"); e2.setJuzgado("Juzgado 2"); e2.setEstado("En proceso");
                expRepo.save(e1);
                expRepo.save(e2);
            }
        };
    }
}
