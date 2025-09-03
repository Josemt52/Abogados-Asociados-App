import React, { useState, useEffect } from 'react';
import { Calendar, Clock, ArrowRight } from 'lucide-react';
import './Blog.css';

interface BlogPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  category: string;
  image_url: string;
  read_time: number;
  created_at: string;
}

const Blog: React.FC = () => {
  const [articles, setArticles] = useState<BlogPost[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchArticles = async () => {
      try {
        const response = await fetch('http://localhost:8080/api/blog/published?limit=6');
        if (response.ok) {
          const data = await response.json();
          setArticles(data);
        } else {
          // Fallback to static data if backend is not available
          setArticles(fallbackArticles);
        }
      } catch (error) {
        console.error('Error fetching articles:', error);
        // Fallback to static data
        setArticles(fallbackArticles);
      } finally {
        setLoading(false);
      }
    };

    fetchArticles();
  }, []);

  // Fallback static data
  const fallbackArticles = [
    {
      id: 1,
      title: 'Nuevas Reformas en el Código Civil 2024',
      slug: 'nuevas-reformas-codigo-civil-2024',
      excerpt: 'Análisis de las principales modificaciones que afectan a contratos, herencias y responsabilidad civil.',
      created_at: '2024-03-15',
      read_time: 5,
      category: 'Derecho Civil',
      image_url: 'https://images.pexels.com/photos/5668772/pexels-photo-5668772.jpeg?auto=compress&cs=tinysrgb&w=400'
    },
    {
      id: 2,
      title: 'Guía Completa: Proceso de Divorcio Express',
      slug: 'guia-completa-proceso-divorcio-express',
      excerpt: 'Todo lo que necesitas saber sobre el divorcio de mutuo acuerdo y los requisitos legales actuales.',
      created_at: '2024-03-10',
      read_time: 7,
      category: 'Derecho Familia',
      image_url: 'https://images.pexels.com/photos/5668858/pexels-photo-5668858.jpeg?auto=compress&cs=tinysrgb&w=400'
    },
    {
      id: 3,
      title: 'Derechos del Trabajador ante Despidos',
      slug: 'derechos-trabajador-ante-despidos',
      excerpt: 'Conoce tus derechos y las indemnizaciones que te corresponden según el tipo de despido.',
      created_at: '2024-03-05',
      read_time: 6,
      category: 'Derecho Laboral',
      image_url: 'https://images.pexels.com/photos/5668473/pexels-photo-5668473.jpeg?auto=compress&cs=tinysrgb&w=400'
    }
  ];

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  };

  if (loading) {
    return (
      <section id="blog" className="blog">
        <div className="container">
          <div className="blog-header">
            <h2 className="section-title">Blog Jurídico</h2>
            <p className="section-description">Cargando artículos...</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section id="blog" className="blog">
      <div className="container">
        <div className="blog-header">
          <h2 className="section-title">Blog Jurídico</h2>
          <p className="section-description">
            Mantente informado con nuestros artículos sobre las últimas novedades 
            legales y consejos prácticos para proteger tus derechos.
          </p>
        </div>

        <div className="articles-grid">
          {loading ? (
            <div>Cargando artículos...</div>
          ) : (
            (articles.length > 0 ? articles : fallbackArticles).map((article, index) => (
              <article key={article.id || index} className="article-card">
                <div className="article-image">
                  <img src={article.image_url || (article as any).image} alt={article.title} />
                  <div className="article-category">{article.category}</div>
                </div>
                <div className="article-content">
                  <h3 className="article-title">{article.title}</h3>
                  <p className="article-excerpt">{article.excerpt}</p>
                  <div className="article-meta">
                    <div className="meta-item">
                      <Calendar className="meta-icon" />
                      <span>{article.created_at ? formatDate(article.created_at) : (article as any).date}</span>
                    </div>
                    <div className="meta-item">
                      <Clock className="meta-icon" />
                      <span>{article.read_time || (article as any).readTime} min</span>
                    </div>
                  </div>
                  <button className="read-more-btn">
                    Leer más
                    <ArrowRight className="btn-icon" />
                  </button>
                </div>
              </article>
            ))
          )}
        </div>

        <div className="blog-cta">
          <h3>¿Necesitas asesoría legal personalizada?</h3>
          <p>Nuestros artículos son informativos, pero cada caso es único. Contacta con nosotros para una consulta personalizada.</p>
          <button className="cta-btn">Solicitar Consulta</button>
        </div>
      </div>
    </section>
  );
};

export default Blog;