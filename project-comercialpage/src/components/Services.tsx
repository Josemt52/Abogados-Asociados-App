import React, { useState, useEffect } from 'react';
import { Scale, Users, Heart, Briefcase, Home, Car, ArrowRight } from 'lucide-react';
import './Services.css';

interface Service {
  id: number;
  title: string;
  description: string;
  service_type: string;
  features: string[];
  icon: string;
}

const Services: React.FC = () => {
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchServices = async () => {
      try {
        const response = await fetch('http://localhost:8080/api/services/public');
        if (response.ok) {
          const data = await response.json();
          setServices(data);
        } else {
          // Fallback to static data if backend is not available
          setServices(fallbackServices);
        }
      } catch (error) {
        console.error('Error fetching services:', error);
        // Fallback to static data
        setServices(fallbackServices);
      } finally {
        setLoading(false);
      }
    };

    fetchServices();
  }, []);

  // Fallback static data
  const fallbackServices = [
    {
      id: 1,
      icon: 'Scale',
      title: 'Derecho Civil',
      description: 'Contratos, responsabilidad civil, propiedad, herencias y sucesiones.',
      features: ['Contratos civiles', 'Responsabilidad civil', 'Herencias', 'Propiedad'],
      service_type: 'civil'
    },
    {
      id: 2,
      icon: 'Users',
      title: 'Derecho Penal',
      description: 'Defensa penal, delitos contra las personas, patrimonio y orden público.',
      features: ['Defensa penal', 'Delitos económicos', 'Violencia de género', 'Recursos'],
      service_type: 'penal'
    },
    {
      id: 3,
      icon: 'Heart',
      title: 'Derecho de Familia',
      description: 'Divorcios, custodia, pensiones alimenticias y adopciones.',
      features: ['Divorcios', 'Custodia menores', 'Pensiones', 'Adopciones'],
      service_type: 'familia'
    },
    {
      id: 4,
      icon: 'Briefcase',
      title: 'Derecho Laboral',
      description: 'Despidos, reclamaciones salariales, accidentes laborales.',
      features: ['Despidos', 'Reclamaciones', 'Accidentes', 'Convenios'],
      service_type: 'laboral'
    },
    {
      id: 5,
      icon: 'Home',
      title: 'Derecho Inmobiliario',
      description: 'Compraventa, arrendamientos, desahucios y comunidades.',
      features: ['Compraventa', 'Arrendamientos', 'Desahucios', 'Comunidades'],
      service_type: 'inmobiliario'
    },
    {
      id: 6,
      icon: 'Car',
      title: 'Derecho del Tráfico',
      description: 'Accidentes de tráfico, multas, retirada de carnet.',
      features: ['Accidentes', 'Multas', 'Carnet puntos', 'Indemnizaciones'],
      service_type: 'trafico'
    }
  ];

  const getIcon = (iconName: string) => {
    const icons: { [key: string]: React.ReactNode } = {
      'Scale': <Scale />,
      'Users': <Users />,
      'Heart': <Heart />,
      'Briefcase': <Briefcase />,
      'Home': <Home />,
      'Car': <Car />,
    };
    return icons[iconName] || <Scale />;
  };

  const scrollToContact = () => {
    const element = document.getElementById('contacto');
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  if (loading) {
    return (
      <section id="servicios" className="services">
        <div className="container">
          <div className="services-header">
            <h2 className="section-title">Nuestros Servicios Legales</h2>
            <p className="section-description">Cargando servicios...</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section id="servicios" className="services">
      <div className="container">
        <div className="services-header">
          <h2 className="section-title">Nuestros Servicios Legales</h2>
          <p className="section-description">
            Ofrecemos asesoría legal integral en múltiples áreas del derecho, 
            adaptándonos a las necesidades específicas de cada cliente.
          </p>
        </div>
        
        <div className="services-grid">
          {(services.length > 0 ? services : fallbackServices).map((service, index) => (
            <div key={service.id || index} className="service-card">
              <div className="service-icon">
                {getIcon(service.icon)}
              </div>
              <h3 className="service-title">{service.title}</h3>
              <p className="service-description">{service.description}</p>
              <ul className="service-features">
                {service.features.map((feature, featureIndex) => (
                  <li key={featureIndex} className="service-feature">
                    {feature}
                  </li>
                ))}
              </ul>
              <button onClick={scrollToContact} className="service-btn">
                Consultar
                <ArrowRight className="btn-icon" />
              </button>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default Services;