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

/** Cargos de la junta. Los mismos textos que `BoardPosition` en la API. */
export type BoardPosition = 'president' | 'vice_president' | 'secretary' | 'treasurer' | 'member'

/** Un cargo de la junta con todos sus datos personales (`/api/admin/junta`, solo administradores). */
export interface BoardMember {
  id: string
  firstName: string
  lastName: string
  document: string
  address: string
  email: string
  phone: string
  position: BoardPosition
  /** AAAA-MM-DD. */
  startDate: string
  /** AAAA-MM-DD; null mientras sigue en el cargo. */
  endDate: string | null
  /** Persona de la plataforma asociada, si hay. */
  userId: string | null
}

/** La junta: quién está hoy y quién estuvo antes. */
export interface Board {
  active: BoardMember[]
  past: BoardMember[]
}

/** Lo que se manda al guardar los correos de alguien. */
export interface Contact {
  secondaryEmail: string | null
  notify: NotificationTarget
}

/** Cuántas preguntas de la ayuda hay de una aplicación (o de "general"). */
export interface HelpCount {
  application: string
  name: string
  entries: number
}

/** Lo que hay cargado en la ayuda de la suite (`/api/admin/ayuda`). */
export interface HelpSummary {
  /** ISO 8601. null: todavía no se ha cargado nunca. */
  loadedAt: string | null
  /** AAAA-MM-DD: cuándo se revisó el fichero (lo dice el fichero). */
  updatedAt: string | null
  notebookUrl: string | null
  contactEmail: string | null
  total: number
  /** Todas las aplicaciones de la suite, también las que no tienen ninguna. */
  byApplication: HelpCount[]
}
