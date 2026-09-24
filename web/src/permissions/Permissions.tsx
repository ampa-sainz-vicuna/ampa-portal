import { ApiError, apiRequest, messageOf } from '@ampa/ui'
import PersonAddRounded from '@mui/icons-material/PersonAddRounded'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Chip from '@mui/material/Chip'
import CircularProgress from '@mui/material/CircularProgress'
import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import { useCallback, useEffect, useState } from 'react'
import type { CatalogApplication, PortalUser, SuiteUser } from '../types'
import { UserDialog } from './UserDialog'

interface Props {
  me: PortalUser
  onUnauthorized: () => void
}

type Editing = { kind: 'new' } | { kind: 'existing'; user: SuiteUser } | null

/**
 * Quién entra en qué aplicación de la suite y con qué rol. Solo la ve quien
 * gestiona los permisos (el servidor lo impone igualmente).
 *
 * Una lista por nombre; al pulsar a alguien se abre su ficha. Nadie se borra:
 * se desactiva, y con eso deja de entrar en todo al momento.
 */
export function Permissions({ me, onUnauthorized }: Props) {
  const [users, setUsers] = useState<SuiteUser[] | null>(null)
  const [applications, setApplications] = useState<CatalogApplication[]>([])
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<Editing>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    let cancelled = false

    Promise.all([
      apiRequest<SuiteUser[]>('/api/admin/users'),
      apiRequest<CatalogApplication[]>('/api/admin/applications'),
    ])
      .then(([loadedUsers, loadedApplications]) => {
        if (!cancelled) {
          setUsers(loadedUsers)
          setApplications(loadedApplications)
          setError(null)
        }
      })
      .catch((e: unknown) => {
        if (cancelled) {
          return
        }
        if (e instanceof ApiError && e.status === 401) {
          onUnauthorized()
          return
        }
        setError(messageOf(e))
      })

    return () => {
      cancelled = true
    }
  }, [attempt, onUnauthorized])

  const reload = useCallback(() => setAttempt((n) => n + 1), [])

  const roleName = (application: string, role: string): string => {
    const found = applications.find((candidate) => candidate.code === application)
    const roleLabel = found?.roles.find((candidate) => candidate.code === role)?.name ?? role

    return `${found?.name ?? application}: ${roleLabel}`
  }

  if (error !== null) {
    return (
      <Alert
        severity="error"
        action={
          <Button color="inherit" size="small" onClick={reload}>
            Reintentar
          </Button>
        }
      >
        {error}
      </Alert>
    )
  }

  if (users === null) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
        <CircularProgress aria-label="Cargando" />
      </Box>
    )
  }

  return (
    <Stack spacing={2}>
      <Button
        variant="contained"
        startIcon={<PersonAddRounded />}
        onClick={() => setEditing({ kind: 'new' })}
        sx={{ alignSelf: 'flex-start' }}
      >
        Dar de alta
      </Button>

      <List aria-label="Personas">
        {users.map((user) => (
          <ListItemButton key={user.id} onClick={() => setEditing({ kind: 'existing', user })} divider>
            <ListItemText
              primary={user.name}
              secondary={user.email}
              slotProps={{ primary: { sx: { color: user.active ? 'text.primary' : 'text.disabled' } } }}
            />
            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', justifyContent: 'flex-end', rowGap: 1, ml: 2 }}>
              {!user.active && <Chip size="small" label="Desactivado" />}
              {Object.entries(user.grants).flatMap(([application, roles]) =>
                roles.map((role) => (
                  <Chip key={`${application}-${role}`} size="small" variant="outlined" label={roleName(application, role)} />
                )),
              )}
            </Stack>
          </ListItemButton>
        ))}
      </List>

      {editing !== null && (
        <UserDialog
          user={editing.kind === 'existing' ? editing.user : null}
          applications={applications}
          isMe={editing.kind === 'existing' && editing.user.email === me.email}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            reload()
          }}
          onUnauthorized={onUnauthorized}
        />
      )}
    </Stack>
  )
}
