import './Step3Contact.css'

function Step3Contact({ data, update, next, back, heading, buttons, labels }) {
  const canContinue = data.name && data.email && data.phone

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
              onChange={e => update({ name: e.target.value })}
              placeholder={labels?.placeholderName}
            />
          </div>
          <div className="contact-field">
            <label className="contact-label">{labels?.phone}</label>
            <input
              type="tel"
              autoComplete="tel"
              value={data.phone}
              onChange={e => update({ phone: e.target.value })}
              placeholder={labels?.placeholderPhone}
            />
          </div>
        </div>
        <div className="contact-field">
          <label className="contact-label">{labels?.email}</label>
          <input
            type="email"
            autoComplete="email"
            value={data.email}
            onChange={e => update({ email: e.target.value })}
            placeholder={labels?.placeholderEmail}
          />
        </div>
      </div>
      <div className="step-buttons">
        <button onClick={back}>{buttons?.secondary}</button>
        <button onClick={next} disabled={!canContinue}>{buttons?.primary}</button>
      </div>
    </div>
  )
}

export default Step3Contact
