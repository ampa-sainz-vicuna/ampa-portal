import { describe, expect, it } from 'vitest'
import { formatDate, isValidDocument, isValidPhone } from './validation'

describe('Validación de la junta', () => {
  it('comprueba la letra del DNI y del NIE', () => {
    expect(isValidDocument('12345678Z')).toBe(true)
    expect(isValidDocument('12.345.678-z')).toBe(true)
    expect(isValidDocument('X1234567L')).toBe(true)
    expect(isValidDocument('12345678A')).toBe(false)
    expect(isValidDocument('X1234567A')).toBe(false)
    expect(isValidDocument('')).toBe(false)
  })

  it('admite 9 cifras o prefijo internacional', () => {
    expect(isValidPhone('600 12 34 56')).toBe(true)
    expect(isValidPhone('+34 600123456')).toBe(true)
    expect(isValidPhone('60012345')).toBe(false)
    expect(isValidPhone('abc')).toBe(false)
  })

  it('da la vuelta a las fechas para leerlas', () => {
    expect(formatDate('2026-09-30')).toBe('30/09/2026')
  })
})
