import './Step1Service.css'
import { useNavigate } from 'react-router-dom'

function Step1Service({ data, update, next }) {
    const navigate = useNavigate()

    const services = [
      { id: 'jakkafot', title: 'Jakkaföt', desc: 'Bókun fyrir máltöku og ráðgjöf vegna sérsaums.' },
      { id: 'skyrta', title: 'Skyrta / stök flík', desc: 'Fyrir skyrtur, jakka eða aðrar sérsniðnar flíkur.' },
      { id: 'annad', title: 'Annað', desc: 'Ef þú ert með sértæka beiðni eða vilt útskýra nánar.' },
    ]

console.log('Selected service:', data.service)

    return (
      <div>
        <h2>Hvað viltu bóka?</h2>
        <p>Veldu það sem passar best svo við getum undirbúið tímann betur.</p>
        <div className="service-options">
          {services.map(s => (
            <div
              key={s.id}
              className={`service-card ${data.service === s.id ? 'selected' : ''}`}
              onClick={() => update({ service: s.id })}
            >
              <strong>{s.title}</strong>
              <p>{s.desc}</p>
            </div>
          ))}
        </div>
        <div className="step-buttons">
            <button onClick={() => navigate(-1)}>Til baka</button>
            <button onClick={next} disabled={!data.service}>Næsta skref</button>
        </div>
    </div>
    )
  }

  export default Step1Service