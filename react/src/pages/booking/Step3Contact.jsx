import './Step3Contact.css'

function Step3Contact({ data, update, next, back }) {
  const canContinue = data.name && data.email && data.phone

  return (
    <div>
      <h2>Your details.</h2>
      <div className="contact-fields">
        <div className="contact-field-row">
          <div className="contact-field">
            <label className="contact-label">Name *</label>
            <input
              autoComplete="name"
              value={data.name}
              onChange={e => update({ name: e.target.value })}
              placeholder="Jón Sigurðsson"
            />
          </div>
          <div className="contact-field">
            <label className="contact-label">Phone *</label>
            <input
              type="tel"
              autoComplete="tel"
              value={data.phone}
              onChange={e => update({ phone: e.target.value })}
              placeholder="+354 000 0000"
            />
          </div>
        </div>
        <div className="contact-field">
          <label className="contact-label">Email *</label>
          <input
            type="email"
            autoComplete="email"
            value={data.email}
            onChange={e => update({ email: e.target.value })}
            placeholder="jon@example.is"
          />
        </div>
      </div>
      <div className="step-buttons">
        <button onClick={back}>Back</button>
        <button onClick={next} disabled={!canContinue}>Continue</button>
      </div>
    </div>
  )
}

export default Step3Contact
