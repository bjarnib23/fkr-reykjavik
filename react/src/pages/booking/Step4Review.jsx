import { useState } from 'react'
import './Step4Review.css'

function Step4Review({ data, update, back, releaseHold }) {
  const [submitted, setSubmitted] = useState(false)

  function formatDate(dateStr) {
    if (!dateStr) return ''
    const [y, m, d] = dateStr.split('-')
    return `${d}.${m}.${y}`
  }

  async function handleSubmit() {
    const payload = { ...data, date: `${data.date}T${data.time}:00` }
    const res = await fetch('http://fkr-reykjavik.ddev.site/api/fkr/booking', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    })
    if (res.ok) {
      releaseHold?.()
      setSubmitted(true)
    } else {
      alert('Eitthvað fór úrskeiðis, reyndu aftur.')
    }
  }

  if (submitted) {
    return (
      <div className="review-done">
        <h2>You're booked.</h2>
        <p>Confirmation sent to {data.email}. We'll see you then.</p>
      </div>
    )
  }

  return (
    <div>
      <h2>Confirm your booking.</h2>

      <div className="review-rows">
        <div className="review-row"><span>Service</span><strong>{data.service}</strong></div>
        <div className="review-row"><span>Date</span><strong>{formatDate(data.date)}</strong></div>
        <div className="review-row"><span>Time</span><strong>{data.time}</strong></div>
        <div className="review-row"><span>Name</span><strong>{data.name}</strong></div>
        <div className="review-row"><span>Email</span><strong>{data.email}</strong></div>
        <div className="review-row"><span>Phone</span><strong>{data.phone}</strong></div>
      </div>

      <div className="review-notes-field">
        <label className="review-notes-label">Notes (optional)</label>
        <textarea
          placeholder="Anything we should know beforehand..."
          value={data.notes}
          onChange={e => update({ notes: e.target.value })}
          rows={3}
        />
      </div>

      <div className="step-buttons">
        <button onClick={back}>Back</button>
        <button onClick={handleSubmit}>Confirm booking</button>
      </div>
    </div>
  )
}

export default Step4Review
