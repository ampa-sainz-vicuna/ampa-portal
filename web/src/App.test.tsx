import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { goTo } from './navigation'
import { CATALOG, jsonResponse, portalUser, renderInPortal, suiteUser } from './test/fixtures'

vi.mock('./navigation', () => ({ goTo: vi.fn() }))

beforeEach(() => {
  vi.mocked(goTo).mockClear()
})

afterEach(() => {
  window.history.replaceState(null, '', '/')
})

describe('El portal', () => {
  it('enseña una tarjeta por cada aplicación a la que puede ir', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)

    expect(await screen.findByRole('heading', { name: 'Fichajes' })).toBeTruthy()
    expect(screen.getByRole('heading', { name: 'Listados' })).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Listados' }).getAttribute('href')).toBe('http://localhost:5174')
  })

  it('la pestaña de permisos solo la tiene quien los gestiona', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)

    expect(await screen.findByRole('tab', { name: 'Mis correos' })).toBeTruthy()
    expect(screen.queryByRole('tab', { name: 'Permisos' })).toBeNull()
  })

  it('si se llega ya dentro con "volver", no vuelve sola: lo ofrece', async () => {
    // Si volviera sola y la aplicación siguiera sin ver la sesión, darían
    // vueltas la una a la otra para siempre.
    window.history.replaceState(null, '', '/?volver=' + encodeURIComponent('http://localhost:5174/'))
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)

    expect(await screen.findByText('Venías de Listados.')).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Volver' }).getAttribute('href')).toBe('http://localhost:5174/')
    expect(goTo).not.toHaveBeenCalled()
  })

  it('"volver" a una web que no es de sus aplicaciones ni se ofrece', async () => {
    window.history.replaceState(null, '', '/?volver=' + encodeURIComponent('https://ampa-falso.example/'))
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)

    await screen.findByRole('heading', { name: 'Fichajes' })
    expect(screen.queryByText(/Venías de/)).toBeNull()
  })
})

describe('Mis correos', () => {
  it('sin segundo correo, los avisos solo pueden ir al de la cuenta', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Mis correos' }))

    expect((screen.getByRole('radio', { name: 'Al segundo correo' }) as HTMLInputElement).disabled).toBe(true)
    expect((screen.getByRole('radio', { name: 'A los dos' }) as HTMLInputElement).disabled).toBe(true)
  })

  it('guarda el segundo correo y a dónde van los avisos', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse(200, portalUser()))
      .mockResolvedValueOnce(jsonResponse(200, portalUser({ secondaryEmail: 'presi@gmail.com', notify: 'secondary' })))
    vi.stubGlobal('fetch', fetchMock)

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Mis correos' }))
    await userEvent.type(screen.getByLabelText('Segundo correo'), 'presi@gmail.com')
    await userEvent.click(screen.getByRole('radio', { name: 'Al segundo correo' }))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Guardado.')).toBeTruthy()
    const [url, init] = fetchMock.mock.calls[1]
    expect(url).toBe('/api/me/contacto')
    expect(init.method).toBe('PUT')
    expect(JSON.parse(init.body)).toEqual({ secondaryEmail: 'presi@gmail.com', notify: 'secondary' })
  })

  it('si el servidor no lo acepta, dice por qué', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(200, portalUser()))
        .mockResolvedValueOnce(jsonResponse(422, { error: '"esto no" no es un correo válido.' })),
    )

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Mis correos' }))
    await userEvent.type(screen.getByLabelText('Segundo correo'), 'esto no')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('"esto no" no es un correo válido.')).toBeTruthy()
  })
})

describe('Permisos', () => {
  const ADMIN = portalUser({ isAdmin: true })

  function withAdmin(...more: Response[]) {
    // El segundo argumento no se usa aquí, pero el test lee luego qué se mandó.
    const fetchMock = vi.fn((url: string, _init?: RequestInit) => {
      if (url === '/api/me') {
        return Promise.resolve(jsonResponse(200, ADMIN))
      }
      if (url === '/api/admin/applications') {
        return Promise.resolve(jsonResponse(200, CATALOG))
      }
      if (url === '/api/admin/users') {
        const next = more.shift()
        if (next) {
          return Promise.resolve(next)
        }
      }
      return Promise.resolve(jsonResponse(200, []))
    })
    vi.stubGlobal('fetch', fetchMock)

    return fetchMock
  }

  it('lista a las personas con sus permisos y si están desactivadas', async () => {
    withAdmin(
      jsonResponse(200, [
        suiteUser(),
        suiteUser({ id: '2', name: 'Vocal antigua', email: 'vocal@ampasainzvicuna.com', active: false, grants: { listados: ['usuario'] } }),
      ]),
    )

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Permisos' }))

    const list = await screen.findByRole('list', { name: 'Personas' })
    expect(within(list).getByText('Tesorería')).toBeTruthy()
    expect(within(list).getByText('Fichajes: Administración')).toBeTruthy()
    expect(within(list).getByText('Desactivado')).toBeTruthy()
    expect(within(list).getByText('Listados: Usuario')).toBeTruthy()
  })

  it('da de alta a alguien con sus permisos y su segundo correo', async () => {
    const fetchMock = withAdmin(jsonResponse(200, []), jsonResponse(201, suiteUser()), jsonResponse(200, [suiteUser()]))

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Permisos' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Dar de alta' }))

    const dialog = await screen.findByRole('dialog', { name: 'Dar de alta' })
    await userEvent.type(within(dialog).getByLabelText(/Correo de la cuenta de Google/), 'tesoreria@ampasainzvicuna.com')
    await userEvent.type(within(dialog).getByLabelText(/Nombre/), 'Tesorería')
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Administración' }))
    await userEvent.type(within(dialog).getByLabelText('Segundo correo'), 'teso@gmail.com')
    await userEvent.click(within(dialog).getByRole('radio', { name: 'A los dos' }))
    await userEvent.click(within(dialog).getByRole('button', { name: 'Guardar' }))

    await screen.findByText('Tesorería')
    const post = fetchMock.mock.calls.find(([url, init]) => url === '/api/admin/users' && init?.method === 'POST')
    expect(JSON.parse(String(post?.[1]?.body))).toEqual({
      email: 'tesoreria@ampasainzvicuna.com',
      name: 'Tesorería',
      active: true,
      grants: { fichajes: ['admin'] },
      secondaryEmail: 'teso@gmail.com',
      notify: 'both',
    })
  })

  it('en su propia ficha no puede quitarse la gestión de permisos ni desactivarse', async () => {
    withAdmin(jsonResponse(200, [suiteUser({ email: ADMIN.email, name: ADMIN.name, grants: { portal: ['admin'] } })]))

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Permisos' }))
    await userEvent.click(await screen.findByText('Presidencia', { selector: '.MuiListItemText-primary' }))

    const dialog = await screen.findByRole('dialog')
    expect((within(dialog).getByRole('checkbox', { name: 'Gestiona los permisos' }) as HTMLInputElement).disabled).toBe(true)
    expect((within(dialog).getByRole('switch') as HTMLInputElement).disabled).toBe(true)
  })
})
