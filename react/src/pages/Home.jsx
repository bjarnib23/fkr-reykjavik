import { Link } from 'react-router-dom'                           
  import './Home.css'                    
                                                                    
  function Home() {
    return (                                                        
      <main>      
        <section className="hero">
          <h1>FKR</h1>            
          <p>REYKJAVÍK</p>
          <p>Sígild klæði</p>
        </section>                                                  
                  
        <section className="intro">                                 
          <h2>Sérsaumuð jakkafót</h2>
          <Link to="/boka-tima">Bóka Tíma</Link>
        </section>
      </main>                                                       
    )
  }   

export default Home