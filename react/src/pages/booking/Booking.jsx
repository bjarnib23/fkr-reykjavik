import {useState} from 'react'
import Step1Service from './Step1Service'
import Step2Date from './Step2Date'
import Step3Contact from './Step3Contact'                         
import Step4Review from './Step4Review'
import './Booking.css' 

function Booking() {                                                                          
    const [step, setStep] = useState(1)
    const [data, setData] = useState({
      service: '',
      date: '',        
      time: '',                                                                                 
      name: '',
      email: '',                                                                                
      phone: '',                                                                                
      notes: ''
    })                                                                                          
                  
    function next() { setStep(step + 1) }
    function back() { setStep(step - 1) }
    function update(fields) { setData({ ...data, ...fields }) }

console.log('Current step:', step)                                              
console.log('Form data:', data)

    return (                                                                                    
      <main className="booking-wrapper">
        <h1>TÍMABÓKUN</h1>                                                                      
        <div className="progress-bar">
          <div className="progress-fill" style={{ width: `${(step / 4) * 100}%` }}></div>
        </div>                                                                                  
        <p className="step-label">Skref {step} af 4</p>
                                                                                                
        {step === 1 && <Step1Service data={data} update={update} next={next} />}                
        {step === 2 && <Step2Date data={data} update={update} next={next} back={back} />}
        {step === 3 && <Step3Contact data={data} update={update} next={next} back={back} />}    
        {step === 4 && <Step4Review data={data} back={back} />}                                 
      </main>
    )                                                                                           
  }               

  export default Booking
