import './Calendar.css'
import { useState } from 'react'                                                                   


const days = ['MÁN', 'ÞRI', 'MIÐ', 'FIM', 'FÖS', 'LAU', 'SUN']
const months = ['Janúar', 'Febrúar', 'Mars', 'Apríl', 'Maí', 'Júní', 'Júlí', 'Ágúst', 'September', 'Október', 'Nóvember', 'Desember']

function Calendar({ selected, onSelect }) {
    const [year, setYear] = useState(new Date().getFullYear())
    const [month, setMonth] = useState(new Date().getMonth())

    function getDaysInMonth() {
        const firstDay = new Date(year, month, 1).getDay()
        const totalDays = new Date(year, month + 1, 0).getDate()
        const offset = firstDay === 0 ? 6 : firstDay - 1
        const cells = []
        for (let i = 0; i < offset; i++) cells.push(null)
        for (let i = 1; i <= totalDays; i++) cells.push(i)
        return cells
    }

    function toDateStr(day) {
        return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    }

    function isPast(day) {
        if (!day) return false
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        const cellDate = new Date(year, month, day)
        return cellDate < today
    }

    function handleSelect(day) {
        if (!day || isPast(day)) return
        onSelect(toDateStr(day))
    }

    function isSelected(day) {
        if (!day || !selected) return false
        return toDateStr(day) === selected
    }

    return (
      <div className="calendar">
        <div className="calendar-header">
          <button
            onClick={() => month === 0 ? (setMonth(11), setYear(year - 1)) : setMonth(month - 1)}
            disabled={year === new Date().getFullYear() && month === new Date().getMonth()}
          >‹</button>
          <strong>{months[month]} {year}</strong>
          <button onClick={() => month === 11 ? (setMonth(0), setYear(year + 1)) : setMonth(month + 1)}>›</button>
        </div>
        <div className="calendar-grid">
          {days.map(d => <div key={d} className="calendar-day-label">{d}</div>)}
          {getDaysInMonth().map((day, i) => (
            <div
              key={i}
              className={`calendar-cell ${!day ? 'empty' : ''} ${isSelected(day) ? 'selected' : ''}`}
              onClick={() => handleSelect(day)}
              style={isPast(day) ? { color: '#bbb', textDecoration: 'line-through', pointerEvents: 'none', cursor: 'default' } : {}}
            >
              {day}
            </div>
          ))}
        </div>
      </div>
    )

}

export default Calendar
