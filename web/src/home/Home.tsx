import { AppShell, useAuth } from '@ampa/ui'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import CircularProgress from '@mui/material/CircularProgress'
import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'
import { useEffect, useState } from 'react'
import { MyEmails } from '../contact/MyEmails'
import { goTo } from '../navigation'
import { Permissions } from '../permissions/Permissions'
import type { PortalUser } from '../types'
import { Applications } from './Applications'
import { returnTarget } from './returnTo'

type Section = 'applications' | 'emails' | 'permissions'

interface Props {
  user: PortalUser
  onUnauthorized: () => void
  onSignOut: () => void
}

/**
 * Dentro del portal: a qué aplicaciones puede ir, sus correos y, si gestiona
 * los permisos, la pantalla de permisos.
 */
export function Home({ user, onUnauthorized, onSignOut }: Props) {
  const { epoch } = useAuth()
  const [section, setSection] = useState<Section>('applications')
  const [me, setMe] = useState(user)

  // Volver a la aplicación que mandó aquí, pero solo AUTOMÁTICAMENTE si se
  // acaba de entrar en esta misma visita (epoch > 0: se ha pasado por el botón
  // de Google). Si se llega con la sesión ya abierta y un "volver", algo no
  // cuadra (la aplicación no ve la cookie): volver sola daría vueltas entre
  // las dos para siempre. Entonces se ofrece un botón y se deja decidir.
  const target = returnTarget(window.location.search, me.applications)
  const returnsNow = target !== null && epoch > 0
  const returnUrl = returnsNow ? target.url : null

  useEffect(() => {
    if (returnUrl !== null) {
      goTo(returnUrl)
    }
  }, [returnUrl])

  if (returnsNow && target !== null) {
    return (
      <AppShell userName={me.name}>
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2, py: 10 }}>
          <CircularProgress aria-label={`Volviendo a ${target.application.name}`} />
          <Button href={target.url}>Volver a {target.application.name}</Button>
        </Box>
      </AppShell>
    )
  }

  const tabs = (
    <Tabs value={section} onChange={(_, value: Section) => setSection(value)} variant="scrollable" allowScrollButtonsMobile>
      <Tab value="applications" label="Aplicaciones" />
      <Tab value="emails" label="Mis correos" />
      {me.isAdmin && <Tab value="permissions" label="Permisos" />}
    </Tabs>
  )

  return (
    <AppShell userName={me.name} onSignOut={onSignOut} tabs={tabs} maxWidth={section === 'permissions' ? 'md' : 'sm'}>
      {target !== null && (
        <Alert
          severity="info"
          sx={{ mb: 3 }}
          action={
            <Button color="inherit" size="small" href={target.url}>
              Volver
            </Button>
          }
        >
          Venías de {target.application.name}.
        </Alert>
      )}

      {section === 'applications' && <Applications applications={me.applications} />}
      {section === 'emails' && <MyEmails user={me} onSaved={setMe} onUnauthorized={onUnauthorized} />}
      {section === 'permissions' && me.isAdmin && <Permissions me={me} onUnauthorized={onUnauthorized} />}
    </AppShell>
  )
}
