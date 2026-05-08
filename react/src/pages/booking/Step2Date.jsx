import { useState, useEffect } from 'react'
import Calendar from './Calendar'
import './Step2Date.css'

function Step2Date({ data, update, next, back, releaseReady, heading, buttons, labels }) {
  const [slots, setSlots] = useState([])

  useEffect(() => {
    if (!data.date || !releaseReady) return
    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/availability?date=${data.date}`, { cache: 'no-store' })
      .then(res => res.json())
      .then(slots => {
        const now = new Date()
        const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
        setSlots(slots.filter(s => {
          if (s.status !== 'available') return false
          if (data.date === todayStr) {
            const [h, m] = s.time.split(':').map(Number)
            const slotTime = new Date()
            slotTime.setHours(h, m, 0, 0)
            return slotTime > now
          }
          return true
        }))
      })
  }, [data.date, releaseReady])

  return (
    <div>
      <h2>{heading}</h2>
      <Calendar selected={data.date} onSelect={date => { update({ date, time: '' }); setSlots([]) }} />

      {data.date && (
        <div>
          <p className="time-options-label">{labels?.slots} — {data.date}</p>
          <div className="time-options">
            {slots.length === 0
              ? <p className="time-empty">{labels?.noSlots}</p>
              : slots.map(s => (
                  <button
                    key={s.time}
                    className={`time-btn${data.time === s.time ? ' selected' : ''}`}
                    onClick={() => update({ time: s.time })}
                  >
                    {s.time}
                  </button>
                ))
            }
          </div>
        </div>
      )}

      <div className="step-buttons">
        <button onClick={back}>{buttons?.secondary}</button>
        <button onClick={next} disabled={!data.date || !data.time}>{buttons?.primary}</button>
      </div>
    </div>
  )
}

export default Step2Date
