import React from 'react';
import { Phone, MessageCircle, ArrowRight } from 'lucide-react';
import './Hero.css';

const Hero: React.FC = () => {
  const scrollToContact = () => {
    const element = document.getElementById('contacto');
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <section id="inicio" className="hero">
      <div className="hero-background"></div>
      <div className="container">
        <div className="hero-content">
          <div className="hero-text">
            <h1 className="hero-title">
              Defendiendo tus derechos con 
              <span className="highlight"> experiencia y dedicación</span>
            </h1>
            <p className="hero-description">
              Más de 15 años de experiencia en derecho civil, penal, familiar y laboral. 
              Brindamos asesoría legal integral con un enfoque personalizado para cada cliente.
            </p>
            <div className="hero-actions">
              <button onClick={scrollToContact} className="btn-primary">
                Consulta Gratuita
                <ArrowRight className="btn-icon" />
              </button>
              <div className="contact-buttons">
                <a href="tel:+34123456789" className="contact-btn">
                  <Phone className="contact-icon" />
                  <span>Llamar Ahora</span>
                </a>
                <a href="https://wa.me/+51944243535" className="contact-btn whatsapp">
                  <MessageCircle className="contact-icon" />
                  <span>WhatsApp</span>
                </a>
              </div>
            </div>
          </div>
          <div className="hero-image">
            <img 
              src="https://images.pexels.com/photos/5668882/pexels-photo-5668882.jpeg?auto=compress&cs=tinysrgb&w=600" 
              alt="Abogado profesional"
              className="lawyer-photo"
            />
          </div>
        </div>
      </div>
    </section>
  );
};

export default Hero;