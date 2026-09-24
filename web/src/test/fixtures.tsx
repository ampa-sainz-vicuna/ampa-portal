import { SuiteRoot } from '@ampa/ui'
import { render, type RenderResult } from '@testing-library/react'
import type { ReactNode } from 'react'
import { PORTAL } from '../suiteApp'
import type { CatalogApplication, PortalUser, SuiteUser } from '../types'

/** Quien ha entrado, como lo devuelve `GET /api/me`. */
export function portalUser(overrides: Partial<PortalUser> = {}): PortalUser {
  return {
    name: 'Presidencia',
    email: 'presidencia@ampasainzvicuna.com',
    secondaryEmail: null,
    notify: 'primary',
    isAdmin: false,
    applications: [
      { code: 'fichajes', name: 'Fichajes', url: 'http://localhost:5173', roles: ['admin'] },
      { code: 'listados', name: 'Listados', url: 'http://localhost:5174', roles: ['usuario'] },
    ],
    ...overrides,
  }
}

/** Una persona en la pantalla de permisos. */
export function suiteUser(overrides: Partial<SuiteUser> = {}): SuiteUser {
  return {
    id: '0192a4b0-0000-7000-8000-000000000001',
    email: 'tesoreria@ampasainzvicuna.com',
    name: 'Tesorería',
    active: true,
    grants: { fichajes: ['admin'] },
    secondaryEmail: null,
    notify: 'primary',
    ...overrides,
  }
}

export const CATALOG: CatalogApplication[] = [
  {
    code: 'fichajes',
    name: 'Fichajes',
    roles: [
      { code: 'empleado', name: 'Empleado' },
      { code: 'admin', name: 'Administración' },
    ],
  },
  { code: 'listados', name: 'Listados', roles: [{ code: 'usuario', name: 'Usuario' }] },
  { code: 'portal', name: 'Portal', roles: [{ code: 'admin', name: 'Gestiona los permisos' }] },
]

export function renderInPortal(ui: ReactNode): RenderResult {
  return render(<SuiteRoot app={PORTAL}>{ui}</SuiteRoot>)
}

export function jsonResponse(status: number, body: unknown): Response {
  if (status === 204) {
    return new Response(null, { status })
  }

  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
