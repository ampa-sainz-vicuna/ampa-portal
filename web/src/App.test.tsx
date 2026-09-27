import { fireEvent, screen, within } from '@testing-library/react'
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

  it('dice quién no ha entrado nunca: seguramente el correo del alta no es el de su cuenta', async () => {
    withAdmin(
      jsonResponse(200, [
        suiteUser(),
        suiteUser({ id: '2', name: 'Nueva', email: 'nueva@ampasainzvicuna.com', lastSeenAt: null }),
        // Desactivada: que no entre es lo normal, no se avisa.
        suiteUser({ id: '3', name: 'Antigua', email: 'antigua@ampasainzvicuna.com', active: false, lastSeenAt: null }),
      ]),
    )

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Permisos' }))

    const list = await screen.findByRole('list', { name: 'Personas' })
    expect(within(list).getAllByText('Nunca ha entrado')).toHaveLength(1)
    expect(within(list).getByText(/^tesoreria@ampasainzvicuna\.com · Última entrada: /)).toBeTruthy()
    expect(within(list).getByText('nueva@ampasainzvicuna.com')).toBeTruthy()
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

describe('Calendario', () => {
  const ADMIN = portalUser({ isAdmin: true })
  const COURSE = {
    schoolYear: '2026-2027',
    classesStart: '2026-09-08',
    classesEnd: '2027-06-18',
    periods: [{ from: '2026-10-12', to: '2026-10-12', kind: 'holiday', name: 'Día de la Hispanidad' }],
  }

  beforeEach(() => {
    // Un día fijo del curso 2026/27, para que el curso "actual" no dependa de cuándo se pasen los tests.
    vi.useFakeTimers({ now: new Date(2026, 8, 26, 12), toFake: ['Date'] })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  function withCalendar(loaded: unknown[]) {
    const fetchMock = vi.fn((url: string, init?: RequestInit) => {
      if (url === '/api/me') {
        return Promise.resolve(jsonResponse(200, ADMIN))
      }
      if (url === '/api/admin/calendario') {
        return Promise.resolve(jsonResponse(200, loaded))
      }
      if (url.startsWith('/api/admin/calendario/') && init?.method === 'PUT') {
        return Promise.resolve(jsonResponse(200, { schoolYear: url.split('/').pop(), ...JSON.parse(String(init.body)) }))
      }
      return Promise.reject(new Error(`Petición inesperada: ${url}`))
    })
    vi.stubGlobal('fetch', fetchMock)

    return fetchMock
  }

  it('la pestaña solo la tiene quien gestiona los permisos', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(200, portalUser())))

    renderInPortal(<App />)

    await screen.findByRole('tab', { name: 'Mis correos' })
    expect(screen.queryByRole('tab', { name: 'Calendario' })).toBeNull()
  })

  it('abre el curso actual con lo cargado y cuenta los días de clase', async () => {
    withCalendar([COURSE])

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Calendario' }))

    expect(await screen.findByRole('group', { name: 'Día de la Hispanidad' })).toBeTruthy()
    expect((screen.getByLabelText('Primer día de clase') as HTMLInputElement).value).toBe('2026-09-08')
    expect(screen.getByText(/^\d+ días de clase en el curso\.$/)).toBeTruthy()
  })

  it('apunta un día sin clase y guarda el curso entero', async () => {
    const fetchMock = withCalendar([COURSE])

    renderInPortal(<App />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Calendario' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Añadir día sin clase' }))

    const row = screen.getByRole('group', { name: 'Día sin clase 2' })
    await userEvent.type(within(row).getByLabelText('Nombre'), 'Todos los Santos')
    // Al poner "desde", "hasta" se rellena con el mismo día.
    fireEvent.change(within(row).getByLabelText('Desde'), { target: { value: '2026-11-02' } })
    await userEvent.click(screen.getByRole('button', { name: 'Guardar el curso 2026/2027' }))

    expect(await screen.findByText('Guardado. Las aplicaciones ya lo ven.')).toBeTruthy()
    const put = fetchMock.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(put?.[0]).toBe('/api/admin/calendario/2026-2027')
    expect(JSON.parse(String(put?.[1]?.body)).periods).toEqual([
      COURSE.periods[0],
      { from: '2026-11-02', to: '2026-11-02', kind: 'holiday', name: 'Todos los Santos' },
    ])
  })
})
