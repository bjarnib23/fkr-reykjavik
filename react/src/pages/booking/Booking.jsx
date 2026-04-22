import { useState, useEffect } from 'react'
import Step1Service from './Step1Service'
import Step2Date from './Step2Date'
import Step3Contact from './Step3Contact'
import Step4Review from './Step4Review'
import './Booking.css'

const STEP_SLUGS = ['booking_step1', 'booking_step2', 'booking_step3', 'booking_step4']

function Booking() {
  const [step, setStep]         = useState(1)
  const [stepTitles, setTitles] = useState({})
  const [data, setData] = useState({
    service: '', date: '', time: '', name: '', email: '', phone: '', notes: ''
  })

  useEffect(() => {
    fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
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

  function next()               { setStep(s => s + 1) }
  function back()               { setStep(s => s - 1) }
  function update(fields)       { setData(d => ({ ...d, ...fields })) }

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

      <div className="booking-body">
        <div className="booking-content">
          {step === 1 && <Step1Service data={data} update={update} next={next} />}
          {step === 2 && <Step2Date    data={data} update={update} next={next} back={back} />}
          {step === 3 && <Step3Contact data={data} update={update} next={next} back={back} />}
          {step === 4 && <Step4Review  data={data} update={update} back={back} />}
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
          {step > 1 && (
            <button className="booking-summary-continue" onClick={next} disabled={
              (step === 1 && !data.service) ||
              (step === 2 && (!data.date || !data.time)) ||
              (step === 3 && (!data.name || !data.email || !data.phone))
            }>
              Continue →
            </button>
          )}
        </div>
      </div>
    </main>
  )
}

export default Booking
