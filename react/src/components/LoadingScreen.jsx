import { useEffect, useState } from 'react'
import { useLoading } from '../context/LoadingContext'
import './LoadingScreen.css'

function LoadingScreen() {
  const { loading } = useLoading()
  const [visible, setVisible] = useState(true)

  useEffect(() => {
    if (loading) {
      setVisible(true)
    } else {
      const t = setTimeout(() => setVisible(false), 700)
      return () => clearTimeout(t)
    }
  }, [loading])

  if (!visible) return null

  return (
    <div className={`loading-screen${loading ? '' : ' loading-screen--out'}`}>
      <img src="/fkr-logo-transparent.png" alt="FKR Rvk." className="loading-logo" />
    </div>
  )
}

export default LoadingScreen
