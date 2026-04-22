import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import './Step1Service.css'

function Step1Service({ data, update, next }) {
  const navigate  = useNavigate()
  const [services, setServices] = useState([])
  const [title, setTitle]       = useState('')

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/services', { cache: 'no-store' })
      .then(res => res.json())
      .then(setServices)

    fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'booking_step1')
        if (p) setTitle(p.subtitle || p.title || '')
      })
  }, [])

  return (
    <div>
      <h2>{title}</h2>
      <div className="service-list">
        {services.map(s => (
          <label
            key={s.id}
            className={`service-row${data.service === s.title ? ' selected' : ''}`}
            onClick={() => update({ service: s.title })}
          >
            <div className="service-row-info">
              <span className="service-row-name">{s.title}</span>
              {s.duration && <span className="service-row-meta">{s.duration}</span>}
            </div>
            {s.price && <span className="service-row-price">{s.price}</span>}
            <div className={`service-radio${data.service === s.title ? ' checked' : ''}`} />
          </label>
        ))}
      </div>
      <div className="step-buttons">
        <button onClick={() => navigate(-1)}>Back</button>
        <button onClick={next} disabled={!data.service}>Continue</button>
      </div>
    </div>
  )
}

export default Step1Service
