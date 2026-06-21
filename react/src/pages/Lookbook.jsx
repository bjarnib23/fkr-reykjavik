import { useState, useEffect } from 'react'
import { useLoading } from '../context/LoadingContext'
import { preloadImages } from '../context/preloadImages'
import './Lookbook.css'

function Lookbook() {
  const [images, setImages] = useState([])
  const { setLoading } = useLoading()

  useEffect(() => {
    fetch(`${import.meta.env.VITE_DRUPAL_URL}/api/fkr/lookbook`, { cache: 'no-store' })
      .then(r => r.json())
      .then(async imgs => {
        setImages(imgs)
        await preloadImages(imgs.slice(0, 6))
        setLoading(false)
      })
      .catch(() => setLoading(false))
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
