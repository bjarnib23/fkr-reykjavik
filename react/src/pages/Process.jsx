import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import './Process.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function Process() {
  const [page, setPage] = useState({})
  const [steps, setSteps] = useState([])

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'process')
        if (p) setPage(p)
      })

    fetch('http://fkr-reykjavik.ddev.site/api/fkr/process-steps', { cache: 'no-store' })
      .then(r => r.json())
      .then(setSteps)
  }, [])

  const images = page.images || []
  const [slideIndex, setSlideIndex] = useState(0)

  useEffect(() => {
    if (images.length < 2) return
    const id = setInterval(() => setSlideIndex(i => (i + 1) % images.length), 10000)
    return () => clearInterval(id)
  }, [images.length])

  return (
    <main className="process">
      <div className="process-header">
        {page.subtitle && <p className="process-header-label">{page.subtitle}</p>}
        <h1>{page.title || ''}</h1>
      </div>

      <div className="process-body">
        <div className="process-image-col">
          <div className="process-image-sticky">
            {images.map((src, i) => (
              <img
                key={src}
                src={src}
                alt=""
                className={`process-sticky-img${i === slideIndex ? ' process-sticky-img--active' : ''}`}
              />
            ))}
          </div>
        </div>

        <div className="process-right">
          <div className="process-steps">
            {steps.map((step, i) => (
              <div key={step.id} className="process-step">
                <span className="process-step-number">{String(i + 1).padStart(2, '0')}</span>
                <div className="process-step-body">
                  <h3>{step.title}</h3>
                  {step.description && <p>{stripHtml(step.description)}</p>}
                </div>
              </div>
            ))}
          </div>

          <div className="process-closing">
            {page.body_text && <p>{stripHtml(page.body_text)}</p>}
            {page.cta_text && (
              <Link to="/boka-tima" className="process-cta">{page.cta_text} →</Link>
            )}
          </div>
        </div>
      </div>

    </main>
  )
}

export default Process
