import { useState, useEffect } from 'react'
import './GiftCard.css'

const API = 'http://fkr-reykjavik.ddev.site'

function GiftCard() {
  const [amounts, setAmounts]   = useState([])
  const [selected, setSelected] = useState(null)
  const [form, setForm]         = useState({ name: '', email: '', confirmEmail: '', phone: '', notes: '' })
  const [errors, setErrors]     = useState({})
  const [apiError, setApiError] = useState('')
  const [loading, setLoading]   = useState(false)

  useEffect(() => {
    fetch(`${API}/api/fkr/giftcard/amounts`)
      .then(r => r.json())
      .then(setAmounts)
      .catch(() => setApiError('Gat ekki sótt gjafabréfsupphæðir. Reyndu að endurhlaða síðuna.'))
  }, [])

  function update(field, value) {
    setForm(f => ({ ...f, [field]: value }))
    setErrors(e => ({ ...e, [field]: '' }))
  }

  function validate() {
    const e = {}
    if (!selected)             e.amount       = 'Veldu upphæð'
    if (!form.name.trim())     e.name         = 'Nafn vantar'
    if (!form.phone.trim())    e.phone        = 'Símanúmer vantar'
    if (!form.email.trim())    e.email        = 'Tölvupóstur vantar'
    else if (!/\S+@\S+\.\S+/.test(form.email)) e.email = 'Ógildur tölvupóstur'
    if (form.email !== form.confirmEmail)      e.confirmEmail = 'Tölvupóstar passa ekki'
    return e
  }

  async function handleSubmit(e) {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }

    setLoading(true)
    setApiError('')

    try {
      const res  = await fetch(`${API}/api/fkr/giftcard/checkout`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          sku:   selected.sku,
          name:  form.name,
          email: form.email,
          phone: form.phone,
          notes: form.notes,
        }),
      })
      const json = await res.json()
      if (!res.ok) { setApiError(json.error || 'Villa kom upp. Reyndu aftur.'); return }
      window.location.href = json.checkout_url
    }
    catch {
      setApiError('Greiðsluþjónusta ekki aðgengileg. Reyndu aftur.')
    }
    finally {
      setLoading(false)
    }
  }

  function formatPrice(label) {
    const match = label.match(/([\d.,]+)/)
    return match ? `kr. ${match[1]}` : label
  }

  function getSubtitle(label) {
    const clean = label.replace(/[\d.,]+\s*(kr\.?|ISK)?/i, '').trim()
    return clean || null
  }

  return (
    <div className="gc-page">
      <div className="gc-hero">
        <div className="gc-hero-left">
          <p className="gc-eyebrow">Gift Cards</p>
          <h1>A quiet<br />and useful<br />present.</h1>
        </div>
        <div className="gc-hero-right">
          <p className="gc-hero-desc">
            Fjórar upphæðir, sendar á tölvupóst með handskrifuðu korti ef óskað er.
            Rennur aldrei út. Gildir fyrir allt sem við gerum — skyrtu, breytingu, heilan jakkaföt.
          </p>
        </div>
      </div>

      <div className="gc-body">
        <div className="gc-left">
          <form onSubmit={handleSubmit} noValidate>
            <div className="gc-section">
              <p className="gc-section-label">Choose an amount</p>
              <div className="gc-amounts">
                {amounts.map(a => (
                  <label
                    key={a.sku}
                    className={`gc-amount-row${selected?.sku === a.sku ? ' selected' : ''}`}
                    onClick={() => { setSelected(a); setErrors(e => ({ ...e, amount: '' })) }}
                  >
                    <div className="gc-amount-info">
                      <span className="gc-amount-price">{formatPrice(a.label)}</span>
                      {getSubtitle(a.label) && (
                        <span className="gc-amount-sub">{getSubtitle(a.label)}</span>
                      )}
                    </div>
                    <div className={`gc-radio${selected?.sku === a.sku ? ' checked' : ''}`} />
                  </label>
                ))}
              </div>
              {errors.amount && <p className="gc-error">{errors.amount}</p>}
            </div>

            <div className="gc-section">
              <p className="gc-section-label">Your details</p>

              <div className="gc-field-row">
                <div className="gc-field">
                  <label className="gc-label">Nafn *</label>
                  <input
                    autoComplete="name"
                    value={form.name}
                    onChange={e => update('name', e.target.value)}
                    className={errors.name ? 'has-error' : ''}
                  />
                  {errors.name && <span className="gc-error">{errors.name}</span>}
                </div>
                <div className="gc-field">
                  <label className="gc-label">Sími *</label>
                  <input
                    type="tel"
                    autoComplete="tel"
                    value={form.phone}
                    onChange={e => update('phone', e.target.value)}
                    className={errors.phone ? 'has-error' : ''}
                  />
                  {errors.phone && <span className="gc-error">{errors.phone}</span>}
                </div>
              </div>

              <div className="gc-field">
                <label className="gc-label">Tölvupóstur *</label>
                <input
                  type="email"
                  autoComplete="email"
                  value={form.email}
                  onChange={e => update('email', e.target.value)}
                  className={errors.email ? 'has-error' : ''}
                />
                {errors.email && <span className="gc-error">{errors.email}</span>}
              </div>

              <div className="gc-field">
                <label className="gc-label">Staðfesta tölvupóst *</label>
                <input
                  type="email"
                  autoComplete="email"
                  value={form.confirmEmail}
                  onChange={e => update('confirmEmail', e.target.value)}
                  className={errors.confirmEmail ? 'has-error' : ''}
                />
                {errors.confirmEmail && <span className="gc-error">{errors.confirmEmail}</span>}
              </div>

              <div className="gc-field">
                <label className="gc-label">Athugasemd</label>
                <textarea
                  value={form.notes}
                  onChange={e => update('notes', e.target.value)}
                  rows={3}
                />
              </div>
            </div>

            <button type="submit" className="gc-submit" disabled={loading}>
              {loading ? 'Hinkraðu...' : 'Greiða →'}
            </button>

            {apiError && <p className="gc-api-error">{apiError}</p>}
          </form>
        </div>

        <div className="gc-right">
          <p className="gc-section-label">Preview</p>
          <div className="gc-card-preview">
            <div className="gc-card-top">
              <span className="gc-card-brand">FKR</span>
              <span className="gc-card-tag">REYKJAVÍK<br />GIFT CARD</span>
            </div>
            <div className="gc-card-amount">
              {selected ? formatPrice(selected.label) : 'kr. —'}
            </div>
            <div className="gc-card-bottom">
              <span className="gc-card-code">CODE · —</span>
              <span className="gc-card-expiry">NO EXPIRY</span>
            </div>
          </div>
          <p className="gc-preview-note">
            Gjafabréfið er sent á tölvupóst. Við getum einnig sent handskrifað kort — spurðu við greiðslu.
          </p>
        </div>
      </div>
    </div>
  )
}

export default GiftCard
