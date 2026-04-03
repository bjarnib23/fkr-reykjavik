import { BrowserRouter, Routes, Route } from 'react-router-dom'
  import Navbar from './components/Navbar'
  import Footer from './components/Footer'
  import Home from './pages/Home'
  import Booking from './pages/booking/Booking'  
                                                                    
  function App() {
    return (                                                        
      <BrowserRouter>
        <Navbar />
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/boka-tima" element={<Booking />} />
          <Route path="/ferlid" element={<p>Ferlið</p>} />
          <Route path="/algengar-spurningar" element={<p>Algengar spurningar</p>} />
          <Route path="/gjafabref" element={<p>Gjafabréf</p>} />
          <Route path="/verdskra" element={<p>Verðskrá</p>} />
        </Routes>
        <Footer/>
      </BrowserRouter>
    )
  }

  export default App