import Calendar from "./Calendar"

function Step2Date({ data, update, next, back }) {
    const times = ['09:00', '10:00', '11:00', '13:00', '14:00']

console.log('Selected date:', data.date)                                        
console.log('Selected time:', data.time) 

    return (
      <div>
        <h2>Veldu dag og tíma</h2>
        <p>Smelltu á lausa dagsetningu til að sjá tíma.</p>

        <Calendar selected={data.date} onSelect={date => update({ date })}/>

        {data.date && (
          <div>
            <p><strong>Lausir tímar — {data.date}</strong></p>
            <div className="time-options">
              {times.map(t => (
                <button
                  key={t}
                  className={data.time === t ? 'time-btn selected' : 'time-btn'}
                  onClick={() => update({ time: t })}
                >
                  {t}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="step-buttons">
          <button onClick={back}>Til baka</button>
          <button onClick={next} disabled={!data.date || !data.time}>Næsta skref</button>
        </div>
      </div>
    )
  }

  export default Step2Date