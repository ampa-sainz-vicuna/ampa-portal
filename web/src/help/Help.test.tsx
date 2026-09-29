import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import App from '../App'
import { jsonResponse, portalUser, renderInPortal } from '../test/fixtures'
import type { HelpSummary } from '../types'

vi.mock('../navigation', () => ({ goTo: vi.fn() }))

const ADMIN = portalUser({ isAdmin: true })

const EMPTY: HelpSummary = {
  loadedAt: null,
  updatedAt: null,
  notebookUrl: null,
  contactEmail: null,
  total: 0,
  byApplication: [
    { application: 'general', name: 'General', entries: 0 },
    { application: 'facturacion', name: 'Facturación', entries: 0 },
  ],
}

const LOADED: HelpSummary = {
  loadedAt: '2026-09-28T18:25:00+00:00',
  updatedAt: '2026-09-28',
  notebookUrl: 'https://notebooklm.example.com/notebook/prueba',
  contactEmail: 'ayuda@example.com',
  total: 3,
  byApplication: [
    { application: 'general', name: 'General', entries: 3 },
    { application: 'facturacion', name: 'Facturación', entries: 0 },
  ],
}

const FILE = { version: 1, updatedAt: '2026-09-28', entries: [{ id: 'a', application: 'general', question: 'P', answer: 'R' }] }

function withHelp(summary: HelpSummary, put: (body: unknown) => Response = () => jsonResponse(200, LOADED)) {
  const fetchMock = vi.fn((url: string, init?: RequestInit) => {
    if (url === '/api/me') {
      return Promise.resolve(jsonResponse(200, ADMIN))
    }
    if (url === '/api/admin/ayuda' && init?.method === 'PUT') {
      return Promise.resolve(put(JSON.parse(String(init.body))))
    }
    if (url === '/api/admin/ayuda') {
      return Promise.resolve(jsonResponse(200, summary))
    }
    return Promise.reject(new Error(`Petición inesperada: ${url}`))
  })
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

async function openHelp() {
  renderInPortal(<App />)
  await userEvent.click(await screen.findByRole('tab', { name: 'Ayuda' }))
}

function jsonFile(content: string, name = 'faq.json'): File {
  return new File([content], name, { type: 'application/json' })
}

describe('Ayuda', () => {
  it('la pestaña solo la tiene quien gestiona los permisos', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)

    await screen.findByRole('tab', { name: 'Mis correos' })
    expect(screen.queryByRole('tab', { name: 'Ayuda' })).toBeNull()
  })

  it('sin nada cargado lo dice', async () => {
    withHelp(EMPTY)

    await openHelp()

    expect(await screen.findByText(/Todavía no se ha cargado ninguna ayuda/)).toBeTruthy()
  })

  it('enseña cuándo se cargó, cuántas hay de cada aplicación y el cuaderno', async () => {
    withHelp(LOADED)

    await openHelp()

    expect(await screen.findByText(/Cargada el 28 de septiembre de 2026 a las 20:25/)).toBeTruthy()
    expect(screen.getByText('3 preguntas en total.')).toBeTruthy()
    const counts = screen.getByLabelText('Preguntas por aplicación')
    expect(within(counts).getByText('Facturación')).toBeTruthy()
    expect(within(counts).getByText('ninguna')).toBeTruthy()
    expect(screen.getByRole('link', { name: 'abrir en NotebookLM' }).getAttribute('href')).toBe(LOADED.notebookUrl)
  })

  it('carga el fichero tal cual y enseña el resultado', async () => {
    const fetchMock = withHelp(EMPTY)

    await openHelp()
    await userEvent.upload(await screen.findByLabelText('Cargar el fichero de preguntas'), jsonFile(JSON.stringify(FILE)))

    expect(await screen.findByText('Cargadas 3 preguntas. Las aplicaciones ya las ven.')).toBeTruthy()
    expect(screen.getByText('3 preguntas en total.')).toBeTruthy()
    const put = fetchMock.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(JSON.parse(String(put?.[1]?.body))).toEqual(FILE)
  })

  it('si el servidor no lo acepta, dice por qué', async () => {
    withHelp(LOADED, () => jsonResponse(422, { error: '"a": la aplicación "cocina" no existe.' }))

    await openHelp()
    await userEvent.upload(await screen.findByLabelText('Cargar el fichero de preguntas'), jsonFile(JSON.stringify(FILE)))

    expect(await screen.findByText('"a": la aplicación "cocina" no existe.')).toBeTruthy()
  })

  it('un fichero que no es JSON ni se manda', async () => {
    const fetchMock = withHelp(EMPTY)

    await openHelp()
    await userEvent.upload(await screen.findByLabelText('Cargar el fichero de preguntas'), jsonFile('{"version": 1,', 'roto.json'))

    expect(await screen.findByText('«roto.json» no es un fichero JSON válido.')).toBeTruthy()
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'PUT')).toBe(false)
  })
})
