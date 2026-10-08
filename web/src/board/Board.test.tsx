import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import App from '../App'
import { jsonResponse, portalUser, renderInPortal, suiteUser } from '../test/fixtures'
import type { Board, BoardMember } from '../types'

vi.mock('../navigation', () => ({ goTo: vi.fn() }))

const ADMIN = portalUser({ isAdmin: true })

function member(overrides: Partial<BoardMember> = {}): BoardMember {
  return {
    id: '0192a4b0-0000-7000-8000-0000000000a1',
    firstName: 'Ana',
    lastName: 'Prueba Ejemplo',
    document: '12345678Z',
    address: 'Calle Falsa 123',
    email: 'ana@example.com',
    phone: '600123456',
    position: 'secretary',
    startDate: '2026-01-15',
    endDate: null,
    userId: null,
    ...overrides,
  }
}

const BOARD: Board = {
  active: [member()],
  past: [member({ id: '0192a4b0-0000-7000-8000-0000000000a2', firstName: 'Luis', position: 'treasurer', endDate: '2025-12-31' })],
}

function withBoard(me = ADMIN) {
  const fetchMock = vi.fn((url: string) => {
    if (url === '/api/me') return Promise.resolve(jsonResponse(200, me))
    if (url === '/api/admin/junta') return Promise.resolve(jsonResponse(200, BOARD))
    if (url === '/api/admin/users') return Promise.resolve(jsonResponse(200, [suiteUser()]))
    return Promise.reject(new Error(`Petición inesperada: ${url}`))
  })
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

describe('Junta', () => {
  it('la pestaña solo la ve quien gestiona los permisos', async () => {
    withBoard(portalUser())

    renderInPortal(<App />)

    await screen.findByRole('tab', { name: 'Mis correos' })
    expect(screen.queryByRole('tab', { name: 'Junta' })).toBeNull()
  })

  it('enseña los cargos activos y, aparte, los anteriores', async () => {
    withBoard()
    renderInPortal(<App />)

    await userEvent.click(await screen.findByRole('tab', { name: 'Junta' }))

    const active = await screen.findByRole('list', { name: 'Cargos activos' })
    expect(within(active).getByText('Secretaría · Ana Prueba Ejemplo')).toBeTruthy()
    const past = screen.getByRole('list', { name: 'Cargos anteriores' })
    expect(within(past).getByText('Tesorería · Luis Prueba Ejemplo')).toBeTruthy()
  })

  it('no manda un DNI con la letra mal: avisa en el formulario', async () => {
    const fetchMock = withBoard()
    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Junta' }))
    await userEvent.click(await screen.findByText('Secretaría · Ana Prueba Ejemplo'))

    const dialog = await screen.findByRole('dialog')
    const document = within(dialog).getByLabelText(/DNI o NIE/)
    await userEvent.clear(document)
    await userEvent.type(document, '12345678A')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Guardar' }))

    expect(await within(dialog).findByText(/DNI o NIE no válido/)).toBeTruthy()
    expect((fetchMock.mock.calls as unknown[][]).some(([, init]) => (init as RequestInit | undefined)?.method === 'PUT')).toBe(false)
  })

  it('pide confirmación antes de dar de baja', async () => {
    const fetchMock = withBoard()
    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Junta' }))
    await userEvent.click(await screen.findByText('Secretaría · Ana Prueba Ejemplo'))

    await userEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Dar de baja' }))

    expect(await screen.findByText(/deja el cargo con fecha de hoy/)).toBeTruthy()
    expect(fetchMock.mock.calls.some(([url]) => String(url).endsWith('/baja'))).toBe(false)
  })
})
