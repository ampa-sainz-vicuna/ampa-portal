import type { SessionUser } from '@ampa/ui'

/** A dónde le llegan los avisos: la cuenta con la que entra, el segundo correo o los dos. */
export type NotificationTarget = 'primary' | 'secondary' | 'both'

/** Aplicación → roles: `{ "fichajes": ["admin"] }`. */
export type Grants = Record<string, string[]>

/** Una aplicación a la que puede ir quien ha entrado (una tarjeta). */
export interface ReachableApplication {
  code: string
  name: string
  url: string
  roles: string[]
}

/** Quién ha entrado en el portal (`GET /api/me`). */
export interface PortalUser extends SessionUser {
  secondaryEmail: string | null
  notify: NotificationTarget
  /** Si gestiona los permisos de la suite. */
  isAdmin: boolean
  applications: ReachableApplication[]
}

/** Una persona en la pantalla de permisos (`/api/admin/users`). */
export interface SuiteUser {
  id: string
  email: string
  name: string
  active: boolean
  grants: Grants
  secondaryEmail: string | null
  notify: NotificationTarget
}

/** Una aplicación del catálogo con sus roles, para las casillas (`/api/admin/applications`). */
export interface CatalogApplication {
  code: string
  name: string
  roles: { code: string; name: string }[]
}

/** Lo que se manda al guardar los correos de alguien. */
export interface Contact {
  secondaryEmail: string | null
  notify: NotificationTarget
}
