import { useState, useEffect } from 'react'
import Calendar from "./Calendar"

function Step2Date({ data, update, next, back }) {
    const [slots, setSlots] = useState([])
    const [pageTitle, setPageTitle] = useState('')
    const [pageDesc, setPageDesc] = useState('')

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
            .then(res => res.json())
            .then(pages => {
                const page = Object.values(pages).find(p => p.slug === 'booking_step2')
                if (page) {
                    setPageTitle(page.subtitle || '')
                    setPageDesc(page.body_text || '')
                }
            })
    }, [])

    useEffect(() => {
        if (!data.date) return
        fetch(`http://fkr-reykjavik.ddev.site/api/fkr/availability?date=${data.date}`, { cache: 'no-store' })
            .then(res => res.json())
            .then(slots => setSlots(slots.filter(s => s.status === 'available')))
    }, [data.date])

    return (
        <div>
            <h2>{pageTitle}</h2>
            {pageDesc && <div dangerouslySetInnerHTML={{ __html: pageDesc }} />}

            <Calendar selected={data.date} onSelect={date => { update({ date, time: '' }); setSlots([]) }} />

            {data.date && (
                <div>
                    <p><strong>Lausir tímar — {data.date}</strong></p>
                    <div className="time-options">
                        {slots.length === 0
                            ? <p>Engir lausir tímar þennan dag.</p>
                            : slots.map(s => (
                                <button
                                    key={s.time}
                                    className={data.time === s.time ? 'time-btn selected' : 'time-btn'}
                                    onClick={() => update({ time: s.time })}
                                >
                                    {s.time}
                                </button>
                            ))
                        }
                    </div>
                </div>
            )}

            <div className="step-buttons">
                <button onClick={back}>Til baka</button>
                <button onClick={next} disabled={!data.date || !data.time}>Næsta skref</button>
            </div>
        </div>
    )
}

export default Step2Date
