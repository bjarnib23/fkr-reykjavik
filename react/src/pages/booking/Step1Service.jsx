import { useState, useEffect } from 'react'
import './Step1Service.css'

const DRUPAL = import.meta.env.VITE_DRUPAL_URL

function Step1Service({ data, update, next, back, buttons }) {
  const [services, setServices] = useState([])
  const [title, setTitle]       = useState('')

  useEffect(() => {
    fetch(`${DRUPAL}/api/fkr/services`, { cache: 'no-store' })
      .then(res => res.json())
      .then(setServices)

    fetch(`${DRUPAL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'booking_step1')
        if (p) setTitle(p.subtitle || p.title || '')
      })
  }, [])

  return (
    <div>
      <h2>{title}</h2>
      <div className="service-grid">
        {services.map(s => (
          <button
            key={s.id}
            className={`service-card${data.service === s.title ? ' selected' : ''}`}
            onClick={() => update({ service: s.title })}
          >
            <span className="service-card-name">{s.title}</span>
            {s.duration && <span className="service-card-meta">{s.duration}</span>}
            {s.price && <span className="service-card-price">{s.price}</span>}
          </button>
        ))}
      </div>
      <div className="step-buttons">
        <button onClick={back}>{buttons?.secondary}</button>
        <button onClick={next} disabled={!data.service}>{buttons?.primary}</button>
      </div>
    </div>
  )
}

export default Step1Service
