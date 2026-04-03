import { NavLink } from 'react-router-dom'
import './Navbar.css'

function Navbar() {
    return (
        <nav className="navbar">
            <NavLink to="/" className="navbar-logo">
            FKR 🦊 Rvk.
            </NavLink>
            <ul className="navbar-links">
                <li><NavLink to="/boka-tima">Bóka Tíma</NavLink></li>
                <li><NavLink to="/ferlid">Ferlið</NavLink></li>
                <li><NavLink to="/algengar-spurningar">Algenar spurningar</NavLink></li>
                <li><NavLink to="/gjafabref">Gjafabréf</NavLink></li>
                <li><NavLink to="/verdskra">Verðskrá</NavLink></li>
            </ul>
        </nav>
    )
}

export default Navbar

