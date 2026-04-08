import { useState, useEffect } from 'react'
import Step1Service from './Step1Service'
import Step2Date from './Step2Date'
import Step3Contact from './Step3Contact'
import Step4Review from './Step4Review'
import './Booking.css'

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

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
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

    function next() { setStep(step + 1) }
    function back() { setStep(step - 1) }
    function update(fields) { setData({ ...data, ...fields }) }

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

            {step === 1 && <Step1Service data={data} update={update} next={next} />}
            {step === 2 && <Step2Date data={data} update={update} next={next} back={back} />}
            {step === 3 && <Step3Contact data={data} update={update} next={next} back={back} />}
            {step === 4 && <Step4Review data={data} update={update} back={back} />}
        </main>
    )
}

export default Booking
