import { useState, useEffect, useRef } from 'react'
import Step1Service from './Step1Service'
import Step2Date from './Step2Date'
import Step3Contact from './Step3Contact'
import Step4Review from './Step4Review'
import './Booking.css'

const DRUPAL = 'http://fkr-reykjavik.ddev.site'

function Booking() {
    const [step, setStep] = useState(1)
    const [data, setData] = useState({
        service: '',
        date: '',
        time: '',
        name: '',
        email: '',
        phone: '',
        notes: ''
    })
    const [stepTitles, setStepTitles] = useState({})
    const [holdToken, setHoldToken] = useState(null)
    const [holdExpires, setHoldExpires] = useState(null)
    const [secondsLeft, setSecondsLeft] = useState(null)
    const [holdExpiredMsg, setHoldExpiredMsg] = useState(false)
    const [releaseReady, setReleaseReady] = useState(false)
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
        const onPopState = (e) => {
            if (e.state?.step) setStep(e.state.step)
        }
        window.addEventListener('popstate', onPopState)
        return () => window.removeEventListener('popstate', onPopState)
    }, [])

    useEffect(() => {
        fetch(`${DRUPAL}/api/fkr/pages`, { cache: 'no-store' })
            .then(res => res.json())
            .then(pages => {
                const titles = {}
                Object.values(pages).forEach(p => {
                    if (p.slug === 'booking_step1') titles[1] = p.subtitle
                    if (p.slug === 'booking_step2') titles[2] = p.subtitle
                    if (p.slug === 'booking_step3') titles[3] = p.subtitle
                    if (p.slug === 'booking_step4') titles[4] = p.subtitle
                })
                setStepTitles(titles)
            })
    }, [])

    // Release hold on page close/refresh
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

    // Countdown ticker
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
        const nextStep = 3
        history.pushState({ step: nextStep }, '')
        setStep(nextStep)
    }

    function next() {
        const nextStep = step + 1
        history.pushState({ step: nextStep }, '')
        setStep(nextStep)
    }

    function back() {
        history.back()
    }

    function update(fields) { setData({ ...data, ...fields }) }

    function formatTime(secs) {
        const m = Math.floor(secs / 60)
        const s = secs % 60
        return `${m}:${String(s).padStart(2, '0')}`
    }

    const showCountdown = holdExpires && secondsLeft > 0 && step >= 3

    return (
        <main className="booking-wrapper">
            <h1>TÍMABÓKUN</h1>
            <div className="progress-bar">
                <div className="progress-fill" style={{ width: `${(step / 4) * 100}%` }}></div>
            </div>
            <div className="step-label-row">
                <p className="step-label">Skref {step} af 4</p>
                {stepTitles[step] && <p className="step-title">{stepTitles[step]}</p>}
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

            {step === 1 && <Step1Service data={data} update={update} next={next} />}
            {step === 2 && <Step2Date data={data} update={update} next={nextFromStep2} back={back} releaseReady={releaseReady} />}
            {step === 3 && <Step3Contact data={data} update={update} next={next} back={back} />}
            {step === 4 && <Step4Review data={data} update={update} back={back} releaseHold={() => releaseHold(holdToken)} />}
        </main>
    )
}

export default Booking
