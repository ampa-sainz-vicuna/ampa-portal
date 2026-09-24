import { cleanup, configure } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

// findBy… espera 1 s por defecto. Con todos los tests a la vez en Docker sobre
// Windows, lo que se carga aparte (la administración, "Mis jornadas") puede
// tardar más, y el test fallaría sin que la aplicación haga nada mal.
configure({ asyncUtilTimeout: 5000 })

// userEvent, al mover el foco, a veces pone el propio `document` como
// relatedTarget. La trampa de foco de los diálogos de MUI lo guarda para
// devolverle el foco al cerrarse, y el `document` de jsdom no tiene focus(): el
// test falla al desmontar. En un navegador relatedTarget es siempre un elemento
// o null, así que esto solo existe para los tests.
if (!('focus' in Document.prototype)) {
  Object.defineProperty(Document.prototype, 'focus', { value: () => {}, configurable: true })
}

// Sin "globals" de Vitest, Testing Library no desmonta solo entre tests.
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.useRealTimers()
  sessionStorage.clear()
})
