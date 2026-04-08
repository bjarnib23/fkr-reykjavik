import { useState, useEffect } from 'react'
import './FAQ.css'

function FAQ() {
    const [faqs, setFaqs] = useState([])
    const [pageTitle, setPageTitle] = useState('')

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/faq', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                setPageTitle(data.page_title || '')
                setFaqs(data.items)
            })
    }, [])

    return (
        <main className="faq">
            <h1>{pageTitle}</h1>
            <div className="faq-grid">
                {faqs.map((faq, i) => (
                    <div key={i} className="faq-card">
                        <h3>{faq.question}</h3>
                        <div dangerouslySetInnerHTML={{ __html: faq.answer }} />
                    </div>
                ))}
            </div>
        </main>
    )
}

export default FAQ
