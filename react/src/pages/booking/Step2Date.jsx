import { useState, useEffect } from 'react'
import Calendar from './Calendar'
import './Step2Date.css'

function Step2Date({ data, update, next, back, releaseReady }) {
  const [slots, setSlots] = useState([])

  useEffect(() => {
    if (!data.date || !releaseReady) return
    fetch(`http://fkr-reykjavik.ddev.site/api/fkr/availability?date=${data.date}`, { cache: 'no-store' })
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
      <h2>Pick a date & time.</h2>
      <Calendar selected={data.date} onSelect={date => { update({ date, time: '' }); setSlots([]) }} />

      {data.date && (
        <div>
          <p className="time-options-label">Available times — {data.date}</p>
          <div className="time-options">
            {slots.length === 0
              ? <p className="time-empty">No available times on this day.</p>
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
        <button onClick={back}>Back</button>
        <button onClick={next} disabled={!data.date || !data.time}>Continue</button>
      </div>
    </div>
  )
}

export default Step2Date
