import type { BoardPosition } from '../types'

/** Los cargos, en el orden de la pantalla, con el nombre que se ve. */
export const POSITIONS: { value: BoardPosition; label: string }[] = [
  { value: 'president', label: 'Presidencia' },
  { value: 'vice_president', label: 'Vicepresidencia' },
  { value: 'secretary', label: 'Secretaría' },
  { value: 'treasurer', label: 'Tesorería' },
  { value: 'member', label: 'Vocal' },
]

export function positionLabel(position: BoardPosition): string {
  return POSITIONS.find((candidate) => candidate.value === position)?.label ?? position
}

const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE'

/** Mayúsculas, sin espacios, puntos ni guiones: lo mismo que hace el servidor. */
export function normalizeDocument(value: string): string {
  return value.replace(/[\s.-]+/g, '').toUpperCase()
}

/** DNI o NIE con la letra de control bien. El servidor lo vuelve a comprobar. */
export function isValidDocument(value: string): boolean {
  const normalized = normalizeDocument(value)
  if (!/^(\d{8}|[XYZ]\d{7})[A-Z]$/.test(normalized)) {
    return false
  }
  const digits = normalized.slice(0, 8).replace('X', '0').replace('Y', '1').replace('Z', '2')

  return LETTERS[Number(digits) % 23] === normalized.slice(-1)
}

/** 9 cifras, o prefijo internacional con "+" (8 a 15 cifras). */
export function isValidPhone(value: string): boolean {
  return /^(\d{9}|\+\d{8,15})$/.test(value.replace(/[\s.()-]+/g, ''))
}

export function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

/** AAAA-MM-DD → DD/MM/AAAA, para leerlo. */
export function formatDate(value: string): string {
  const [year, month, day] = value.split('-')

  return `${day}/${month}/${year}`
}
