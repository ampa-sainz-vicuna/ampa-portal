import { describe, expect, it } from 'vitest'
import { describeLastSeen } from './lastSeen'

describe('la última entrada, dicha como se dice', () => {
  // Sábado 26/09/2026 a las 09:00 de Madrid.
  const now = new Date('2026-09-26T07:00:00Z')

  it('hoy, ayer y hace unos días, contando por días de Madrid', () => {
    expect(describeLastSeen('2026-09-26T06:30:00+00:00', now)).toBe('hoy')
    // 23:30 de Madrid del viernes: en UTC todavía es viernes, pero da igual.
    expect(describeLastSeen('2026-09-25T21:30:00+00:00', now)).toBe('ayer')
    // 00:30 de Madrid del sábado es el día 25 en UTC: y es hoy.
    expect(describeLastSeen('2026-09-25T22:30:00+00:00', now)).toBe('hoy')
    expect(describeLastSeen('2026-09-21T10:00:00+02:00', now)).toBe('hace 5 días')
  })

  it('pasado un mes, la fecha', () => {
    expect(describeLastSeen('2026-08-03T10:00:00+02:00', now)).toBe('el 3 de agosto de 2026')
  })
})
