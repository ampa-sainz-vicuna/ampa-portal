import { describe, expect, it } from 'vitest'
import { initials } from './initials'

describe('Las iniciales de la lista de permisos', () => {
  it('son las de las dos primeras palabras con mayúscula, sin "de"', () => {
    expect(initials('Vocal de Prueba')).toBe('VP')
    expect(initials('María José de la Fuente')).toBe('MJ')
  })

  it('con una sola palabra, su primera letra', () => {
    expect(initials('Tesorería')).toBe('T')
  })

  it('un nombre todo en minúsculas también las tiene', () => {
    expect(initials('  alberto  garcía ')).toBe('AG')
  })

  it('las letras con tilde se quedan como son', () => {
    expect(initials('Álvaro Íñiguez')).toBe('ÁÍ')
  })
})
