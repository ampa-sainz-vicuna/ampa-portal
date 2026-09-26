/**
 * La última entrada de alguien, como se dice: "hoy", "ayer", "hace 5 días".
 * Pasado un mes, la fecha. Se cuenta por días de Madrid, no por horas: a las
 * 9 de la mañana, algo de anoche a las 23:00 es "ayer", no "hoy".
 *
 * El portal la apunta como mucho una vez por hora (User::recordVisit), así que
 * no tiene sentido afinar más que "hoy".
 */
export function describeLastSeen(isoInstant: string, now: Date = new Date()): string {
  const seen = new Date(isoInstant)
  const days = Math.round((madridDay(now) - madridDay(seen)) / 86_400_000)

  if (days <= 0) {
    return 'hoy'
  }
  if (days === 1) {
    return 'ayer'
  }
  if (days < 31) {
    return `hace ${days} días`
  }
  return `el ${new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Madrid' }).format(seen)}`
}

/** Medianoche UTC del día de Madrid de ese instante: para restar días sin que la zona del navegador moleste. */
function madridDay(instant: Date): number {
  // en-CA formatea las fechas como AAAA-MM-DD.
  const day = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Europe/Madrid' }).format(instant)
  return Date.parse(`${day}T00:00:00Z`)
}
