import { useState, useEffect } from 'react'
import './Footer.css'

function Footer() {
  const [s, setS] = useState({})

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/settings', { cache: 'no-store' })
      .then(r => r.json())
      .then(setS)
  }, [])

  return (
    <footer className="footer">
      <div className="footer-main">
        <div className="footer-col footer-col--brand">
          <p className="footer-brand">{s.site_name || 'FKR'}</p>
          {s.footer_heading && <p className="footer-tagline">{s.footer_heading}</p>}
        </div>

        {(s.address || s.hours) && (
          <div className="footer-col">
            <p className="footer-col-label">Studio</p>
            {s.address && <p>{s.address}</p>}
            {s.hours   && <p>{s.hours}</p>}
          </div>
        )}

        {(s.phone || s.email) && (
          <div className="footer-col">
            <p className="footer-col-label">Contact</p>
            {s.phone && <p>{s.phone}</p>}
            {s.email && <p>{s.email}</p>}
          </div>
        )}

        {s.instagram && (
          <div className="footer-col">
            <p className="footer-col-label">Instagram</p>
            <p>{s.instagram}</p>
          </div>
        )}
      </div>

      <div className="footer-bottom">
        <span>{s.copyright || ''}</span>
        <span>{s.company_id || ''}</span>
      </div>
    </footer>
  )
}

export default Footer
