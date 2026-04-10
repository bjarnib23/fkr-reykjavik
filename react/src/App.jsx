import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import Home from './pages/Home'
import Booking from './pages/booking/Booking'
import FAQ from './pages/FAQ'
import PriceList from './pages/PriceList'
import Process from './pages/Process'
import GiftCard from './pages/GiftCard'

function App() {
  return (
    <BrowserRouter>
      <Navbar />
      <Routes>
        <Route path="/"                      element={<Home />} />
        <Route path="/boka-tima"             element={<Booking />} />
        <Route path="/ferlid"                element={<Process />} />
        <Route path="/algengar-spurningar"   element={<FAQ />} />
        <Route path="/gjafabref"             element={<GiftCard />} />
        <Route path="/verdskra"              element={<PriceList />} />
      </Routes>
      <Footer />
    </BrowserRouter>
  )
}

export default App
