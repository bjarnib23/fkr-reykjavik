function Step4Review({ data, back }) {
    async function handleSubmit() {
      const res = await fetch('http://fkr-reykjavik.ddev.site/api/fkr/booking', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      if (res.ok) {
        alert('Bókun móttekin!')
      } else {
        alert('Eitthvað fór úrskeiðis, reyndu aftur.')
      }
    }

    return (
      <div>
        <h2>Yfirferð og athugasemdir</h2>
        <p>Farðu yfir upplýsingarnar áður en þú sendir inn.</p>

        <p><strong>Þjónusta:</strong> {data.service}</p>
        <p><strong>Dagsetning:</strong> {data.date}</p>
        <p><strong>Tími:</strong> {data.time}</p>
        <p><strong>Nafn:</strong> {data.name}</p>
        <p><strong>Tölvupóstur:</strong> {data.email}</p>
        <p><strong>Sími:</strong> {data.phone}</p>

        <textarea
          placeholder="Athugasemd (valkvæmt)"
          onChange={e => update({ notes: e.target.value })}
        />

        <div className="step-buttons">
          <button onClick={back}>Til baka</button>
          <button onClick={handleSubmit}>Senda bókunarbeiðni</button>
        </div>
      </div>
    )
  }

  export default Step4Review