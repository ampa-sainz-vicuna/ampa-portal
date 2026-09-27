import { describe, expect, it } from 'vitest'
import { countSchoolDays, schoolYearOf } from './schoolDays'

describe('Los días de clase', () => {
  it('cuenta de lunes a viernes sin los días apuntados', () => {
    // Octubre de 2026: 22 días de lunes a viernes, menos el 12.
    expect(
      countSchoolDays('2026-10-01', '2026-10-31', [{ from: '2026-10-12', to: '2026-10-12', kind: 'holiday', name: 'Hispanidad' }]),
    ).toBe(21)
  })

  it('un periodo a medio escribir no cuenta todavía', () => {
    expect(countSchoolDays('2026-10-01', '2026-10-31', [{ from: '2026-10-12', to: '', kind: 'holiday', name: '' }])).toBe(22)
  })

  it('sin las dos fechas no hay cuenta', () => {
    expect(countSchoolDays('2026-09-08', '', [])).toBeNull()
  })

  it('el curso empieza el 1 de septiembre', () => {
    expect(schoolYearOf(new Date(2026, 8, 1))).toBe('2026-2027')
    expect(schoolYearOf(new Date(2026, 7, 31))).toBe('2025-2026')
  })
})
