import { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import './Navbar.css'

const SLUG_TO_PATH = {
  studio:    '/studio',
  process:   '/ferlid',
  booking:   '/boka-tima',
  giftcard:  '/gjafabref',
  pricelist: '/verdskra',
  faq:       '/algengar-spurningar',
}

const SLUG_ORDER = ['studio', 'process', 'booking', 'giftcard', 'pricelist', 'faq']

function Navbar() {
  const [links, setLinks]   = useState([])
  const [brand, setBrand]   = useState({ name: 'FKR', sub: 'Reykjavík · Est. 2011' })

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const bySlug = {}
        Object.values(pages).forEach(p => { if (p.slug) bySlug[p.slug] = p })

        const nav = SLUG_ORDER
          .filter(slug => bySlug[slug] && SLUG_TO_PATH[slug])
          .map(slug => ({ label: bySlug[slug].title, path: SLUG_TO_PATH[slug] }))
        setLinks(nav)
      })

    fetch('http://fkr-reykjavik.ddev.site/api/fkr/settings', { cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        if (data.site_name) setBrand(b => ({ ...b, name: data.site_name }))
        if (data.site_sub)  setBrand(b => ({ ...b, sub:  data.site_sub  }))
      })
  }, [])

  const bookLink = links.find(l => l.path === '/boka-tima')

  return (
    <nav className="navbar">
      <NavLink to="/" className="navbar-brand">
        <span className="navbar-brand-name">{brand.name}</span>
        <span className="navbar-brand-sub">{brand.sub}</span>
      </NavLink>

      <ul className="navbar-links">
        {links.map(link => (
          <li key={link.path}>
            <NavLink to={link.path}>{link.label}</NavLink>
          </li>
        ))}
      </ul>

      {bookLink && (
        <NavLink to="/boka-tima" className="navbar-cta">
          {bookLink.label} →
        </NavLink>
      )}
    </nav>
  )
}

export default Navbar
