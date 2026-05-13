import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import './Home.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function Home() {
  const [page, setPage] = useState({})
  const [slideIndex, setSlideIndex] = useState(0)

  useEffect(() => {
    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'home')
        if (p) setPage(p)
      })
  }, [])

  const images = page.images || []

  useEffect(() => {
    if (images.length < 2) return
    const id = setInterval(() => {
      setSlideIndex(i => (i + 1) % images.length)
    }, 10000)
    return () => clearInterval(id)
  }, [images.length])

  return (
    <main className="home">
      {/* Desktop: 3-column grid */}
      <section className="home-hero home-hero--desktop">
        <div className="home-hero-col home-hero-col--left">
          {images[0] && <img src={images[0]} alt="" className="home-hero-img" />}
          {images[1] && <img src={images[1]} alt="" className="home-hero-img" />}
        </div>

        <div className="home-hero-col home-hero-col--center">
          {images[2] && <img src={images[2]} alt="" className="home-hero-img home-hero-img--main" />}
          <div className="home-hero-overlay">
            <p className="home-hero-label">{page.subtitle || ''}</p>
            {page.body_text && <p className="home-hero-body">{stripHtml(page.body_text)}</p>}
            <h1>{page.title || ''}</h1>
            {page.cta_text && (
              <Link to="/boka-tima" className="home-cta">{page.cta_text}</Link>
            )}
          </div>
        </div>

        <div className="home-hero-col home-hero-col--right">
          {images[3] && <img src={images[3]} alt="" className="home-hero-img" />}
          {images[4] && <img src={images[4]} alt="" className="home-hero-img" />}
        </div>
      </section>

      {/* Mobile: slideshow */}
      <section className="home-hero home-hero--mobile">
        {images.map((src, i) => (
          <img
            key={src}
            src={src}
            alt=""
            className={`home-slide-img${i === slideIndex ? ' home-slide-img--active' : ''}`}
          />
        ))}
        <div className="home-hero-overlay">
          <p className="home-hero-label">{page.subtitle || ''}</p>
          <h1>{page.title || ''}</h1>
          {page.body_text && <p className="home-hero-body">{stripHtml(page.body_text)}</p>}
          {page.cta_text && (
            <Link to="/boka-tima" className="home-cta">{page.cta_text}</Link>
          )}
        </div>
        <div className="home-slide-dots">
          {images.map((_, i) => (
            <button
              key={i}
              className={`home-slide-dot${i === slideIndex ? ' home-slide-dot--active' : ''}`}
              onClick={() => setSlideIndex(i)}
            />
          ))}
        </div>
      </section>

      {/* Divider */}
      {page.section_label && (
        <div className="home-divider">
          <p>{page.section_label}</p>
        </div>
      )}

      {/* Nav cards */}
      {page.nav_cards?.length > 0 && (
        <section
          className="home-nav-cards"
          style={{ gridTemplateColumns: `repeat(${page.nav_cards.length}, 1fr)` }}
        >
          {page.nav_cards.map((card) => (
            <Link key={card.path} to={card.path} className="home-nav-card">
              {card.image && <img src={card.image} alt={card.label} />}
              <span className="home-nav-card-label">{card.label}</span>
            </Link>
          ))}
        </section>
      )}
    </main>
  )
}

export default Home
