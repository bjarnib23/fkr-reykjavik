import { useState, useEffect } from 'react'
import './Lookbook.css'

function Lookbook() {
  const [images, setImages] = useState([])

  useEffect(() => {
    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/lookbook`, { cache: 'no-store' })
      .then(r => r.json())
      .then(setImages)
  }, [])

  return (
    <main className="lookbook">
      <div className="lookbook-grid">
        {images.map((src, i) => (
          <div key={i} className="lookbook-item">
            <img src={src} alt="" loading="lazy" />
          </div>
        ))}
      </div>
    </main>
  )
}

export default Lookbook
