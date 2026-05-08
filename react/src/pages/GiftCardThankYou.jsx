import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'

const API = import.meta.env.VITE_DRUPAL_URL

function GiftCardThankYou() {
  const [labels, setLabels] = useState({})

  useEffect(() => {
    fetch(`${API}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'giftcard_thankyou')
        if (p) setLabels(p)
      })
  }, [])

  return (
    <main style={{ maxWidth: 600, margin: '80px auto', padding: '0 24px', textAlign: 'center' }}>
      <h1 style={{ fontSize: 36, letterSpacing: 4, marginBottom: 24 }}>{labels.subtitle}</h1>
      <p style={{ fontSize: 16, lineHeight: 1.6, marginBottom: 40 }} dangerouslySetInnerHTML={{ __html: labels.body_text }} />
      <Link
        to="/"
        style={{
          display: 'inline-block',
          background: '#263A38',
          color: 'white',
          padding: '14px 32px',
          textDecoration: 'none',
          fontSize: 15,
          fontFamily: 'inherit',
        }}
      >
        {labels.primary_button}
      </Link>
    </main>
  )
}

export default GiftCardThankYou
