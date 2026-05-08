import { useState, useEffect } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import './Navbar.css'

function Navbar() {
  const [links, setLinks]   = useState([])
  const [logo, setLogo]     = useState('')
  const [menuOpen, setMenuOpen] = useState(false)
  const location = useLocation()

  useEffect(() => { setMenuOpen(false) }, [location])

  useEffect(() => {
    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/nav`, { cache: 'no-store' })
      .then(r => r.json())
      .then(setLinks)

    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/settings`, { cache: 'no-store' })
      .then(r => r.json())
      .then(data => { if (data.logo) setLogo(data.logo) })
  }, [])

  const navLinks = links.filter(l => !l.is_cta)
  const cta      = links.find(l => l.is_cta)

  return (
    <nav className="navbar">
      <NavLink to="/" className="navbar-brand">
        {logo
          ? <img src={logo} alt="Logo" className="navbar-logo" />
          : <span className="navbar-brand-name">FKR</span>
        }
      </NavLink>

      <ul className="navbar-links">
        {navLinks.map(link => (
          <li key={link.path}>
            <NavLink to={link.path}>{link.label}</NavLink>
          </li>
        ))}
      </ul>

      {cta && (
        <NavLink to={cta.path} className="navbar-cta navbar-cta-desktop">
          {cta.label} →
        </NavLink>
      )}

      <button
        className={`navbar-hamburger${menuOpen ? ' open' : ''}`}
        onClick={() => setMenuOpen(o => !o)}
        aria-label="Valmynd"
      >
        <span /><span /><span />
      </button>

      {menuOpen && (
        <div className="navbar-mobile-menu">
          {navLinks.map(link => (
            <NavLink key={link.path} to={link.path} className="navbar-mobile-link">
              {link.label}
            </NavLink>
          ))}
          {cta && (
            <NavLink to={cta.path} className="navbar-mobile-link navbar-mobile-cta">
              {cta.label} →
            </NavLink>
          )}
        </div>
      )}
    </nav>
  )
}

export default Navbar
