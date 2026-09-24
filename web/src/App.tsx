import { SessionGate } from '@ampa/ui'
import { Home } from './home/Home'
import type { PortalUser } from './types'

/**
 * La puerta la pone @ampa/ui: sin sesión, el botón de Google; con sesión,
 * lo de dentro.
 */
export default function App() {
  return (
    <SessionGate<PortalUser>>
      {({ user, onUnauthorized, signOut }) => <Home user={user} onUnauthorized={onUnauthorized} onSignOut={signOut} />}
    </SessionGate>
  )
}
