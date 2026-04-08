import { useState, useEffect } from 'react'
import './PriceList.css'

function PriceList() {
    const [grades, setGrades] = useState([])
    const [rows, setRows] = useState([])
    const [pageTitle, setPageTitle] = useState('')
    const [bodyText, setBodyText] = useState('')
    const [images, setImages] = useState([])
    const [currentImage, setCurrentImage] = useState(0)

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pricelist', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                setGrades(data.grades)
                setRows(data.rows)
            })

        fetch('http://fkr-reykjavik.ddev.site/api/fkr/pages', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                const page = Object.values(data).find(p => p.slug === 'pricelist')
                if (page) {
                    setPageTitle(page.subtitle || '')
                    setBodyText(page.body_text || '')
                    setImages(page.images || [])
                }
            })
    }, [])

    const formatPrice = (price) => {
        if (price === null) return '—'
        return price.toLocaleString('is-IS') + ' kr'
    }

    return (
        <main className="pricelist">
            <div className="pricelist-hero">
                {images.length > 0 && (
                    <div className="pricelist-carousel">
                        <img src={images[currentImage]} alt={pageTitle} />
                        {images.length > 1 && (
                            <>
                                <button className="carousel-btn prev" onClick={() => setCurrentImage(i => (i - 1 + images.length) % images.length)}>←</button>
                                <button className="carousel-btn next" onClick={() => setCurrentImage(i => (i + 1) % images.length)}>→</button>
                            </>
                        )}
                    </div>
                )}
                <div className="pricelist-hero-text">
                    <h1>{pageTitle}</h1>
                    {bodyText && <div dangerouslySetInnerHTML={{ __html: bodyText }} />}
                </div>
            </div>
            <div className="pricelist-table-wrapper">
                <table className="pricelist-table">
                    <thead>
                        <tr>
                            <th>Vara</th>
                            {grades.map(g => <th key={g}>{g}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, i) => (
                            <tr key={i}>
                                <td>{row.item}</td>
                                {grades.map(g => (
                                    <td key={g}>{formatPrice(row.prices[g.toLowerCase()])}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </main>
    )
}

export default PriceList
