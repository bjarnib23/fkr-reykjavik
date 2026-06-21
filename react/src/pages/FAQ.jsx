import { useState, useEffect } from 'react'
import { useLoading } from '../context/LoadingContext'
import './FAQ.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function FAQ() {
  const [faqs, setFaqs]           = useState([])
  const [open, setOpen]           = useState(null)
  const [page, setPage]           = useState({})
  const { setLoading } = useLoading()

  useEffect(() => {
    const p1 = fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/faq`, { cache: 'no-store' })
      .then(r => r.json())
      .then(data => setFaqs(data.items || []))

    const p2 = fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'faq')
        if (p) setPage(p)
      })

    Promise.allSettled([p1, p2]).then(() => setLoading(false))
  }, [])

  function toggle(i) {
    setOpen(open === i ? null : i)
  }

  return (
    <main className="faq">
      <div className="faq-hero">
        <div className="faq-hero-left">
          <h1>{page.title || ''}</h1>
        </div>
        <div className="faq-hero-right">
          {page.subtitle && <p>{page.subtitle}</p>}
        </div>
      </div>

      <div className="faq-list">
        {faqs.map((faq, i) => (
          <div key={i} className={`faq-item${open === i ? ' open' : ''}`}>
            <button className="faq-question" onClick={() => toggle(i)}>
              <span className="faq-num">{String(i + 1).padStart(2, '0')}</span>
              <span className="faq-q-text">{faq.question}</span>
              <span className="faq-toggle">{open === i ? '−' : '+'}</span>
            </button>
            {open === i && (
              <div
                className="faq-answer"
                dangerouslySetInnerHTML={{ __html: faq.answer }}
              />
            )}
          </div>
        ))}
      </div>
    </main>
  )
}

export default FAQ
