import { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import './Navbar.css'

const SLUG_TO_PATH = {
    booking:   '/boka-tima',
    process:   '/ferlid',
    faq:       '/algengar-spurningar',
    giftcard:  '/gjafabref',
    pricelist: '/verdskra',
}

function Navbar() {
    const [links, setLinks] = useState([])
    const [logo, setLogo] = useState('')

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                const navLinks = Object.values(data)
                    .filter(page => page.slug && SLUG_TO_PATH[page.slug])
                    .map(page => ({
                        label: page.title,
                        path: SLUG_TO_PATH[page.slug],
                    }))
                setLinks(navLinks)
            })

        fetch('http://fkr-reykjavik.ddev.site/api/fkr/settings', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => setLogo(data.logo || ''))
    }, [])

    return (
        <nav className="navbar">
            <NavLink to="/" className="navbar-logo">
                {logo ? <img src={logo} alt="FKR Logo" className="navbar-logo-img" /> : 'FKR'}
            </NavLink>
            <ul className="navbar-links">
                {links.map(link => (
                    <li key={link.path}>
                        <NavLink to={link.path}>{link.label}</NavLink>
                    </li>
                ))}
            </ul>
        </nav>
    )
}

export default Navbar
