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
  /** ISO 8601. null: todavía no ha entrado nunca (¿correo mal escrito al darle de alta?). */
  lastSeenAt: string | null
}

/** Una aplicación del catálogo con sus roles, para las casillas (`/api/admin/applications`). */
export interface CatalogApplication {
  code: string
  name: string
  roles: { code: string; name: string }[]
}

/** Festivo (ni clase ni se trabaja) o no lectivo (sin clase, pero laborable). */
export type DayKind = 'holiday' | 'non_school'

/** Uno o varios días seguidos sin clase. Fechas AAAA-MM-DD. */
export interface CalendarPeriod {
  from: string
  to: string
  kind: DayKind
  name: string
}

/** El calendario de un curso (`/api/admin/calendario`). */
export interface SchoolYear {
  /** "2026-2027". */
  schoolYear: string
  classesStart: string
  classesEnd: string
  periods: CalendarPeriod[]
}

/** Lo que se manda al guardar los correos de alguien. */
export interface Contact {
  secondaryEmail: string | null
  notify: NotificationTarget
}
