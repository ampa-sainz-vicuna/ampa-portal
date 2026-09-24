import type { ReachableApplication } from '../types'

/**
 * A dónde volver después de entrar.
 *
 * Una aplicación que recibe a alguien sin sesión lo manda aquí con
 * `?volver=<su dirección>` (lo hace `SessionGate` de @ampa/ui). Solo se hace
 * caso si esa dirección es de una de SUS aplicaciones: si no, cualquiera
 * podría mandar un enlace al portal que, tras entrar, llevara a otra web
 * haciéndose pasar por el AMPA.
 *
 * @returns la aplicación y la dirección exacta a la que volver, o null
 */
export function returnTarget(
  search: string,
  applications: ReachableApplication[],
): { application: ReachableApplication; url: string } | null {
  const requested = new URLSearchParams(search).get('volver')
  if (requested === null) {
    return null
  }

  let origin: string
  try {
    origin = new URL(requested).origin
  } catch {
    return null
  }

  const application = applications.find((candidate) => originOf(candidate.url) === origin)

  return application ? { application, url: requested } : null
}

function originOf(url: string): string | null {
  try {
    return new URL(url).origin
  } catch {
    return null
  }
}
