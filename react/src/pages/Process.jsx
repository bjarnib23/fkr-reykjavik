import { useState, useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { useLoading } from '../context/LoadingContext'
import { preloadImages } from '../context/preloadImages'
import './Process.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function Process() {
  const [page, setPage] = useState({})
  const [steps, setSteps] = useState([])
  const cardRefs = useRef([])
  const { setLoading } = useLoading()

  useEffect(() => {
    const p1 = fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'process')
        if (p) setPage(p)
      })

    const p2 = fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/process-steps`, { cache: 'no-store' })
      .then(r => r.json())
      .then(setSteps)

    Promise.allSettled([p1, p2]).then(async ([r1, r2]) => {
      const stepImgs = (r2.value || []).map(s => s.image).filter(Boolean)
      const pageImgs = (r1.value ? Object.values(r1.value).find(p => p.slug === 'process')?.images || [] : [])
      await preloadImages([...pageImgs, ...stepImgs])
      setLoading(false)
    })
  }, [])

  const images = page.images || []

  useEffect(() => {
    const cards = cardRefs.current.filter(Boolean)
    if (cards.length < 2) return

    const handleScroll = () => {
      cards.forEach((card, i) => {
        const cardRect = card.getBoundingClientRect()
        const next = cards[i + 1]

        if (next) {
          const nextRect = next.getBoundingClientRect()
          const covered = Math.max(0, cardRect.bottom - nextRect.top)
          const progress = Math.min(1, covered / cardRect.height)
          card.style.transform = `scale(${1 - 0.12 * progress})`
        }
      })
    }

    window.addEventListener('scroll', handleScroll, { passive: true })
    handleScroll()
    return () => window.removeEventListener('scroll', handleScroll)
  }, [steps.length])

return (
    <main className="process">
      <div className="process-header">
        {page.subtitle && <p className="process-header-label">{page.subtitle}</p>}
        <h1>{page.title || ''}</h1>
      </div>

      <div className="process-cards">
        {steps.map((step, i) => (
          <div
            key={step.id}
            ref={el => { cardRefs.current[i] = el }}
            className="process-card"
            style={{ top: `${80 + i * 48}px`, zIndex: i + 1 }}
          >
            <div className="process-card-left">
              <span className="process-card-num">{String(i + 1).padStart(2, '0')}</span>
              <div className="process-card-text">
                <h2>{step.title}</h2>
                {step.description && <p>{stripHtml(step.description)}</p>}
              </div>
            </div>
            {images[i] && (
              <div className="process-card-img">
                <img src={images[i]} alt={step.title} />
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="process-closing">
        <img src="/fkr-logo-light.png" className="process-closing-bg" alt="" aria-hidden="true" />
        <div className="process-closing-text">
          {page.closing_heading && <h2 className="process-closing-heading">{page.closing_heading}</h2>}
          {page.body_text && <p>{stripHtml(page.body_text)}</p>}
        </div>
        <Link to="/boka-tima" className="process-cta">Bóka tíma</Link>
      </div>

    </main>
  )
}

export default Process
