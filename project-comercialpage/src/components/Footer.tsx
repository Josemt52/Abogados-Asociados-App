import React from 'react';
import { Scale, Phone, Mail, MapPin, Facebook, Twitter, Linkedin, Instagram } from 'lucide-react';
import './Footer.css';

const Footer: React.FC = () => {
  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <footer className="footer">
      <div className="container">
        <div className="footer-content">
          <div className="footer-section">
            <div className="footer-logo">
              <Scale className="logo-icon" />
              <span className="logo-text">Estudio Jurídico Abogados & Asociados</span>
            </div>
            <p className="footer-description">
              Más de 15 años defendiendo los derechos de nuestros clientes con 
              profesionalidad, experiencia y dedicación personalizada.
            </p>
            <div className="social-links">
              <a href="#" className="social-link" aria-label="Facebook">
                <Facebook />
              </a>
              <a href="#" className="social-link" aria-label="Twitter">
                <Twitter />
              </a>
              <a href="#" className="social-link" aria-label="LinkedIn">
                <Linkedin />
              </a>
              <a href="#" className="social-link" aria-label="Instagram">
                <Instagram />
              </a>
            </div>
          </div>

          <div className="footer-section">
            <h3 className="footer-title">Navegación</h3>
            <ul className="footer-links">
              <li><button onClick={() => scrollToSection('inicio')} className="footer-link">Inicio</button></li>
              <li><button onClick={() => scrollToSection('sobre-nosotros')} className="footer-link">Sobre Nosotros</button></li>
              <li><button onClick={() => scrollToSection('servicios')} className="footer-link">Servicios</button></li>
              <li><button onClick={() => scrollToSection('casos-exito')} className="footer-link">Casos de Éxito</button></li>
              <li><button onClick={() => scrollToSection('blog')} className="footer-link">Blog</button></li>
              <li><button onClick={() => scrollToSection('contacto')} className="footer-link">Contacto</button></li>
            </ul>
          </div>

          <div className="footer-section">
            <h3 className="footer-title">Servicios Legales</h3>
            <ul className="footer-links">
              <li><a href="#" className="footer-link">Derecho Civil</a></li>
              <li><a href="#" className="footer-link">Derecho Penal</a></li>
              <li><a href="#" className="footer-link">Derecho de Familia</a></li>
              <li><a href="#" className="footer-link">Derecho Laboral</a></li>
              <li><a href="#" className="footer-link">Derecho Inmobiliario</a></li>
              <li><a href="#" className="footer-link">Derecho del Tráfico</a></li>
            </ul>
          </div>

          <div className="footer-section">
            <h3 className="footer-title">Contacto</h3>
            <div className="contact-info">
              <div className="contact-item">
                <Phone className="contact-icon" />
                <span>+51 944 243 535</span>
              </div>
              <div className="contact-item">
                <Mail className="contact-icon" />
                <span>info@estudiojuridicogonzalez.com</span>
              </div>
              <div className="contact-item">
                <MapPin className="contact-icon" />
                <span>234 C. Quinta Romaña<br />Arequipa, Perú</span>
              </div>
            </div>
          </div>
        </div>

        <div className="footer-bottom">
          <div className="footer-bottom-content">
            <p className="copyright">
              © 2024 Estudio Jurídico Abogados & Asociados. Todos los derechos reservados.
            </p>
            <div className="legal-links">
              <a href="#" className="legal-link">Aviso Legal</a>
              <a href="#" className="legal-link">Política de Privacidad</a>
              <a href="#" className="legal-link">Política de Cookies</a>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;