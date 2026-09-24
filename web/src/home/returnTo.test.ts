import { describe, expect, it } from 'vitest'
import { portalUser } from '../test/fixtures'
import { returnTarget } from './returnTo'

const APPLICATIONS = portalUser().applications

describe('returnTarget', () => {
  it('vuelve a la página exacta de una de sus aplicaciones', () => {
    const target = returnTarget('?volver=' + encodeURIComponent('http://localhost:5174/historico?mes=2026-09'), APPLICATIONS)

    expect(target?.application.code).toBe('listados')
    expect(target?.url).toBe('http://localhost:5174/historico?mes=2026-09')
  })

  it('no lleva a una web que no es de sus aplicaciones', () => {
    // Si no, un enlace al portal podría acabar, tras entrar, en una web
    // que se hace pasar por el AMPA.
    expect(returnTarget('?volver=' + encodeURIComponent('https://ampa-falso.example/entrar'), APPLICATIONS)).toBeNull()
  })

  it('tampoco a una aplicación de la suite en la que no entra', () => {
    expect(returnTarget('?volver=' + encodeURIComponent('http://localhost:5175/'), APPLICATIONS)).toBeNull()
  })

  it('sin "volver", o con algo que no es una dirección, no hay a dónde volver', () => {
    expect(returnTarget('', APPLICATIONS)).toBeNull()
    expect(returnTarget('?volver=javascript:alert(1)', APPLICATIONS)).toBeNull()
  })
})
