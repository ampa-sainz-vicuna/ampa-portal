import { ApiError, apiRequest, CardTitle, messageOf } from '@ampa/ui'
import Diversity3Outlined from '@mui/icons-material/Diversity3Outlined'
import PersonAddRounded from '@mui/icons-material/PersonAddRounded'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import CircularProgress from '@mui/material/CircularProgress'
import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { useCallback, useEffect, useState } from 'react'
import type { Board as BoardData, BoardMember, SuiteUser } from '../types'
import { BoardDialog } from './BoardDialog'
import { formatDate, positionLabel } from './validation'

interface Props {
  onUnauthorized: () => void
}

type Editing = { kind: 'new' } | { kind: 'existing'; member: BoardMember } | null

/**
 * Los cargos de la junta del AMPA: quién está hoy en cada uno y, aparte, quién
 * estuvo antes. Solo la ve quien gestiona los permisos (el servidor lo impone
 * igualmente: lleva DNI, dirección y teléfono).
 *
 * Al pulsar un cargo se abre su ficha. Nadie se borra: «Dar de baja» le pone
 * fecha de fin y pasa a anteriores.
 */
export function Board({ onUnauthorized }: Props) {
  const [board, setBoard] = useState<BoardData | null>(null)
  const [users, setUsers] = useState<SuiteUser[]>([])
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<Editing>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    let cancelled = false

    Promise.all([apiRequest<BoardData>('/api/admin/junta'), apiRequest<SuiteUser[]>('/api/admin/users')])
      .then(([loadedBoard, loadedUsers]) => {
        if (!cancelled) {
          setBoard(loadedBoard)
          setUsers(loadedUsers)
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

  if (board === null) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
        <CircularProgress aria-label="Cargando" />
      </Box>
    )
  }

  const userName = (id: string | null): string | null => (id === null ? null : (users.find((user) => user.id === id)?.name ?? 'Usuario desconocido'))

  return (
    <Card>
      <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
        <CardTitle
          icon={<Diversity3Outlined />}
          subtitle={board.active.length === 1 ? '1 cargo activo' : `${board.active.length} cargos activos`}
          action={
            <Button variant="outlined" startIcon={<PersonAddRounded />} onClick={() => setEditing({ kind: 'new' })}>
              Nuevo cargo
            </Button>
          }
        >
          Junta
        </CardTitle>

        {board.active.length === 0 ? (
          <Typography color="text.secondary" sx={{ mt: 2 }}>
            Todavía no hay nadie en la junta.
          </Typography>
        ) : (
          <List aria-label="Cargos activos" sx={{ mt: 1, pb: 0 }}>
            {board.active.map((member) => (
              <MemberRow
                key={member.id}
                member={member}
                secondary={`${member.email} · ${member.phone}`}
                linkedUser={userName(member.userId)}
                onClick={() => setEditing({ kind: 'existing', member })}
              />
            ))}
          </List>
        )}

        {board.past.length > 0 && (
          <>
            <Typography variant="subtitle1" component="h2" sx={{ mt: 4, fontWeight: 500 }}>
              Anteriores
            </Typography>
            <List aria-label="Cargos anteriores" sx={{ pb: 0 }}>
              {board.past.map((member) => (
                <MemberRow
                  key={member.id}
                  member={member}
                  secondary={`${formatDate(member.startDate)} – ${formatDate(member.endDate ?? member.startDate)}`}
                  linkedUser={userName(member.userId)}
                  onClick={() => setEditing({ kind: 'existing', member })}
                  muted
                />
              ))}
            </List>
          </>
        )}
      </CardContent>

      {editing !== null && (
        <BoardDialog
          member={editing.kind === 'existing' ? editing.member : null}
          users={users}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            reload()
          }}
          onUnauthorized={onUnauthorized}
        />
      )}
    </Card>
  )
}

interface RowProps {
  member: BoardMember
  secondary: string
  linkedUser: string | null
  onClick: () => void
  muted?: boolean
}

/** Una fila: cargo y nombre; debajo, el contacto o las fechas; y el usuario asociado. */
function MemberRow({ member, secondary, linkedUser, onClick, muted = false }: RowProps) {
  return (
    <ListItemButton onClick={onClick} divider sx={{ '&:last-of-type': { borderBottom: 0 }, flexWrap: 'wrap', gap: 1 }}>
      <ListItemText
        primary={`${positionLabel(member.position)} · ${member.firstName} ${member.lastName}`}
        secondary={secondary}
        slotProps={{ primary: { sx: { color: muted ? 'text.secondary' : 'text.primary' } } }}
        sx={{ flex: '1 1 16rem', minWidth: 0, overflowWrap: 'anywhere' }}
      />
      {linkedUser !== null && (
        <Stack direction="row" sx={{ flexShrink: 0 }}>
          <Chip size="small" variant="outlined" label={`Usuario: ${linkedUser}`} />
        </Stack>
      )}
    </ListItemButton>
  )
}
