package com.abogados.backend.controllers;

import org.springframework.web.bind.annotation.*;
import org.springframework.http.ResponseEntity;
import org.springframework.http.HttpStatus;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Pageable;
import com.abogados.backend.models.BlogPost;
import com.abogados.backend.repositories.BlogPostRepository;
import java.util.List;
import java.time.LocalDateTime;

@RestController
@RequestMapping("/api/blog")
@CrossOrigin(origins = {"http://localhost:5173", "http://localhost:5174", "http://localhost:3000"})
public class BlogController {

    private final BlogPostRepository blogPostRepository;

    public BlogController(BlogPostRepository blogPostRepository) {
        this.blogPostRepository = blogPostRepository;
    }

    @GetMapping("/published")
    public ResponseEntity<List<BlogPost>> getPublishedPosts(@RequestParam(defaultValue = "6") int limit) {
        Pageable pageable = PageRequest.of(0, limit);
        List<BlogPost> posts = blogPostRepository.findByIsPublishedTrueOrderByCreatedAtDesc(pageable);
        return ResponseEntity.ok(posts);
    }

    @GetMapping("/published/{slug}")
    public ResponseEntity<BlogPost> getPublishedPostBySlug(@PathVariable String slug) {
        return blogPostRepository.findBySlugAndIsPublishedTrue(slug)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @GetMapping
    public List<BlogPost> getAllPosts() {
        return blogPostRepository.findAll();
    }

    @GetMapping("/{id}")
    public ResponseEntity<BlogPost> getPost(@PathVariable Integer id) {
        return blogPostRepository.findById(id)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ResponseEntity<BlogPost> createPost(@RequestBody BlogPost blogPost) {
        BlogPost savedPost = blogPostRepository.save(blogPost);
        return ResponseEntity.status(HttpStatus.CREATED).body(savedPost);
    }

    @PutMapping("/{id}")
    public ResponseEntity<BlogPost> updatePost(@PathVariable Integer id, @RequestBody BlogPost blogPost) {
        return blogPostRepository.findById(id)
                .map(existing -> {
                    existing.setTitle(blogPost.getTitle());
                    existing.setSlug(blogPost.getSlug());
                    existing.setExcerpt(blogPost.getExcerpt());
                    existing.setContent(blogPost.getContent());
                    existing.setCategory(blogPost.getCategory());
                    existing.setImageUrl(blogPost.getImageUrl());
                    existing.setReadTime(blogPost.getReadTime());
                    existing.setIsPublished(blogPost.getIsPublished());
                    if (blogPost.getIsPublished() && existing.getPublishedAt() == null) {
                        existing.setPublishedAt(LocalDateTime.now());
                    }
                    BlogPost updated = blogPostRepository.save(existing);
                    return ResponseEntity.ok(updated);
                }).orElse(ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> deletePost(@PathVariable Integer id) {
        if (!blogPostRepository.existsById(id)) {
            return ResponseEntity.notFound().build();
        }
        blogPostRepository.deleteById(id);
        return ResponseEntity.noContent().build();
    }

    @PutMapping("/{id}/publish")
    public ResponseEntity<BlogPost> publishPost(@PathVariable Integer id) {
        return blogPostRepository.findById(id)
                .map(post -> {
                    post.setIsPublished(true);
                    post.setPublishedAt(LocalDateTime.now());
                    BlogPost updated = blogPostRepository.save(post);
                    return ResponseEntity.ok(updated);
                }).orElse(ResponseEntity.notFound().build());
    }

    @GetMapping("/category/{category}/published")
    public ResponseEntity<List<BlogPost>> getPublishedPostsByCategory(
            @PathVariable String category, 
            @RequestParam(defaultValue = "10") int limit) {
        Pageable pageable = PageRequest.of(0, limit);
        List<BlogPost> posts = blogPostRepository.findByCategoryAndIsPublishedTrueOrderByCreatedAtDesc(category, pageable);
        return ResponseEntity.ok(posts);
    }
}
