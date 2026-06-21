import { useState, useEffect, useRef } from 'react'
import { allCountries } from 'country-telephone-data'
import { useLoading } from '../../context/LoadingContext'
import './Booking.css'

const DRUPAL = import.meta.env.VITE_DRUPAL_URL

function isoToFlag(iso2) {
  return iso2.toUpperCase().replace(/./g, c =>
    String.fromCodePoint(c.charCodeAt(0) + 127397)
  )
}

const COUNTRIES = allCountries.map(c => ({
  iso2: c.iso2,
  name: c.name.replace(/\s*\(.*?\)\s*/g, '').trim(),
  dial: '+' + c.dialCode,
  flag: isoToFlag(c.iso2),
  priority: c.priority || 0,
})).filter((c, i, arr) =>
  arr.findIndex(x => x.iso2 === c.iso2) === i
)

const IS = COUNTRIES.find(c => c.iso2 === 'is') || COUNTRIES[0]

function PhoneField({ value, country, onChange, onCountryChange, hasError }) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const ref = useRef(null)

  useEffect(() => {
    function handler(e) { if (ref.current && !ref.current.contains(e.target)) { setOpen(false); setSearch('') } }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const filtered = search.trim()
    ? COUNTRIES.filter(c => c.name.toLowerCase().includes(search.toLowerCase()) || c.dial.includes(search))
    : COUNTRIES

  return (
    <div className="bk-phone-row" ref={ref}>
      <button type="button" className={`bk-phone-prefix${open ? ' open' : ''}`} onClick={() => setOpen(o => !o)}>
        <span className="bk-phone-flag">{country.flag}</span>
        <span className="bk-phone-arrow">▾</span>
      </button>
      {open && (
        <div className="bk-phone-dropdown">
          <div className="bk-phone-search">
            <input
              autoFocus
              type="text"
              placeholder="Leita að landi..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>
          {filtered.map(c => (
            <button key={c.iso2} type="button"
              className={`bk-phone-option${c.iso2 === country.iso2 ? ' selected' : ''}`}
              onClick={() => { onCountryChange(c); setOpen(false); setSearch('') }}>
              <span>{c.flag}</span>
              <span>{c.name}</span>
              <span className="bk-phone-option-dial">{c.dial}</span>
            </button>
          ))}
        </div>
      )}
      <div className={`bk-phone-input-wrap${hasError ? ' error' : ''}`}>
        <span className="bk-phone-dial-label">Sími: *</span>
        <div className="bk-phone-dial-row">
          <span className="bk-phone-dial">{country.dial}</span>
          <input type="tel" value={value} onChange={e => onChange(e.target.value)} placeholder="" />
        </div>
      </div>
    </div>
  )
}

function Booking() {
  const { setLoading } = useLoading()
  useEffect(() => { setLoading(false) }, [])

  const [form, setForm] = useState({
    name: '', email: '', phone: '', country: IS, service: '', dd: '', mm: '', yyyy: '', notes: ''
  })
  const [errors, setErrors] = useState({})
  const [submitted, setSubmitted] = useState(false)
  const [sending, setSending] = useState(false)
  const [serverError, setServerError] = useState('')

  function update(field, value) {
    setForm(f => ({ ...f, [field]: value }))
    setErrors(e => ({ ...e, [field]: '' }))
  }

  function validate() {
    const e = {}
    if (!form.name.trim())  e.name  = 'Nafn er nauðsynlegt'
    if (!form.email.trim()) e.email = 'Tölvupóstur er nauðsynlegur'
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = 'Ógildur tölvupóstur'
    if (!form.phone.trim()) e.phone = 'Símanúmer er nauðsynlegt'
    return e
  }

  async function handleSubmit(e) {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }
    setSending(true)
    setServerError('')
    try {
      const date = (form.dd && form.mm && form.yyyy)
        ? `${form.yyyy}-${form.mm.padStart(2,'0')}-${form.dd.padStart(2,'0')}`
        : ''
      const res = await fetch(`${DRUPAL}/api/fkr/booking/simple`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: form.name,
          email: form.email,
          phone: `${form.country.dial} ${form.phone}`,
          service: form.service,
          date,
          notes: form.notes,
        }),
      })
      if (!res.ok) throw new Error()
      setSubmitted(true)
    } catch {
      setServerError('Eitthvað fór úrskeiðis. Vinsamlegast reyndu aftur.')
    } finally {
      setSending(false)
    }
  }

  if (submitted) {
    return (
      <main className="bk">
        <div className="bk-box bk-thanks">
          <h1>Takk!</h1>
          <p>Við höfum samband til að staðfesta tímann.</p>
        </div>
      </main>
    )
  }

  return (
    <main className="bk">
      <div className="bk-box">
        <h1 className="bk-title">BÓKA TÍMA</h1>
        <p className="bk-subtitle">Fylltu út eftirfarandi og við höfum samband til að staðfesta tímann.</p>

        <form className="bk-form" onSubmit={handleSubmit} noValidate>

          <div className="bk-field">
            <input
              type="text"
              placeholder="Nafn: *"
              value={form.name}
              onChange={e => update('name', e.target.value)}
              className={errors.name ? 'error' : ''}
            />
            {errors.name && <span className="bk-error">{errors.name}</span>}
          </div>

          <div className="bk-field">
            <input
              type="email"
              placeholder="Tölvupóstur: *"
              value={form.email}
              onChange={e => update('email', e.target.value)}
              className={errors.email ? 'error' : ''}
            />
            {errors.email && <span className="bk-error">{errors.email}</span>}
          </div>

          <div className="bk-field">
            <PhoneField
              value={form.phone}
              country={form.country}
              onChange={val => update('phone', val)}
              onCountryChange={c => update('country', c)}
              hasError={!!errors.phone}
            />
            {errors.phone && <span className="bk-error">{errors.phone}</span>}
          </div>

          <div className="bk-field">
            <input
              type="text"
              placeholder="Hvað viltu panta?"
              value={form.service}
              onChange={e => update('service', e.target.value)}
            />
          </div>

          <div className="bk-field">
            <label className="bk-date-label">Hvenær þarftu að nota fötin?</label>
            <div className="bk-date-row">
              <input
                type="text"
                placeholder="DD"
                maxLength={2}
                value={form.dd}
                onChange={e => update('dd', e.target.value.replace(/\D/g,''))}
              />
              <input
                type="text"
                placeholder="MM"
                maxLength={2}
                value={form.mm}
                onChange={e => update('mm', e.target.value.replace(/\D/g,''))}
              />
              <input
                type="text"
                placeholder="YYYY"
                maxLength={4}
                value={form.yyyy}
                onChange={e => update('yyyy', e.target.value.replace(/\D/g,''))}
              />
            </div>
          </div>

          <div className="bk-field">
            <textarea
              placeholder="Athugasemd (Valkvætt)"
              value={form.notes}
              onChange={e => update('notes', e.target.value)}
              rows={3}
            />
          </div>

          {serverError && <p className="bk-error bk-server-error">{serverError}</p>}

          <button type="submit" className="bk-submit" disabled={sending}>
            {sending ? '...' : 'Bóka'}
          </button>
        </form>

        <p className="bk-footer-note">-Nákvæmari staðsetning auglýst þegar tími er staðfestur-</p>
      </div>
    </main>
  )
}

export default Booking
