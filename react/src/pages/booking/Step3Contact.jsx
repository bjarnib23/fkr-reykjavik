import { useState } from 'react'
import './Step3Contact.css'

function Step3Contact({ data, update, next, back, heading, buttons, labels }) {
  const [errors, setErrors] = useState({})

  function validate() {
    const e = {}
    if (!data.name.trim())  e.name  = labels?.errName  || ''
    if (!data.phone.trim()) e.phone = labels?.errPhone || ''
    if (!data.email.trim()) e.email = labels?.errEmail || ''
    else if (!/\S+@\S+\.\S+/.test(data.email)) e.email = labels?.errInvalidEmail || ''
    return e
  }

  function handleNameChange(e) {
    update({ name: e.target.value.replace(/[0-9]/g, '') })
    setErrors(p => ({ ...p, name: '' }))
  }

  function handlePhoneChange(e) {
    update({ phone: e.target.value.replace(/[^0-9+\s\-]/g, '') })
    setErrors(p => ({ ...p, phone: '' }))
  }

  function handleNext() {
    const e = validate()
    if (Object.keys(e).length) { setErrors(e); return }
    next()
  }

  return (
    <div>
      <h2>{heading}</h2>
      <div className="contact-fields">
        <div className="contact-field-row">
          <div className="contact-field">
            <label className="contact-label">{labels?.name}</label>
            <input
              autoComplete="name"
              value={data.name}
              onChange={handleNameChange}
              placeholder={labels?.placeholderName}
              className={errors.name ? 'has-error' : ''}
            />
            {errors.name && <span className="contact-error">{errors.name}</span>}
          </div>
          <div className="contact-field">
            <label className="contact-label">{labels?.phone}</label>
            <input
              type="tel"
              autoComplete="tel"
              value={data.phone}
              onChange={handlePhoneChange}
              placeholder={labels?.placeholderPhone}
              className={errors.phone ? 'has-error' : ''}
            />
            {errors.phone && <span className="contact-error">{errors.phone}</span>}
          </div>
        </div>
        <div className="contact-field">
          <label className="contact-label">{labels?.email}</label>
          <input
            type="email"
            autoComplete="email"
            value={data.email}
            onChange={e => { update({ email: e.target.value }); setErrors(p => ({ ...p, email: '' })) }}
            placeholder={labels?.placeholderEmail}
            className={errors.email ? 'has-error' : ''}
          />
          {errors.email && <span className="contact-error">{errors.email}</span>}
        </div>
      </div>
      <div className="step-buttons">
        <button onClick={back}>{buttons?.secondary}</button>
        <button onClick={handleNext}>{buttons?.primary}</button>
      </div>
    </div>
  )
}

export default Step3Contact
