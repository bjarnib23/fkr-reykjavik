import { useState, useEffect } from 'react'

function Step3Contact({ data, update, next, back }) {
    const [pageTitle, setPageTitle] = useState('')
    const [pageDesc, setPageDesc] = useState('')

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
            .then(res => res.json())
            .then(pages => {
                const page = Object.values(pages).find(p => p.slug === 'booking_step3')
                if (page) {
                    setPageTitle(page.subtitle || '')
                    setPageDesc(page.body_text || '')
                }
            })
    }, [])

    return (
        <div>
            <h2>{pageTitle}</h2>
            {pageDesc && <div dangerouslySetInnerHTML={{ __html: pageDesc }} />}

            <input
                placeholder="Nafn *"
                value={data.name}
                onChange={e => update({ name: e.target.value })}
            />
            <input
                placeholder="Tölvupóstur *"
                value={data.email}
                onChange={e => update({ email: e.target.value })}
            />
            <input
                placeholder="Sími *"
                value={data.phone}
                onChange={e => update({ phone: e.target.value })}
            />

            <div className="step-buttons">
                <button onClick={back}>Til baka</button>
                <button onClick={next} disabled={!data.name || !data.email || !data.phone}>Næsta skref</button>
            </div>
        </div>
    )
}

export default Step3Contact
