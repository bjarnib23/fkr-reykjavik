import { useState } from 'react'
import { Link } from 'react-router-dom'
import './Step4Review.css'

const DRUPAL = import.meta.env.VITE_DRUPAL_URL

function Step4Review({ data, update, back, releaseHold, heading, buttons, labels, submitError, onSubmitted }) {
  const [submitted, setSubmitted] = useState(false)

  function formatDate(dateStr) {
    if (!dateStr) return ''
    const [y, m, d] = dateStr.split('-')
    return `${d}.${m}.${y}`
  }

  async function handleSubmit() {
    const payload = { ...data, date: `${data.date}T${data.time}:00` }
    const res = await fetch(`${DRUPAL}/api/fkr/booking`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    })
    if (res.ok) {
      releaseHold?.()
      setSubmitted(true)
      onSubmitted?.()
    } else {
      alert(submitError)
    }
  }

  if (submitted) {
    const successBody = labels?.successBody
      ? labels.successBody.replace('{email}', data.email)
      : ''
    return (
      <div className="review-done">
        <h2>{labels?.successHeading}</h2>
        <p>{successBody}</p>
        {labels?.successButton && (
          <Link to="/" className="review-done-btn">{labels.successButton}</Link>
        )}
      </div>
    )
  }

  return (
    <div>
      <h2>{heading}</h2>

      <div className="review-rows">
        <div className="review-row"><span>{labels?.service}</span><strong>{data.service}</strong></div>
        <div className="review-row"><span>{labels?.date}</span><strong>{formatDate(data.date)}</strong></div>
        <div className="review-row"><span>{labels?.time}</span><strong>{data.time}</strong></div>
        <div className="review-row"><span>{labels?.name}</span><strong>{data.name}</strong></div>
        <div className="review-row"><span>{labels?.email}</span><strong>{data.email}</strong></div>
        <div className="review-row"><span>{labels?.phone}</span><strong>{data.phone}</strong></div>
      </div>

      <div className="review-notes-field">
        <label className="review-notes-label">{labels?.notes}</label>
        <textarea
          placeholder={labels?.placeholderNotes}
          value={data.notes}
          onChange={e => update({ notes: e.target.value })}
          rows={3}
        />
      </div>

      <div className="step-buttons">
        <button onClick={back}>{buttons?.secondary}</button>
        <button onClick={handleSubmit}>{buttons?.primary}</button>
      </div>
    </div>
  )
}

export default Step4Review
