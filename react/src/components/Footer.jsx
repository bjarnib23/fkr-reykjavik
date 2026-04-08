import { useState, useEffect } from 'react'
import './Footer.css'

function Footer() {
    const [settings, setSettings] = useState({})

    useEffect(() => {
        fetch('http://fkr-reykjavik.ddev.site/api/fkr/settings', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => setSettings(data))
    }, [])

    return (
        <footer className="footer">
            {settings.footer_heading && <p><strong>{settings.footer_heading}</strong></p>}
            {settings.address && <p>{settings.address}</p>}
            {settings.company_id && <p>{settings.company_id}</p>}
            {settings.phone && <p>{settings.phone}</p>}
        </footer>
    )
}

export default Footer
