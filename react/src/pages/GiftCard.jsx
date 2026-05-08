import { useState, useEffect } from 'react'
import './GiftCard.css'

const API = import.meta.env.VITE_DRUPAL_URL

function GiftCard() {
  const [amounts, setAmounts]   = useState([])
  const [selected, setSelected] = useState(null)
  const [form, setForm]         = useState({ name: '', email: '', confirmEmail: '', phone: '', notes: '' })
  const [errors, setErrors]     = useState({})
  const [apiError, setApiError] = useState('')
  const [loading, setLoading]   = useState(false)
  const [labels, setLabels]     = useState({})

  useEffect(() => {
    fetch(`${API}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const p = Object.values(pages).find(p => p.slug === 'giftcard')
        if (p) setLabels(p)
      })

    fetch(`${API}/api/fkr/giftcard/amounts`)
      .then(r => r.json())
      .then(setAmounts)
      .catch(() => setApiError(labels.err_load_amounts || ''))
  }, [])

  function update(field, value) {
    setForm(f => ({ ...f, [field]: value }))
    setErrors(e => ({ ...e, [field]: '' }))
  }

  function validate() {
    const e = {}
    if (!selected)             e.amount       = labels.err_select_amount  || ''
    if (!form.name.trim())     e.name         = labels.err_name_required  || ''
    if (!form.phone.trim())    e.phone        = labels.err_phone_required || ''
    if (!form.email.trim())    e.email        = labels.err_email_required || ''
    else if (!/\S+@\S+\.\S+/.test(form.email)) e.email = labels.err_invalid_email || ''
    if (form.email !== form.confirmEmail)      e.confirmEmail = labels.err_email_mismatch || ''
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
      if (!res.ok) { setApiError(json.error || labels.err_submit || ''); return }
      window.location.href = json.checkout_url
    }
    catch {
      setApiError(labels.err_payment || '')
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
          <p className="gc-eyebrow">{labels.subtitle}</p>
          <h1>{labels.title}</h1>
        </div>
        <div className="gc-hero-right">
          <p className="gc-hero-desc" dangerouslySetInnerHTML={{ __html: labels.body_text }} />
        </div>
      </div>

      <div className="gc-body">
        <div className="gc-left">
          <form onSubmit={handleSubmit} noValidate>
            <div className="gc-section">
              <p className="gc-section-label">{labels.label_choose_amount}</p>
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
              <p className="gc-section-label">{labels.label_your_details}</p>

              <div className="gc-field-row">
                <div className="gc-field">
                  <label className="gc-label">{labels.label_name}</label>
                  <input
                    autoComplete="name"
                    value={form.name}
                    onChange={e => update('name', e.target.value)}
                    className={errors.name ? 'has-error' : ''}
                  />
                  {errors.name && <span className="gc-error">{errors.name}</span>}
                </div>
                <div className="gc-field">
                  <label className="gc-label">{labels.label_phone}</label>
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
                <label className="gc-label">{labels.label_email}</label>
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
                <label className="gc-label">{labels.label_confirm_email}</label>
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
                <label className="gc-label">{labels.label_notes}</label>
                <textarea
                  value={form.notes}
                  onChange={e => update('notes', e.target.value)}
                  onInput={e => { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 'px' }}
                  rows={1}
                />
              </div>
            </div>

            <button type="submit" className="gc-submit" disabled={loading}>
              {loading ? labels.label_loading : labels.cta_text}
            </button>

            {apiError && <p className="gc-api-error">{apiError}</p>}
          </form>
        </div>

        <div className="gc-right">
          <p className="gc-section-label">{labels.label_preview}</p>
          <div className="gc-card-preview">
            <div className="gc-card-top">
              <span className="gc-card-brand">FKR</span>
              <span className="gc-card-tag">{labels.card_title}</span>
            </div>
            <div className="gc-card-amount-row">
              <span className="gc-card-amount">
                {selected ? formatPrice(selected.label) : 'kr. —'}
              </span>
              <img src="/fox.png" alt="" className="gc-card-fox" />
            </div>
            <div className="gc-card-bottom">
              <span className="gc-card-code">{labels.card_code_label}</span>
              <span className="gc-card-expiry">{labels.card_no_expiry}</span>
            </div>
          </div>
          <p className="gc-preview-note">{labels.card_note}</p>
        </div>
      </div>
    </div>
  )
}

export default GiftCard
