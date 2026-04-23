import { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import './Navbar.css'

function Navbar() {
  const [links, setLinks] = useState([])
  const [logo, setLogo]   = useState('')

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/nav', { cache: 'no-store' })
      .then(r => r.json())
      .then(setLinks)

    fetch('http://fkr-reykjavik.ddev.site/api/fkr/settings', { cache: 'no-store' })
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
        <NavLink to={cta.path} className="navbar-cta">
          {cta.label} →
        </NavLink>
      )}
    </nav>
  )
}

export default Navbar
