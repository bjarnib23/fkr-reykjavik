import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { useEffect } from 'react'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import Home from './pages/Home'
import Booking from './pages/booking/Booking'
import FAQ from './pages/FAQ'
import PriceList from './pages/PriceList'
import GiftCard from './pages/GiftCard'
import GiftCardThankYou from './pages/GiftCardThankYou'
import GiftCardCancel from './pages/GiftCardCancel'
import Process from './pages/Process'
import Lookbook from './pages/Lookbook'

function ScrollToTop() {
  const { pathname } = useLocation()
  useEffect(() => { window.scrollTo(0, 0) }, [pathname])
  return null
}

function App() {
  return (
    <BrowserRouter>
      <ScrollToTop />
      <Navbar />
      <main style={{ flex: 1 }}>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/boka-tima" element={<Booking />} />
          <Route path="/ferlid" element={<Process />} />
          <Route path="/myndasafn" element={<Lookbook />} />
          <Route path="/algengar-spurningar" element={<FAQ />} />
          <Route path="/gjafabref" element={<GiftCard />} />
          <Route path="/verdskra" element={<PriceList />} />
          <Route path="/giftcard/thank-you" element={<GiftCardThankYou />} />
          <Route path="/giftcard/cancel" element={<GiftCardCancel />} />
        </Routes>
      </main>
      <Footer />
    </BrowserRouter>
  )
}

export default App