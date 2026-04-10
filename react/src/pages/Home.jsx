import { Link } from 'react-router-dom'
import './Home.css'

function Home() {
  return (
    <main>
      <section className="hero">
        <h1>FKR</h1>
        <p className="hero-sub">Reykjavík</p>
        <p className="hero-tagline">Sígild klæði</p>
      </section>

      <section className="intro">
        <h2>Sérsaumuð jakkafót</h2>
        <p>Við hönnum og saumum klæði sem passa þér fullkomlega — úr efni sem þú velur, í stíl sem endurspeglar þig.</p>
        <Link to="/boka-tima">Bóka Tíma</Link>
      </section>
    </main>
  )
}

export default Home
