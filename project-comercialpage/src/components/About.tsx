import React from 'react';
import { Award, Users, Clock, CheckCircle } from 'lucide-react';
import './About.css';

const About: React.FC = () => {
  const achievements = [
    { icon: <Award />, number: '15+', text: 'Años de Experiencia' },
    { icon: <Users />, number: '500+', text: 'Clientes Satisfechos' },
    { icon: <CheckCircle />, number: '95%', text: 'Casos Exitosos' },
    { icon: <Clock />, number: '24/7', text: 'Atención Disponible' }
  ];

  return (
    <section id="sobre-nosotros" className="about">
      <div className="container">
        <div className="about-content">
          <div className="about-text">
            <h2 className="section-title">Sobre el Abogado</h2>
            <h3 className="about-name">Dr. Juan Antonio Arias Carrasco</h3>
            <p className="about-description">
              Licenciado en Derecho por la Universidad ..... con más de 15 años 
              de experiencia en el ejercicio de la abogacía. Especializado en derecho civil, 
              penal, familiar y laboral.
            </p>
            <div className="credentials">
              <h4>Certificaciones y Colegiatura:</h4>
              <ul className="credentials-list">
                <li>Colegio de Abogados de Arequipa </li>
                <li>Máster en Derecho Penal y Procesal Penal</li>
                <li>Especialización en Derecho de Familia</li>
                <li>Certificación en Mediación Civil y Mercantil</li>
              </ul>
            </div>
            <div className="achievements-grid">
              {achievements.map((achievement, index) => (
                <div key={index} className="achievement-card">
                  <div className="achievement-icon">
                    {achievement.icon}
                  </div>
                  <div className="achievement-content">
                    <div className="achievement-number">{achievement.number}</div>
                    <div className="achievement-text">{achievement.text}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div className="about-image">
            <img 
              src="https://images.pexels.com/photos/5668473/pexels-photo-5668473.jpeg?auto=compress&cs=tinysrgb&w=600" 
              alt="Dr. Carlos González en su oficina"
              className="about-photo"
            />
            <div className="experience-badge">
              <span className="badge-number">15+</span>
              <span className="badge-text">Años de Experiencia</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default About;