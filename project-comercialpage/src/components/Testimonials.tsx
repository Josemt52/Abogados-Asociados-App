import React from 'react';
import { Star, Quote } from 'lucide-react';
import './Testimonials.css';

const Testimonials: React.FC = () => {
  const testimonials = [
    {
      name: 'María García',
      case: 'Divorcio y Custodia',
      rating: 5,
      text: 'El Dr. González me ayudó en un proceso de divorcio muy complicado. Su profesionalidad y dedicación fueron excepcionales. Logró un acuerdo favorable para la custodia de mis hijos.',
      image: 'https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=150'
    },
    {
      name: 'Juan Martínez',
      case: 'Accidente de Tráfico',
      rating: 5,
      text: 'Después de un accidente grave, el estudio jurídico me representó de manera excelente. Conseguimos una indemnización justa que cubrió todos mis gastos médicos y más.',
      image: 'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=150'
    },
    {
      name: 'Ana López',
      case: 'Despido Improcedente',
      rating: 5,
      text: 'Me despidieron injustamente de mi trabajo. El Dr. González demostró que el despido era improcedente y conseguimos una compensación económica muy satisfactoria.',
      image: 'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=150'
    }
  ];

  const successCases: Array<{
    title: string;
    description: string;
    result: string;
  }> = [];

  return (
    <section id="casos-exito" className="testimonials">
      <div className="container">
        <div className="testimonials-header">
          <h2 className="section-title">Casos de Éxito y Testimonios</h2>
          <p className="section-description">
            La satisfacción de nuestros clientes y los resultados obtenidos 
            son nuestro mejor aval profesional.
          </p>
        </div>

        <div className="content-grid">
          <div className="testimonials-section">
            <h3 className="subsection-title">Testimonios de Clientes</h3>
            <div className="testimonials-grid">
              {testimonials.map((testimonial, index) => (
                <div key={index} className="testimonial-card">
                  <div className="testimonial-header">
                    <img 
                      src={testimonial.image} 
                      alt={testimonial.name}
                      className="testimonial-avatar"
                    />
                    <div className="testimonial-info">
                      <h4 className="testimonial-name">{testimonial.name}</h4>
                      <p className="testimonial-case">{testimonial.case}</p>
                      <div className="testimonial-rating">
                        {[...Array(testimonial.rating)].map((_, i) => (
                          <Star key={i} className="star-filled" />
                        ))}
                      </div>
                    </div>
                  </div>
                  <div className="testimonial-content">
                    <Quote className="quote-icon" />
                    <p className="testimonial-text">{testimonial.text}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="success-cases-section">
            <h3 className="subsection-title">Casos de Éxito Destacados</h3>
            <div className="success-cases">
              {successCases.map((case_, index) => (
                <div key={index} className="success-case">
                  <div className="case-content">
                    <h4 className="case-title">{case_.title}</h4>
                    <p className="case-description">{case_.description}</p>
                  </div>
                  <div className="case-result">
                    <span className="result-badge">{case_.result}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default Testimonials;