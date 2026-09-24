/**
 * Ir a otra página (una aplicación de la suite).
 *
 * En una función aparte solo para los tests: jsdom no sabe navegar, y
 * `window.location` no se deja sustituir. Los tests cambian este módulo con
 * `vi.mock('../navigation')`.
 */
export function goTo(url: string): void {
  window.location.assign(url)
}
