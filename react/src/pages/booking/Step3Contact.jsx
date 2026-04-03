  function Step3Contact({ data, update, next, back }) {
    return (
      <div>
        <h2>Upplýsingar um þig</h2>
        <p>Settu inn tengiliðaupplýsingar svo við getum haft samband og staðfest tímann.</p>

        <input
          placeholder="Nafn *"
          value={data.name}
          onChange={e => update({ name: e.target.value })}
        />
        <input
          placeholder="Tölvupóstur *"
          value={data.email}
          onChange={e => update({ email: e.target.value })}
        />
        <input
          placeholder="Sími *"
          value={data.phone}
          onChange={e => update({ phone: e.target.value })}
        />

        <div className="step-buttons">
          <button onClick={back}>Til baka</button>
          <button onClick={next} disabled={!data.name || !data.email || !data.phone}>Næsta skref</button>
        </div>
      </div>
    )
  }

  export default Step3Contact