/**
 * Las iniciales de un nombre para el círculo de la lista de *Permisos*: las
 * dos primeras palabras que empiezan por mayúscula («Vocal de Prueba» → «VP»,
 * sin el «de»), o la primera letra si solo hay una («Tesorería» → «T»).
 */
export function initials(name: string): string {
  const words = name.trim().split(/\s+/).filter((word) => word !== '')
  const capitalised = words.filter((word) => word[0] !== word[0].toLocaleLowerCase('es'))
  const chosen = (capitalised.length > 0 ? capitalised : words).slice(0, 2)

  return chosen.map((word) => word[0].toLocaleUpperCase('es')).join('')
}
