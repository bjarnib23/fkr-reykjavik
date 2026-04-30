import { useState, useEffect, useRef } from 'react'
import Step1Service from './Step1Service'
import Step2Date from './Step2Date'
import Step3Contact from './Step3Contact'
import Step4Review from './Step4Review'
import './Booking.css'

const DRUPAL     = 'http://fkr-reykjavik.ddev.site'
const STEP_SLUGS = ['booking_step1', 'booking_step2', 'booking_step3', 'booking_step4']

function Booking() {
  const [step, setStep]               = useState(1)
  const [stepTitles, setTitles]       = useState({})
  const [stepButtons, setButtons]     = useState({})
  const [stepLabels, setLabels]       = useState({})
  const [studioAddress, setStudio]    = useState('')
  const [summaryLabels, setSummary]   = useState({})
  const [messages, setMessages]       = useState({})
  const [data, setData]               = useState({
    service: '', date: '', time: '', name: '', email: '', phone: '', notes: ''
  })
  const [submitted, setSubmitted]         = useState(false)
  const [holdToken, setHoldToken]         = useState(null)
  const [holdExpires, setHoldExpires]     = useState(null)
  const [secondsLeft, setSecondsLeft]     = useState(null)
  const [holdExpiredMsg, setHoldExpiredMsg] = useState(false)
  const [releaseReady, setReleaseReady]   = useState(false)
  const timerRef    = useRef(null)
  const holdTokenRef = useRef(null)

  useEffect(() => {
    history.replaceState({ step: 1 }, '')
    const orphan = sessionStorage.getItem('holdToken')
    if (orphan) {
      sessionStorage.removeItem('holdToken')
      fetch(`${DRUPAL}/api/fkr/booking/hold-release`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: orphan }),
      }).finally(() => setReleaseReady(true))
    } else {
      setReleaseReady(true)
    }
  }, [])

  useEffect(() => { holdTokenRef.current = holdToken }, [holdToken])

  useEffect(() => {
    const onPopState = (e) => {
      if (!e.state?.step) return
      if (e.state.step <= 2 && holdTokenRef.current) {
        setReleaseReady(false)
        const token = holdTokenRef.current
        fetch(`${DRUPAL}/api/fkr/booking/hold-release`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token }),
        }).finally(() => {
          holdTokenRef.current = null
          setHoldToken(null)
          setHoldExpires(null)
          setSecondsLeft(null)
          clearInterval(timerRef.current)
          sessionStorage.removeItem('holdToken')
          setReleaseReady(true)
        })
        setData(d => ({ ...d, date: '', time: '', name: '', email: '', phone: '', notes: '' }))
      }
      if (e.state.step === 1) {
        setData(d => ({ ...d, name: '', email: '', phone: '', notes: '', date: '', time: '' }))
      }
      setStep(e.state.step)
    }
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [])

  useEffect(() => {
    fetch(`${DRUPAL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const t = {}
        const b = {}
        const l = {}
        Object.values(pages).forEach(p => {
          const i = STEP_SLUGS.indexOf(p.slug)
          if (i >= 0) {
            t[i + 1] = p.subtitle || p.title || ''
            b[i + 1] = { primary: p.primary_button || '', secondary: p.secondary_button || '' }
            l[i + 1] = {
              name: p.label_name || '', phone: p.label_phone || '', email: p.label_email || '',
              service: p.label_service || '', date: p.label_date || '', time: p.label_time || '',
              notes: p.label_notes || '', placeholderNotes: p.placeholder_notes || '',
              successHeading: p.success_heading || '', successBody: p.success_body || '', successButton: p.success_button || '',
              errName: p.err_name_required || '', errPhone: p.err_phone_required || '', errEmail: p.err_email_required || '', errInvalidEmail: p.err_invalid_email || '',
              slots: p.label_slots || '', noSlots: p.label_no_slots || '',
              placeholderName: p.placeholder_name || '', placeholderPhone: p.placeholder_phone || '', placeholderEmail: p.placeholder_email || '',
            }
          }
        })
        setTitles(t)
        setButtons(b)
        setLabels(l)
      })
    fetch(`${DRUPAL}/api/fkr/settings`, { cache: 'no-store' })
      .then(r => r.json())
      .then(s => {
        setStudio(s.address || '')
        setSummary({
          heading:  s.summary_heading  || '',
          location: s.summary_location || '',
          when:     s.summary_when     || '',
          name:     s.summary_name     || '',
          phone:    s.summary_phone    || '',
          email:    s.summary_email    || '',
        })
        setMessages({
          slotTaken:     s.msg_slot_taken     || '',
          holdExpired:   s.msg_hold_expired   || '',
          submitError:   s.msg_submit_error   || '',
          holdCountdown: s.msg_hold_countdown || '',
        })
      })
  }, [])

  useEffect(() => {
    const onUnload = () => {
      if (!holdToken) return
      fetch(`${DRUPAL}/api/fkr/booking/hold-release`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: holdToken }),
        keepalive: true,
      })
    }
    window.addEventListener('beforeunload', onUnload)
    return () => window.removeEventListener('beforeunload', onUnload)
  }, [holdToken])

  useEffect(() => {
    if (!holdExpires) return
    clearInterval(timerRef.current)
    timerRef.current = setInterval(() => {
      const secs = holdExpires - Math.floor(Date.now() / 1000)
      if (secs <= 0) {
        clearInterval(timerRef.current)
        setSecondsLeft(0)
        releaseHold(holdToken)
        setHoldToken(null)
        setHoldExpires(null)
        setHoldExpiredMsg(true)
        history.replaceState({ step: 2 }, '')
        setStep(2)
      } else {
        setSecondsLeft(secs)
      }
    }, 1000)
    return () => clearInterval(timerRef.current)
  }, [holdExpires, holdToken])

  function releaseHold(token, onDone) {
    if (!token) { onDone?.(); return }
    sessionStorage.removeItem('holdToken')
    setHoldToken(null)
    setHoldExpires(null)
    setSecondsLeft(null)
    clearInterval(timerRef.current)
    fetch(`${DRUPAL}/api/fkr/booking/hold-release`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token }),
    }).finally(() => onDone?.())
  }

  async function nextFromStep2() {
    const datetime = `${data.date}T${data.time}:00`
    const res = await fetch(`${DRUPAL}/api/fkr/booking/hold`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ datetime }),
    })
    if (!res.ok) {
      alert(messages.slotTaken)
      return
    }
    const { token, expires } = await res.json()
    setHoldToken(token)
    setHoldExpires(expires)
    sessionStorage.setItem('holdToken', token)
    setHoldExpiredMsg(false)
    history.pushState({ step: 3 }, '')
    setStep(3)
  }

  function next() { const s = step + 1; history.pushState({ step: s }, ''); setStep(s) }
  function back() { history.back() }
  function update(fields) { setData(d => ({ ...d, ...fields })) }

  function formatTime(secs) {
    const m = Math.floor(secs / 60)
    const s = secs % 60
    return `${m}:${String(s).padStart(2, '0')}`
  }

  const showCountdown = holdExpires && secondsLeft > 0 && step >= 3

  return (
    <main className="booking">
      <div className="booking-stepper">
        {STEP_SLUGS.map((_, i) => (
          <div
            key={i}
            className={`booking-step-tab${step === i + 1 ? ' active' : ''}${step > i + 1 ? ' done' : ''}`}
            onClick={() => {
              const target = i + 1
              if (submitted || target >= step) return
              if (target <= 2 && holdTokenRef.current) {
                setReleaseReady(false)
                const token = holdTokenRef.current
                fetch(`${DRUPAL}/api/fkr/booking/hold-release`, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ token }),
                }).finally(() => {
                  holdTokenRef.current = null
                  setHoldToken(null)
                  setHoldExpires(null)
                  setSecondsLeft(null)
                  clearInterval(timerRef.current)
                  sessionStorage.removeItem('holdToken')
                  setReleaseReady(true)
                })
                setData(d => ({ ...d, date: '', time: '', name: '', email: '', phone: '', notes: '' }))
              }
              if (target === 1) {
                setData(d => ({ ...d, name: '', email: '', phone: '', notes: '', date: '', time: '' }))
              }
              history.pushState({ step: target }, '')
              setStep(target)
            }}
            style={i + 1 < step && !submitted ? { cursor: 'pointer' } : {}}
          >
            <span className="booking-step-num">{String(i + 1).padStart(2, '0')}</span>
            {stepTitles[i + 1] && <span className="booking-step-label">· {stepTitles[i + 1]}</span>}
          </div>
        ))}
      </div>

      {showCountdown && (
        <div className="hold-countdown">
          {messages.holdCountdown} <strong>{formatTime(secondsLeft)}</strong>
        </div>
      )}

      {holdExpiredMsg && step === 2 && (
        <div className="hold-expired">
          {messages.holdExpired}
        </div>
      )}

      <div className="booking-body">
        <div className="booking-content">
          {step === 1 && <Step1Service data={data} update={update} next={next} back={back} buttons={stepButtons[1]} />}
          {step === 2 && <Step2Date    data={data} update={update} next={nextFromStep2} back={back} releaseReady={releaseReady} heading={stepTitles[2]} buttons={stepButtons[2]} labels={stepLabels[2]} />}
          {step === 3 && <Step3Contact data={data} update={update} next={next} back={back} heading={stepTitles[3]} buttons={stepButtons[3]} labels={stepLabels[3]} />}
          {step === 4 && <Step4Review  data={data} update={update} back={back} releaseHold={() => releaseHold(holdToken)} heading={stepTitles[4]} buttons={stepButtons[4]} labels={stepLabels[4]} submitError={messages.submitError} onSubmitted={() => setSubmitted(true)} />}
        </div>

        <div className={`booking-summary${step === 4 ? ' hidden' : ''}`}>
          <p className="booking-summary-label">{summaryLabels.heading}</p>
          {data.service && (
            <>
              <div className="booking-summary-row">
                <span>{stepLabels[4]?.service}</span><span>{data.service}</span>
              </div>
              <div className="booking-summary-row">
                <span>{summaryLabels.location}</span><span>{studioAddress}</span>
              </div>
            </>
          )}
          {data.date && (
            <div className="booking-summary-row">
              <span>{summaryLabels.when}</span>
              <span>{data.date}{data.time ? ` · ${data.time}` : ''}</span>
            </div>
          )}
          {data.name && (
            <div className="booking-summary-row">
              <span>{summaryLabels.name}</span><span>{data.name}</span>
            </div>
          )}
          {data.email && (
            <div className="booking-summary-row">
              <span>{summaryLabels.email}</span><span>{data.email}</span>
            </div>
          )}
          {data.phone && (
            <div className="booking-summary-row">
              <span>{summaryLabels.phone}</span><span>{data.phone}</span>
            </div>
          )}
        </div>
      </div>
    </main>
  )
}

export default Booking
