import {useState, useEffect } from 'react'
import './FAQ.css'

function FAQ() {
    const [faqs, setFaqs] = useState([])

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/jsonapi/node/fkr_faq?sort=field_faq_weight', { cache: 'no-store' })         
        .then(res => res.json())                                                                 
        .then(data => setFaqs(data.data))
    }, [])

    return (
        <main className="faq">
            <h1>Spurt og svarað</h1>
            <div className="faq-grid">
                {faqs.map(faq => (
                    <div key={faq.id} className="faq-card">
                        <h3>{faq.attributes.title}</h3>
                        <div dangerouslySetInnerHTML={{ __html: faq.attributes.field_answer.value }} />
                    </div>
                ))}
            </div>
        </main>
    )
}

export default FAQ