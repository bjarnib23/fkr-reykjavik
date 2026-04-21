import { Link } from 'react-router-dom'

function GiftCardCancel() {
  return (
    <main style={{ maxWidth: 600, margin: '80px auto', padding: '0 24px', textAlign: 'center' }}>
      <h1 style={{ fontSize: 36, letterSpacing: 4, marginBottom: 24 }}>GREIÐSLU HÆTT VIÐ</h1>
      <p style={{ fontSize: 16, lineHeight: 1.6, marginBottom: 40 }}>
        Greiðslunni var hætt við. Ekkert var rukkað.
      </p>
      <Link
        to="/gjafabref"
        style={{
          background: '#263A38',
          color: 'white',
          padding: '14px 32px',
          textDecoration: 'none',
          fontSize: 15,
          fontFamily: 'inherit',
        }}
      >
        Reyna aftur
      </Link>
    </main>
  )
}

export default GiftCardCancel
