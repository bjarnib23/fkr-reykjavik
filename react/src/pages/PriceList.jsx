import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import './PriceList.css'

function stripHtml(html) {
  return html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : ''
}

function fmt(price) {
  if (price === null || price === undefined) return null
  return 'kr. ' + Number(price).toLocaleString('is-IS')
}

function PriceList() {
  const [grades, setGrades]   = useState([])
  const [rows, setRows]       = useState([])
  const [page, setPage]       = useState({})
  const [imgIdx, setImgIdx]   = useState(0)
  const [open, setOpen]       = useState(null)

  useEffect(() => {
    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/pricelist`, { cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        setGrades(data.grades || [])
        setRows(data.rows   || [])
      })

    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'pricelist')
        if (p) setPage(p)
      })
  }, [])

  const images = page.images || []

  function toggle(i) { setOpen(open === i ? null : i) }

  function fromPrice(row) {
    for (const g of grades) {
      const val = row.prices?.[g]
      if (val !== null && val !== undefined) return val
    }
    return null
  }

  return (
    <main className="pl">
      <div className="pl-hero">
        <div className="pl-hero-left">
          <h1>{page.title || ''}</h1>
          {page.body_text && <p className="pl-hero-desc">{stripHtml(page.body_text)}</p>}
        </div>
        {images.length > 0 && (
          <div className="pl-hero-image">
            <img src={images[imgIdx]} alt={page.title} />
            {images.length > 1 && (
              <div className="pl-img-nav">
                <button onClick={() => setImgIdx(i => (i - 1 + images.length) % images.length)}>←</button>
                <button onClick={() => setImgIdx(i => (i + 1) % images.length)}>→</button>
              </div>
            )}
          </div>
        )}
      </div>

      <div className="pl-table">
        {rows.map((row, i) => {
          const from = fromPrice(row)
          const isOpen = open === i
          const gradeEntries = grades
            .map(g => ({ grade: g, price: row.prices?.[g] }))
            .filter(e => e.price !== null && e.price !== undefined)

          return (
            <div key={i} className={`pl-row-wrap${isOpen ? ' open' : ''}`}>
              <button className="pl-row" onClick={() => toggle(i)}>
                <span className="pl-row-num">{String(i + 1).padStart(2, '0')}</span>
                <span className="pl-row-item">{row.item}</span>
                <span className="pl-row-price">{fmt(from)}</span>

                <span className="pl-row-toggle">{isOpen ? '−' : '+'}</span>
              </button>

              {isOpen && (
                <div className="pl-grades">
                  {gradeEntries.map(({ grade, price }) => (
                    <div key={grade} className="pl-grade-row">
                      <span className="pl-grade-label">{grade}</span>
                      <span className="pl-grade-price">{fmt(price)}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </div>

      <div className="pl-cta-band">
        {page.cta_text && <Link to="/boka-tima" className="pl-cta">{page.cta_text}</Link>}
      </div>
    </main>
  )
}

export default PriceList
