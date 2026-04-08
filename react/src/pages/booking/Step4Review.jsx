import { useState, useEffect } from 'react'
import './Step4Review.css'

function Step4Review({ data, update, back }) {
    const [pageTitle, setPageTitle] = useState('')
    const [pageDesc, setPageDesc] = useState('')
    const [submitted, setSubmitted] = useState(false)

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
            .then(res => res.json())
            .then(pages => {
                const page = Object.values(pages).find(p => p.slug === 'booking_step4')
                if (page) {
                    setPageTitle(page.subtitle || '')
                    setPageDesc(page.body_text || '')
                }
            })
    }, [])

    function formatDate(dateStr) {
        if (!dateStr) return ''
        const [y, m, d] = dateStr.split('-')
        return `${d}.${m}.${y}`
    }

    async function handleSubmit() {
        const payload = {
            ...data,
            date: `${data.date}T${data.time}:00`,
        }
        const res = await fetch('http://fkr-reykjavik.ddev.site/api/fkr/booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        if (res.ok) {
            setSubmitted(true)
        } else {
            alert('Eitthvað fór úrskeiðis, reyndu aftur.')
        }
    }

    if (submitted) {
        return (
            <div className="review-confirmation">
                <h2>Takk fyrir!</h2>
                <p>Bókunarbeiðnin hefur verið móttekin. Staðfesting verður send á netfangið þitt innan skamms.</p>
                <button onClick={back}>Til Baka</button>
            </div>
        )
    }

    return (
        <div>
            <h2>{pageTitle}</h2>
            {pageDesc && <div dangerouslySetInnerHTML={{ __html: pageDesc }} />}

            <table className="review-table">
                <tbody>
                    <tr><td>Þjónusta</td><td><strong>{data.service}</strong></td></tr>
                    <tr><td>Dagsetning</td><td><strong>{formatDate(data.date)}</strong></td></tr>
                    <tr><td>Tími</td><td><strong>{data.time}</strong></td></tr>
                    <tr><td>Nafn</td><td><strong>{data.name}</strong></td></tr>
                    <tr><td>Tölvupóstur</td><td><strong>{data.email}</strong></td></tr>
                    <tr><td>Sími</td><td><strong>{data.phone}</strong></td></tr>
                </tbody>
            </table>

            <div className="review-notes">
                <label><strong>Athugasemd (valkvæmt)</strong></label>
                <textarea
                    placeholder="Skrifaðu hér ef þú vilt bæta einhverju við..."
                    onChange={e => update({ notes: e.target.value })}
                />
            </div>

            <div className="step-buttons">
                <button onClick={back}>Til baka</button>
                <button onClick={handleSubmit}>Senda bókunarbeiðni</button>
            </div>
        </div>
    )
}

export default Step4Review
