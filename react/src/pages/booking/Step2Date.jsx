import { useState, useEffect } from 'react'
import Calendar from './Calendar'
import './Step2Date.css'

function Step2Date({ data, update, next, back }) {
  const [slots, setSlots] = useState([])

  useEffect(() => {
    if (!data.date) return
    fetch(`http://fkr-reykjavik.ddev.site/api/fkr/availability?date=${data.date}`, { cache: 'no-store' })
      .then(res => res.json())
      .then(slots => setSlots(slots.filter(s => s.status === 'available')))
  }, [data.date])

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
