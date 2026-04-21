import { useState, useEffect } from 'react'
import './GiftCard.css'

const API = 'http://fkr-reykjavik.ddev.site'

function GiftCard() {
  const [amounts, setAmounts]     = useState([])
  const [selected, setSelected]   = useState(null)
  const [form, setForm]           = useState({ name: '', email: '', confirmEmail: '', phone: '', notes: '' })
  const [errors, setErrors]       = useState({})
  const [apiError, setApiError]   = useState('')
  const [loading, setLoading]     = useState(false)

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
    if (!selected)              e.amount       = 'Veldu upphæð'
    if (!form.name.trim())      e.name         = 'Nafn vantar'
    if (!form.phone.trim())     e.phone        = 'Símanúmer vantar'
    if (!form.email.trim())     e.email        = 'Tölvupóstur vantar'
    else if (!/\S+@\S+\.\S+/.test(form.email)) e.email = 'Ógildur tölvupóstur'
    if (form.email !== form.confirmEmail)       e.confirmEmail = 'Tölvupóstar passa ekki'
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

  return (
    <main className="giftcard-wrapper">
      <h1>GJAFABRÉF</h1>

      <div className="amount-options">
        {amounts.map(a => (
          <button
            key={a.sku}
            className={`amount-btn${selected?.sku === a.sku ? ' selected' : ''}`}
            onClick={() => setSelected(a)}
            type="button"
          >
            {a.label}
          </button>
        ))}
      </div>
      {errors.amount && <p className="error-msg">{errors.amount}</p>}

      {selected && (
        <p className="selected-amount">Valin upphæð: {selected.label}</p>
      )}

      <form onSubmit={handleSubmit} noValidate>
        <input
          placeholder="Nafn *"
          autoComplete="name"
          value={form.name}
          onChange={e => update('name', e.target.value)}
          className={errors.name ? 'error' : ''}
        />
        {errors.name && <p className="error-msg">{errors.name}</p>}

        <input
          type="tel"
          placeholder="Sími *"
          autoComplete="tel"
          value={form.phone}
          onChange={e => update('phone', e.target.value)}
          className={errors.phone ? 'error' : ''}
        />
        {errors.phone && <p className="error-msg">{errors.phone}</p>}

        <input
          type="email"
          placeholder="Tölvupóstur *"
          autoComplete="email"
          value={form.email}
          onChange={e => update('email', e.target.value)}
          className={errors.email ? 'error' : ''}
        />
        {errors.email && <p className="error-msg">{errors.email}</p>}

        <input
          type="email"
          placeholder="Staðfesta tölvupóst *"
          autoComplete="email"
          value={form.confirmEmail}
          onChange={e => update('confirmEmail', e.target.value)}
          className={errors.confirmEmail ? 'error' : ''}
        />
        {errors.confirmEmail && <p className="error-msg">{errors.confirmEmail}</p>}

        <textarea
          placeholder="Athugasemd (valfrjálst)"
          value={form.notes}
          onChange={e => update('notes', e.target.value)}
          rows={3}
        />

        <button type="submit" className="submit-btn" disabled={loading}>
          {loading ? 'Hinkraðu...' : 'Greiða'}
        </button>

        {apiError && <p className="api-error">{apiError}</p>}
      </form>
    </main>
  )
}

export default GiftCard
