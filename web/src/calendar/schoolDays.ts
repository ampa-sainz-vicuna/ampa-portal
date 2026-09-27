import type { CalendarPeriod } from '../types'

/** "2026-2027" para cualquier día entre el 1/9/2026 y el 31/8/2027. */
export function schoolYearOf(day: Date): string {
  const year = day.getFullYear()
  const start = day.getMonth() >= 8 ? year : year - 1

  return `${start}-${start + 1}`
}

/**
 * Cuántos días de clase salen: de lunes a viernes entre el primero y el
 * último, sin los apuntados. Las mismas reglas que el portal
 * (SchoolYear::isSchoolDay); aquí solo para enseñarlo mientras se escribe.
 * null si las fechas aún no están completas.
 */
export function countSchoolDays(classesStart: string, classesEnd: string, periods: CalendarPeriod[]): number | null {
  if (!isDate(classesStart) || !isDate(classesEnd) || classesEnd < classesStart) {
    return null
  }

  const complete = periods.filter((period) => isDate(period.from) && isDate(period.to))
  let count = 0

  // En UTC: sin horario de verano, cada día dura 24 horas justas.
  for (let day = toUtc(classesStart); day <= toUtc(classesEnd); day = new Date(day.getTime() + 86_400_000)) {
    const weekday = day.getUTCDay()
    const text = day.toISOString().slice(0, 10)

    if (weekday !== 0 && weekday !== 6 && !complete.some((period) => text >= period.from && text <= period.to)) {
      count++
    }
  }

  return count
}

function isDate(value: string): boolean {
  return /^\d{4}-\d{2}-\d{2}$/.test(value)
}

function toUtc(value: string): Date {
  return new Date(`${value}T00:00:00Z`)
}
