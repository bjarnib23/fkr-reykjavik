import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import Home from './pages/Home'
import Booking from './pages/booking/Booking'
import FAQ from './pages/FAQ'
import PriceList from './pages/PriceList'
import GiftCard from './pages/GiftCard'
import GiftCardThankYou from './pages/GiftCardThankYou'
import GiftCardCancel from './pages/GiftCardCancel'

function App() {
  return (
    <BrowserRouter>
      <Navbar />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/boka-tima" element={<Booking />} />
        <Route path="/ferlid" element={<p>Ferlið</p>} />
        <Route path="/algengar-spurningar" element={<FAQ />} />
        <Route path="/gjafabref" element={<GiftCard />} />
        <Route path="/verdskra" element={<PriceList />} />
        <Route path="/giftcard/thank-you" element={<GiftCardThankYou />} />
        <Route path="/giftcard/cancel" element={<GiftCardCancel />} />
      </Routes>
      <Footer />
    </BrowserRouter>
  )
}

export default App