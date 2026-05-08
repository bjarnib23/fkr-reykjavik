import { useState, useEffect } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { FaFacebookF, FaInstagram, FaTiktok } from 'react-icons/fa'
import './Footer.css'

const API = 'http://fkr-reykjavik.ddev.site'

function Footer() {
  const [s, setS]     = useState({})
  const [nav, setNav] = useState([])

  useEffect(() => {
    fetch(`${API}/api/fkr/settings`, { cache: 'no-store' })
      .then(r => r.json())
      .then(setS)

    fetch(`${API}/api/fkr/nav`, { cache: 'no-store' })
      .then(r => r.json())
      .then(setNav)
  }, [])

  return (
    <footer className="footer">
      <div className="footer-main">
        <div className="footer-col footer-col--brand">
          {s.footer_logo
            ? <img src={s.footer_logo} alt="FKR" className="footer-logo" />
            : <p className="footer-brand">FKR</p>
          }
        </div>

        {nav.length > 0 && (
          <div className="footer-col">
            <p className="footer-col-label">{s.footer_nav_label}</p>
            <NavLink end to="/" className="footer-nav-link">Heim</NavLink>
            {nav.map(item => (
              <NavLink key={item.path} to={item.path} className="footer-nav-link">
                {item.label}
              </NavLink>
            ))}
          </div>
        )}


        <div className="footer-col">
          {s.phone && (
            <>
              <p className="footer-col-label">{s.footer_phone_label}</p>
              <p className="footer-contact-value">{s.phone}</p>
            </>
          )}
          {s.email && (
            <>
              <p className="footer-col-label footer-col-label--mt">{s.footer_email_label}</p>
              <p className="footer-contact-value">{s.email}</p>
            </>
          )}
          {s.company_id && (
            <>
              <p className="footer-col-label footer-col-label--mt">{s.footer_company_id_label || 'Kennitala'}</p>
              <p className="footer-contact-value">{s.company_id}</p>
            </>
          )}
          {(s.social_facebook || s.social_instagram || s.social_tiktok) && (
            <div className="footer-social">
              {s.social_facebook  && <a href={s.social_facebook}  target="_blank" rel="noreferrer"><FaFacebookF /></a>}
              {s.social_instagram && <a href={s.social_instagram} target="_blank" rel="noreferrer"><FaInstagram /></a>}
              {s.social_tiktok    && <a href={s.social_tiktok}    target="_blank" rel="noreferrer"><FaTiktok /></a>}
            </div>
          )}
        </div>
      </div>

      <div className="footer-bottom">
        <span>
          {s.copyright_year}
          <a href="/" className="footer-company-link">{s.company_name}</a>
        </span>
      </div>
    </footer>
  )
}

export default Footer
