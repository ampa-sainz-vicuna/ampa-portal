import type { SuiteApp } from '@ampa/ui'

/**
 * El portal, para @ampa/ui. Es la única aplicación de la suite con `google`:
 * aquí se entra, y a las demás se llega ya dentro.
 *
 * Fuera de main.tsx para que los tests usen la misma configuración.
 */
export const PORTAL: SuiteApp = {
  name: 'Portal del AMPA',
  // El portal es esta misma página.
  portalUrl: window.location.origin,
  google: {
    clientId: import.meta.env.VITE_GOOGLE_CLIENT_ID,
    hostedDomain: import.meta.env.VITE_GOOGLE_HOSTED_DOMAIN,
  },
}
