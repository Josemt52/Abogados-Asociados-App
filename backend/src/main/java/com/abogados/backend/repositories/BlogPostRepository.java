package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.domain.Pageable;
import com.abogados.backend.models.BlogPost;
import java.util.List;
import java.util.Optional;

public interface BlogPostRepository extends JpaRepository<BlogPost, Integer> {
    List<BlogPost> findByIsPublishedTrueOrderByCreatedAtDesc(Pageable pageable);
    List<BlogPost> findByIsPublishedTrueOrderByCreatedAtDesc();
    List<BlogPost> findByCategoryAndIsPublishedTrueOrderByCreatedAtDesc(String category, Pageable pageable);
    Optional<BlogPost> findBySlugAndIsPublishedTrue(String slug);
    long countByIsPublishedTrue();
}
