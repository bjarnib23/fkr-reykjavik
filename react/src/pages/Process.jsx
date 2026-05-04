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

  const image = page.images?.[0]

  return (
    <main className="process">
      <div className="process-hero">
        <div className="process-hero-left">
          <h1>{page.title || ''}</h1>
        </div>
        <div className="process-hero-right">
        </div>
      </div>

      <div className="process-body">
        {image && (
          <div className="process-image">
            <img src={image} alt={page.title} />
          </div>
        )}

        <div className="process-right">
          {steps.length > 0 && (
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
          )}

          <div className="process-closing">
            {page.body_text && <p>{stripHtml(page.body_text)}</p>}
            <Link to="/boka-tima" className="process-cta">Bóka tíma →</Link>
          </div>
        </div>
      </div>
    </main>
  )
}

export default Process
