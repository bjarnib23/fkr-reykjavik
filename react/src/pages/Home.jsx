import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import './Home.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function Home() {
  const [page, setPage] = useState({})

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'home')
        if (p) setPage(p)
      })
  }, [])

  const heroImage = page.images?.[0]

  return (
    <main className="home">
      <section className="home-hero">
        <div className="home-hero-left">
          <h1>{page.title || ''}</h1>
        </div>
        <div className="home-hero-right">
          {page.body_text && <p className="home-hero-desc">{stripHtml(page.body_text)}</p>}
          {page.cta_text && (
            <Link to="/boka-tima" className="home-cta">{page.cta_text} →</Link>
          )}
        </div>
      </section>

      {heroImage && (
        <div className="home-image">
          <img src={heroImage} alt={page.title} />
        </div>
      )}
    </main>
  )
}

export default Home
