import React, { useState } from 'react';
import { Phone, Mail, MapPin, Clock, MessageCircle, Send } from 'lucide-react';
import './Contact.css';

const Contact: React.FC = () => {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: ''
  });

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitStatus, setSubmitStatus] = useState<'idle' | 'success' | 'error'>('idle');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setSubmitStatus('idle');

    try {
      const response = await fetch('http://localhost:8080/api/contacts', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: formData.name,
          email: formData.email,
          phone: formData.phone || null,
          subject: formData.subject || null,
          message: formData.message
        })
      });

      if (response.ok) {
        setSubmitStatus('success');
        setFormData({
          name: '',
          email: '',
          phone: '',
          subject: '',
          message: ''
        });
        alert('Gracias por su consulta. Nos pondremos en contacto con usted en breve.');
      } else {
        throw new Error('Error al enviar la consulta');
      }
    } catch (error) {
      console.error('Error submitting form:', error);
      setSubmitStatus('error');
      alert('Hubo un error al enviar su consulta. Por favor, intente nuevamente o contáctenos directamente.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <section id="contacto" className="contact">
      <div className="container">
        <div className="contact-header">
          <h2 className="section-title">Contacto</h2>
          <p className="section-description">
            Estamos aquí para ayudarte. Contacta con nosotros para una consulta 
            personalizada y sin compromiso.
          </p>
        </div>

        <div className="contact-content">
          <div className="contact-info">
            <div className="info-card">
              <div className="info-icon">
                <Phone />
              </div>
              <div className="info-content">
                <h3>Teléfono</h3>
                <p>+51 944 243 535</p>
                <a href="tel:+34123456789" className="contact-link">Llamar ahora</a>
              </div>
            </div>

            <div className="info-card">
              <div className="info-icon">
                <Mail />
              </div>
              <div className="info-content">
                <h3>Email</h3>
                <p>info@abogadosyasociados.com</p>
                <a href="mailto:info@abogadosyasociados.com" className="contact-link">Enviar email</a>
              </div>
            </div>

            <div className="info-card">
              <div className="info-icon">
                <MapPin />
              </div>
              <div className="info-content">
                <h3>Dirección</h3>
                <p>C. Quinta Romaña 234<br />Arequipa, Perú</p>
                <a href="#" className="contact-link">Ver en mapa</a>
              </div>
            </div>

            <div className="info-card">
              <div className="info-icon">
                <Clock />
              </div>
              <div className="info-content">
                <h3>Horario</h3>
                <p>Lunes a Viernes: 9:00 - 18:00<br />Sábados: 10:00 - 14:00</p>
              </div>
            </div>

            <div className="whatsapp-cta">
              <MessageCircle className="whatsapp-icon" />
              <div>
                <h4>¿Necesitas ayuda inmediata?</h4>
                <p>Contáctanos por WhatsApp</p>
              </div>
              <a href="https://wa.me/34123456789" className="whatsapp-btn">
                Abrir WhatsApp
              </a>
            </div>
          </div>

          <div className="contact-form-container">
            <form onSubmit={handleSubmit} className="contact-form">
              <h3>Solicitar Consulta</h3>
              
              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="name">Nombre completo *</label>
                  <input
                    type="text"
                    id="name"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    required
                    placeholder="Tu nombre completo"
                  />
                </div>
                
                <div className="form-group">
                  <label htmlFor="email">Email *</label>
                  <input
                    type="email"
                    id="email"
                    name="email"
                    value={formData.email}
                    onChange={handleChange}
                    required
                    placeholder="tu@email.com"
                  />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label htmlFor="phone">Teléfono</label>
                  <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value={formData.phone}
                    onChange={handleChange}
                    placeholder="+34 123 456 789"
                  />
                </div>
                
                <div className="form-group">
                  <label htmlFor="subject">Área legal</label>
                  <select
                    id="subject"
                    name="subject"
                    value={formData.subject}
                    onChange={handleChange}
                  >
                    <option value="">Selecciona un área</option>
                    <option value="civil">Derecho Civil</option>
                    <option value="penal">Derecho Penal</option>
                    <option value="familia">Derecho de Familia</option>
                    <option value="laboral">Derecho Laboral</option>
                    <option value="inmobiliario">Derecho Inmobiliario</option>
                    <option value="trafico">Derecho del Tráfico</option>
                    <option value="otro">Otro</option>
                  </select>
                </div>
              </div>

              <div className="form-group">
                <label htmlFor="message">Mensaje *</label>
                <textarea
                  id="message"
                  name="message"
                  value={formData.message}
                  onChange={handleChange}
                  required
                  rows={5}
                  placeholder="Describe brevemente tu consulta legal..."
                ></textarea>
              </div>

              <div className="form-footer">
                <p className="privacy-note">
                  Al enviar este formulario, acepta nuestra política de privacidad 
                  y el tratamiento de sus datos personales.
                </p>
                <button type="submit" className="submit-btn" disabled={isSubmitting}>
                  {isSubmitting ? 'Enviando...' : 'Enviar Consulta'}
                  <Send className="btn-icon" />
                </button>
              </div>
            </form>
          </div>
        </div>

        <div className="map-container">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1473.097175148115!2d-71.53625370834861!3d-16.406125163612725!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91424af83b353607%3A0x190b79f773cad38f!2sC.%20Quinta%20Roma%C3%B1a%20234%2C%20Arequipa%2004001!5e0!3m2!1ses!2spe!4v1752954471547!5m2!1ses!2spe"
            width="100%"
            height="400"
            style={{ border: 0 }}
            allowFullScreen
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
            title="Ubicación del Estudio Jurídico"
          ></iframe>
        </div>
      </div>
    </section>
  );
};

export default Contact;