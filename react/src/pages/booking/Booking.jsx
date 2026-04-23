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
  const [data, setData]               = useState({
    service: '', date: '', time: '', name: '', email: '', phone: '', notes: ''
  })
  const [holdToken, setHoldToken]         = useState(null)
  const [holdExpires, setHoldExpires]     = useState(null)
  const [secondsLeft, setSecondsLeft]     = useState(null)
  const [holdExpiredMsg, setHoldExpiredMsg] = useState(false)
  const [releaseReady, setReleaseReady]   = useState(false)
  const timerRef = useRef(null)

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

  useEffect(() => {
    const onPopState = (e) => { if (e.state?.step) setStep(e.state.step) }
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [])

  useEffect(() => {
    fetch(`${DRUPAL}/api/fkr/pages`, { cache: 'no-store' })
      .then(r => r.json())
      .then(pages => {
        const t = {}
        Object.values(pages).forEach(p => {
          const i = STEP_SLUGS.indexOf(p.slug)
          if (i >= 0) t[i + 1] = p.subtitle || p.title || ''
        })
        setTitles(t)
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

  function releaseHold(token) {
    if (!token) return
    sessionStorage.removeItem('holdToken')
    setHoldToken(null)
    setHoldExpires(null)
    setSecondsLeft(null)
    clearInterval(timerRef.current)
    fetch(`${DRUPAL}/api/fkr/booking/hold`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token }),
    })
  }

  async function nextFromStep2() {
    const datetime = `${data.date}T${data.time}:00`
    const res = await fetch(`${DRUPAL}/api/fkr/booking/hold`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ datetime }),
    })
    if (!res.ok) {
      alert('Þessi tími er þegar frátekinn, vinsamlegast veldu annan tíma.')
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

  function next()         { const s = step + 1; history.pushState({ step: s }, ''); setStep(s) }
  function back()         { history.back() }
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
          >
            <span className="booking-step-num">{String(i + 1).padStart(2, '0')}</span>
            {stepTitles[i + 1] && <span className="booking-step-label">· {stepTitles[i + 1]}</span>}
          </div>
        ))}
      </div>

      {showCountdown && (
        <div className="hold-countdown">
          Tíminn þinn er frátekinn í <strong>{formatTime(secondsLeft)}</strong>
        </div>
      )}

      {holdExpiredMsg && step === 2 && (
        <div className="hold-expired">
          Frátekningartíminn rann út. Vinsamlegast veldu tíma aftur.
        </div>
      )}

      <div className="booking-body">
        <div className="booking-content">
          {step === 1 && <Step1Service data={data} update={update} next={next} />}
          {step === 2 && <Step2Date    data={data} update={update} next={nextFromStep2} back={back} releaseReady={releaseReady} />}
          {step === 3 && <Step3Contact data={data} update={update} next={next} back={back} />}
          {step === 4 && <Step4Review  data={data} update={update} back={back} releaseHold={() => releaseHold(holdToken)} />}
        </div>

        <div className="booking-summary">
          <p className="booking-summary-label">Summary</p>
          {data.service && (
            <>
              <div className="booking-summary-row">
                <span>Service</span><span>{data.service}</span>
              </div>
              <div className="booking-summary-row">
                <span>Studio</span><span>Fossvogur · Reykjavík</span>
              </div>
            </>
          )}
          {data.date && (
            <div className="booking-summary-row">
              <span>When</span>
              <span>{data.date}{data.time ? ` · ${data.time}` : ''}</span>
            </div>
          )}
          {data.name && (
            <div className="booking-summary-row">
              <span>Name</span><span>{data.name}</span>
            </div>
          )}
        </div>
      </div>
    </main>
  )
}

export default Booking
