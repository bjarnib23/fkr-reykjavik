import { useState, useEffect } from 'react'
import './GiftCard.css'

function GiftCard() {
  const [amounts, setAmounts]     = useState([])
  const [selected, setSelected]   = useState(null)
  const [loading, setLoading]     = useState(false)
  const [error, setError]         = useState('')
  const [form, setForm]           = useState({
    buyer_name:     '',
    recipient_name: '',
    email:          '',
    phone:          '',
    notes:          '',
  })

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/giftcard/amounts', { cache: 'no-store' })
      .then(res => res.json())
      .then(data => setAmounts(data))
      .catch(() => setError('Gat ekki sótt gjafabréfsupphæðir.'))
  }, [])

  function update(field, value) {
    setForm(f => ({ ...f, [field]: value }))
  }

  const isValid = selected &&
    form.buyer_name.trim() &&
    form.recipient_name.trim() &&
    form.email.trim() &&
    form.phone.trim()

  async function handleCheckout(e) {
    e.preventDefault()
    if (!isValid) return
    setLoading(true)
    setError('')
    try {
      const res = await fetch('http://fkr-reykjavik.ddev.site/api/fkr/giftcard/checkout', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ sku: selected, ...form }),
      })
      const data = await res.json()
      if (!res.ok) {
        setError(data.error || 'Villa kom upp.')
        setLoading(false)
        return
      }
      window.location.href = data.checkout_url
    } catch {
      setError('Tenging við þjón mistókst.')
      setLoading(false)
    }
  }

  return (
    <main className="giftcard">
      <div className="giftcard-hero">
        <h1>Gjafabréf</h1>
        <p>Gefðu gjöf sem passar fullkomlega</p>
      </div>

      <div className="giftcard-body">
        <div className="giftcard-text">
          <h2>Sérsaumuð klæði sem gjöf</h2>
          <p>
            Gjafabréf frá FKR Reykjavík er fullkomin gjöf fyrir þá sem meta gæði og stíl.
            Viðtakandinn fær tækifæri til að upplifa alla þjónustu okkar — frá efnisvali til fullkláraðs plaggsins.
          </p>
          <p>
            Gjafabréfið er sent beint á netfang gefanda og gildir í 12 mánuði frá kaupdegi.
          </p>
        </div>

        <div className="giftcard-form-side">
          <div className="giftcard-visual">
            <span className="giftcard-brand">FKR</span>
            <span className="giftcard-sub">Reykjavík · Gjafabréf</span>
          </div>

          <form className="giftcard-form" onSubmit={handleCheckout}>
            <p className="form-section-label">Veldu upphæð</p>
            <div className="amount-options">
              {amounts.map(a => (
                <button
                  type="button"
                  key={a.sku}
                  className={`amount-btn ${selected === a.sku ? 'selected' : ''}`}
                  onClick={() => setSelected(a.sku)}
                >
                  {a.label}
                </button>
              ))}
            </div>

            <p className="form-section-label">Upplýsingar</p>

            <div className="form-row">
              <div className="form-field">
                <label>Nafn kaupanda *</label>
                <input
                  type="text"
                  value={form.buyer_name}
                  onChange={e => update('buyer_name', e.target.value)}
                  placeholder="Jón Jónsson"
                  required
                />
              </div>
              <div className="form-field">
                <label>Nafn viðtakanda *</label>
                <input
                  type="text"
                  value={form.recipient_name}
                  onChange={e => update('recipient_name', e.target.value)}
                  placeholder="Anna Sigurðardóttir"
                  required
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-field">
                <label>Netfang *</label>
                <input
                  type="email"
                  value={form.email}
                  onChange={e => update('email', e.target.value)}
                  placeholder="jon@dæmi.is"
                  required
                />
              </div>
              <div className="form-field">
                <label>Sími *</label>
                <input
                  type="tel"
                  value={form.phone}
                  onChange={e => update('phone', e.target.value)}
                  placeholder="555 1234"
                  required
                />
              </div>
            </div>

            <div className="form-field">
              <label>Athugasemd (valkvæmt)</label>
              <textarea
                value={form.notes}
                onChange={e => update('notes', e.target.value)}
                placeholder="Viltu bæta einhverju við?"
                rows={3}
              />
            </div>

            {error && <p className="form-error">{error}</p>}

            <button
              type="submit"
              className="giftcard-submit"
              disabled={!isValid || loading}
            >
              {loading ? 'Hinkraðu...' : 'Halda áfram í greiðslu'}
            </button>
          </form>
        </div>
      </div>
    </main>
  )
}

export default GiftCard
