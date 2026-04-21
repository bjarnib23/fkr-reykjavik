import { Link } from 'react-router-dom'

function GiftCardThankYou() {
  return (
    <main style={{ maxWidth: 600, margin: '80px auto', padding: '0 24px', textAlign: 'center' }}>
      <h1 style={{ fontSize: 36, letterSpacing: 4, marginBottom: 24 }}>TAKK FYRIR</h1>
      <p style={{ fontSize: 16, lineHeight: 1.6, marginBottom: 8 }}>
        Greiðslan tókst. Gjafabréfið þitt hefur verið sent á tölvupóstfangið þitt.
      </p>
      <p style={{ fontSize: 14, color: '#666', marginBottom: 40 }}>
        Ef þú færð ekki tölvupóst innan nokkrar mínútur skaltu athuga ruslpóstinn.
      </p>
      <Link
        to="/"
        style={{
          background: '#263A38',
          color: 'white',
          padding: '14px 32px',
          textDecoration: 'none',
          fontSize: 15,
          fontFamily: 'inherit',
        }}
      >
        Til baka á forsíðu
      </Link>
    </main>
  )
}

export default GiftCardThankYou
