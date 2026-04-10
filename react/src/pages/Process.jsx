import { Link } from 'react-router-dom'
import './Process.css'

const steps = [
  {
    num: '01',
    title: 'Fyrsta mæting',
    desc: 'Við hittumst og ræðum þarfir þínar, stíl og tilgang plaggana. Við tökum mál og ræðum efnisval.',
  },
  {
    num: '02',
    title: 'Hönnun og efnisval',
    desc: 'Við leggjum til efni úr safni okkar eða vinnum með efni sem þú velur. Hönnunin er staðfest.',
  },
  {
    num: '03',
    title: 'Prófun',
    desc: 'Þegar plaggið er tilbúið á prófun kemur þú aftur í búðina. Við gerum allar nauðsynlegar leiðréttingar.',
  },
  {
    num: '04',
    title: 'Afhending',
    desc: 'Lokaplaggið er afhent fullklárt. Við tryggjum að þú sért fullnægður áður en þú ferð.',
  },
]

function Process() {
  return (
    <main className="process">
      <div className="process-hero">
        <h1>Ferlið</h1>
        <p>Frá fyrstu mætingu til fullkláraðs plaggsins</p>
      </div>

      <div className="process-steps">
        {steps.map((s) => (
          <div className="process-step" key={s.num}>
            <span className="step-num">{s.num}</span>
            <div className="step-body">
              <h2>{s.title}</h2>
              <p>{s.desc}</p>
            </div>
          </div>
        ))}
      </div>

      <div className="process-cta">
        <h3>Tilbúinn að hefja ferðalagið?</h3>
        <Link to="/boka-tima">Bóka tíma</Link>
      </div>
    </main>
  )
}

export default Process
