package com.abogados.backend.repositories;

import org.springframework.data.jpa.repository.JpaRepository;
import com.abogados.backend.models.Contact;
import java.util.List;

public interface ContactRepository extends JpaRepository<Contact, Integer> {
    List<Contact> findByStatusOrderByCreatedAtDesc(String status);
    List<Contact> findAllByOrderByCreatedAtDesc();
}
