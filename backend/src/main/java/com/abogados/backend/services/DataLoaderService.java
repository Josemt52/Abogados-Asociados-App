package com.abogados.backend.services;

import org.springframework.boot.CommandLineRunner;
import org.springframework.stereotype.Component;
import com.abogados.backend.models.BlogPost;
import com.abogados.backend.repositories.BlogPostRepository;
import com.abogados.backend.repositories.ServiceRepository;
import java.time.LocalDateTime;
import java.util.Arrays;
import java.util.List;

@Component
public class DataLoaderService implements CommandLineRunner {

    private final BlogPostRepository blogPostRepository;
    private final ServiceRepository serviceRepository;

    public DataLoaderService(BlogPostRepository blogPostRepository, ServiceRepository serviceRepository) {
        this.blogPostRepository = blogPostRepository;
        this.serviceRepository = serviceRepository;
    }

    @Override
    public void run(String... args) throws Exception {
        loadServices();
        loadBlogPosts();
    }

    private void loadServices() {
        if (serviceRepository.count() == 0) {
            List<com.abogados.backend.models.Service> services = Arrays.asList(
                new com.abogados.backend.models.Service(
                    "Derecho Civil",
                    "Representación legal en asuntos civiles, contratos, responsabilidad civil y disputas patrimoniales.",
                    "civil",
                    Arrays.asList("Contratos", "Responsabilidad civil", "Disputas patrimoniales", "Derecho de familia"),
                    "scale"
                ),
                new com.abogados.backend.models.Service(
                    "Derecho Penal",
                    "Defensa penal especializada en delitos, procedimientos penales y representación en juicios.",
                    "penal",
                    Arrays.asList("Defensa penal", "Procedimientos penales", "Juicios orales", "Recursos"),
                    "briefcase"
                ),
                new com.abogados.backend.models.Service(
                    "Derecho Laboral",
                    "Asesoría y representación en conflictos laborales, despidos y derechos de los trabajadores.",
                    "laboral",
                    Arrays.asList("Conflictos laborales", "Despidos", "Derechos laborales", "Negociación colectiva"),
                    "users"
                ),
                new com.abogados.backend.models.Service(
                    "Derecho Comercial",
                    "Constitución de empresas, contratos comerciales y asesoría empresarial integral.",
                    "comercial",
                    Arrays.asList("Constitución de empresas", "Contratos comerciales", "Asesoría empresarial", "Fusiones"),
                    "heart"
                ),
                new com.abogados.backend.models.Service(
                    "Derecho de Familia",
                    "Divorcios, custodia de menores, pensiones alimenticias y asuntos de familia.",
                    "familia",
                    Arrays.asList("Divorcios", "Custodia de menores", "Pensiones alimenticias", "Adopciones"),
                    "home"
                ),
                new com.abogados.backend.models.Service(
                    "Accidentes de Tránsito",
                    "Representación en casos de accidentes de tránsito, seguros y indemnizaciones.",
                    "transito",
                    Arrays.asList("Accidentes vehiculares", "Seguros", "Indemnizaciones", "SOAT"),
                    "car"
                )
            );
            
            serviceRepository.saveAll(services);
            System.out.println("Servicios cargados exitosamente");
        }
    }

    private void loadBlogPosts() {
        if (blogPostRepository.count() == 0) {
            List<BlogPost> posts = Arrays.asList(
                createBlogPost(
                    "Nuevas Reformas en el Código Civil 2024",
                    "nuevas-reformas-codigo-civil-2024",
                    "Análisis detallado de las principales reformas al Código Civil que entrarán en vigencia este año.",
                    "Las reformas al Código Civil de 2024 representan un cambio significativo...",
                    "Derecho Civil",
                    "https://via.placeholder.com/400x200",
                    5,
                    true
                ),
                createBlogPost(
                    "Derechos Laborales: Guía para Trabajadores",
                    "derechos-laborales-guia-trabajadores",
                    "Una guía completa sobre los derechos fundamentales que todo trabajador debe conocer.",
                    "En el ámbito laboral, es fundamental conocer nuestros derechos...",
                    "Derecho Laboral",
                    "https://via.placeholder.com/400x200",
                    7,
                    true
                ),
                createBlogPost(
                    "Cómo Constituir una Empresa en Colombia",
                    "como-constituir-empresa-colombia",
                    "Paso a paso para constituir legalmente tu empresa y los requisitos necesarios.",
                    "La constitución de una empresa requiere seguir varios pasos legales...",
                    "Derecho Comercial",
                    "https://via.placeholder.com/400x200",
                    8,
                    true
                ),
                createBlogPost(
                    "Proceso de Divorcio: Lo que Debes Saber",
                    "proceso-divorcio-que-debes-saber",
                    "Información esencial sobre el proceso de divorcio y los aspectos legales involucrados.",
                    "El divorcio es un proceso legal que requiere asesoría especializada...",
                    "Derecho de Familia",
                    "https://via.placeholder.com/400x200",
                    6,
                    true
                ),
                createBlogPost(
                    "Accidentes de Tránsito: Tus Derechos",
                    "accidentes-transito-derechos",
                    "Conoce tus derechos en caso de accidentes de tránsito y cómo proceder legalmente.",
                    "Los accidentes de tránsito pueden tener consecuencias legales importantes...",
                    "Accidentes",
                    "https://via.placeholder.com/400x200",
                    4,
                    true
                ),
                createBlogPost(
                    "Contratos: Elementos Esenciales",
                    "contratos-elementos-esenciales",
                    "Los elementos que no pueden faltar en un contrato para que sea válido legalmente.",
                    "Un contrato válido debe cumplir con ciertos elementos esenciales...",
                    "Derecho Civil",
                    "https://via.placeholder.com/400x200",
                    5,
                    true
                )
            );
            
            blogPostRepository.saveAll(posts);
            System.out.println("Posts del blog cargados exitosamente");
        }
    }
    
    private BlogPost createBlogPost(String title, String slug, String excerpt, String content, 
                                   String category, String imageUrl, Integer readTime, Boolean isPublished) {
        BlogPost post = new BlogPost(title, slug, excerpt, content, category, imageUrl, readTime);
        post.setIsPublished(isPublished);
        if (isPublished) {
            post.setPublishedAt(LocalDateTime.now().minusDays((long) (Math.random() * 30)));
        }
        return post;
    }
}
