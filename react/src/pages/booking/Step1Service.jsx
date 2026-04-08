import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import './Step1Service.css'

function Step1Service({ data, update, next }) {
    const navigate = useNavigate()
    const [services, setServices] = useState([])
    const [pageTitle, setPageTitle] = useState('')
    const [pageDesc, setPageDesc] = useState('')

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/services', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => setServices(data))

        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
            .then(res => res.json())
            .then(pages => {
                const page = Object.values(pages).find(p => p.slug === 'booking_step1')
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
            <div className="service-options">
                {services.map(s => (
                    <div
                        key={s.id}
                        className={`service-card ${data.service === s.title ? 'selected' : ''}`}
                        onClick={() => update({ service: s.title })}
                    >
                        {s.image && <img src={s.image} alt={s.title} className="service-card-img" />}
                        <div className={s.image ? 'service-card-label' : 'service-card-label-plain'}>
                            <strong>{s.title}</strong>
                        </div>
                    </div>
                ))}
            </div>
            <div className="step-buttons">
                <button onClick={() => navigate(-1)}>Til baka</button>
                <button onClick={next} disabled={!data.service}>Næsta skref</button>
            </div>
        </div>
    )
}

export default Step1Service
