import React, { useState } from 'react';
import { Menu, X, Scale } from 'lucide-react';
import './Header.css';

const Header: React.FC = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  const toggleMenu = () => {
    setIsMenuOpen(!isMenuOpen);
  };

  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
    setIsMenuOpen(false);
  };

  return (
    <header className="header">
      <div className="container">
        <div className="header-content">
          <div className="logo">
            <Scale className="logo-icon" />
            <span className="logo-text">Estudio Jurídico Abogados & Asociados</span>
          </div>
          
          <nav className={`nav ${isMenuOpen ? 'nav-open' : ''}`}>
            <ul className="nav-list">
              <li><button onClick={() => scrollToSection('inicio')} className="nav-link">Inicio</button></li>
              <li><button onClick={() => scrollToSection('sobre-nosotros')} className="nav-link">Sobre Nosotros</button></li>
              <li><button onClick={() => scrollToSection('servicios')} className="nav-link">Servicios</button></li>
              <li><button onClick={() => scrollToSection('casos-exito')} className="nav-link">Casos de Éxito</button></li>
              <li><button onClick={() => scrollToSection('blog')} className="nav-link">Blog</button></li>
              <li><button onClick={() => scrollToSection('contacto')} className="nav-link">Contacto</button></li>
            </ul>
          </nav>

          <button className="menu-toggle" onClick={toggleMenu}>
            {isMenuOpen ? <X /> : <Menu />}
          </button>
        </div>
      </div>
    </header>
  );
};

export default Header;