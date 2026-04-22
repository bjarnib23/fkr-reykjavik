import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import './Process.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function Process() {
  const [page, setPage] = useState({})

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'process')
        if (p) setPage(p)
      })
  }, [])

  const image = page.images?.[0]

  return (
    <main className="process">
      <div className="process-hero">
        <div className="process-hero-left">
          <h1>{page.title || ''}</h1>
        </div>
        <div className="process-hero-right">
          {page.body_text && <p>{stripHtml(page.body_text)}</p>}
        </div>
      </div>

      {image && (
        <div className="process-image">
          <img src={image} alt={page.title} />
        </div>
      )}

      <div className="process-cta-band">
        {page.cta_text && <p>{page.cta_text}</p>}
        <Link to="/boka-tima" className="process-cta">Bóka tíma →</Link>
      </div>
    </main>
  )
}

export default Process
