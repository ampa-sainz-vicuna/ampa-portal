import { ApiError, apiRequest, ConfirmDialog, messageOf } from '@ampa/ui'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import useMediaQuery from '@mui/material/useMediaQuery'
import { useTheme } from '@mui/material/styles'
import { useState } from 'react'
import type { BoardMember, BoardPosition, SuiteUser } from '../types'
import { isValidDocument, isValidEmail, isValidPhone, POSITIONS } from './validation'

interface Props {
  /** null para dar de alta. */
  member: BoardMember | null
  /** Las personas que ya tiene el portal, para asociar una al cargo. */
  users: SuiteUser[]
  onClose: () => void
  onSaved: () => void
  onUnauthorized: () => void
}

type Errors = Partial<Record<'firstName' | 'lastName' | 'document' | 'address' | 'email' | 'phone' | 'startDate', string>>

/**
 * La ficha de un cargo de la junta: datos personales, cargo, fechas y el
 * usuario de la plataforma asociado (opcional). Guardar sustituye la ficha
 * entera. «Dar de baja» solo en quien sigue en el cargo: pone la fecha de fin
 * de hoy y la persona pasa a anteriores.
 *
 * Se valida aquí lo evidente para avisar sin esperar; el servidor lo vuelve a
 * comprobar todo y su mensaje (409: cargo ocupado, 422: dato inválido) se
 * enseña tal cual.
 */
export function BoardDialog({ member, users, onClose, onSaved, onUnauthorized }: Props) {
  const theme = useTheme()
  // En móvil, a pantalla completa: son muchos campos.
  const fullScreen = useMediaQuery(theme.breakpoints.down('sm'))

  const [firstName, setFirstName] = useState(member?.firstName ?? '')
  const [lastName, setLastName] = useState(member?.lastName ?? '')
  const [identityDocument, setIdentityDocument] = useState(member?.document ?? '')
  const [address, setAddress] = useState(member?.address ?? '')
  const [email, setEmail] = useState(member?.email ?? '')
  const [phone, setPhone] = useState(member?.phone ?? '')
  const [position, setPosition] = useState<BoardPosition>(member?.position ?? 'member')
  const [startDate, setStartDate] = useState(member?.startDate ?? '')
  const [userId, setUserId] = useState(member?.userId ?? '')
  const [errors, setErrors] = useState<Errors>({})
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [confirmingEnd, setConfirmingEnd] = useState(false)

  const isActive = member === null || member.endDate === null
  // Las personas activas y, si ya la tiene asociada, esa aunque se haya desactivado.
  const selectableUsers = users.filter((user) => user.active || user.id === member?.userId)

  const validate = (): Errors => {
    const found: Errors = {}
    if (firstName.trim() === '') found.firstName = 'Falta el nombre.'
    if (lastName.trim() === '') found.lastName = 'Faltan los apellidos.'
    if (!isValidDocument(identityDocument)) found.document = 'DNI o NIE no válido (revisa la letra).'
    if (address.trim() === '') found.address = 'Falta la dirección.'
    if (!isValidEmail(email)) found.email = 'Correo no válido.'
    if (!isValidPhone(phone)) found.phone = '9 cifras, o con prefijo internacional (+34…).'
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startDate)) found.startDate = 'Pon la fecha de inicio.'

    return found
  }

  /** Los errores del servidor (401: sesión caducada) se tratan igual en las tres acciones. */
  const run = async (action: () => Promise<void>) => {
    setBusy(true)
    setError(null)
    try {
      await action()
      onSaved()
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        onUnauthorized()
        return
      }
      setError(messageOf(e))
      setBusy(false)
      setConfirmingEnd(false)
    }
  }

  const save = () => {
    const found = validate()
    setErrors(found)
    if (Object.keys(found).length > 0) {
      return
    }

    void run(async () => {
      const body = { firstName, lastName, document: identityDocument, address, email, phone, position, startDate }
      const saved =
        member === null
          ? await apiRequest<BoardMember>('/api/admin/junta', { method: 'POST', body })
          : await apiRequest<BoardMember>(`/api/admin/junta/${member.id}`, { method: 'PUT', body })

      // El usuario asociado va aparte en la API: solo se llama si ha cambiado.
      if ((saved.userId ?? '') !== userId) {
        await apiRequest(`/api/admin/junta/${saved.id}/usuario`, { method: 'PUT', body: { userId: userId === '' ? null : userId } })
      }
    })
  }

  const end = () => {
    if (member === null) {
      return
    }
    void run(async () => {
      await apiRequest(`/api/admin/junta/${member.id}/baja`, { method: 'POST', body: {} })
    })
  }

  return (
    <>
      <Dialog open onClose={busy ? undefined : onClose} fullWidth maxWidth="sm" fullScreen={fullScreen} aria-labelledby="cargo-titulo">
        <DialogTitle id="cargo-titulo">{member === null ? 'Nuevo cargo' : `${member.firstName} ${member.lastName}`}</DialogTitle>

        <DialogContent>
          <Stack spacing={2.5} sx={{ pt: 1 }}>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2.5}>
              <TextField
                label="Nombre"
                value={firstName}
                onChange={(event) => setFirstName(event.target.value)}
                error={errors.firstName !== undefined}
                helperText={errors.firstName}
                disabled={busy}
                required
                fullWidth
              />
              <TextField
                label="Apellidos"
                value={lastName}
                onChange={(event) => setLastName(event.target.value)}
                error={errors.lastName !== undefined}
                helperText={errors.lastName}
                disabled={busy}
                required
                fullWidth
              />
            </Stack>

            <TextField
              label="DNI o NIE"
              value={identityDocument}
              onChange={(event) => setIdentityDocument(event.target.value)}
              error={errors.document !== undefined}
              helperText={errors.document}
              disabled={busy}
              required
              fullWidth
            />
            <TextField
              label="Dirección postal"
              value={address}
              onChange={(event) => setAddress(event.target.value)}
              error={errors.address !== undefined}
              helperText={errors.address}
              disabled={busy}
              multiline
              minRows={2}
              required
              fullWidth
            />

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2.5}>
              <TextField
                label="Correo de contacto"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                error={errors.email !== undefined}
                helperText={errors.email ?? 'No tiene por qué ser el de su cuenta de la suite.'}
                disabled={busy}
                required
                fullWidth
              />
              <TextField
                label="Teléfono de contacto"
                type="tel"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                error={errors.phone !== undefined}
                helperText={errors.phone}
                disabled={busy}
                required
                fullWidth
              />
            </Stack>

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2.5}>
              <TextField
                select
                label="Cargo"
                value={position}
                onChange={(event) => setPosition(event.target.value as BoardPosition)}
                disabled={busy}
                required
                fullWidth
              >
                {POSITIONS.map((option) => (
                  <MenuItem key={option.value} value={option.value}>
                    {option.label}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label="Fecha de inicio"
                type="date"
                value={startDate}
                onChange={(event) => setStartDate(event.target.value)}
                error={errors.startDate !== undefined}
                helperText={errors.startDate}
                disabled={busy}
                slotProps={{ inputLabel: { shrink: true } }}
                required
                fullWidth
              />
            </Stack>

            <TextField
              select
              label="Usuario de la plataforma"
              value={userId}
              onChange={(event) => setUserId(event.target.value)}
              helperText="Opcional: la persona de la suite que lleva este cargo."
              disabled={busy}
              fullWidth
            >
              <MenuItem value="">Sin usuario asociado</MenuItem>
              {selectableUsers.map((user) => (
                <MenuItem key={user.id} value={user.id}>
                  {user.name} ({user.email})
                </MenuItem>
              ))}
            </TextField>

            {error && <Alert severity="error">{error}</Alert>}
          </Stack>
        </DialogContent>

        <DialogActions sx={{ flexWrap: 'wrap', gap: 1 }}>
          {member !== null && isActive && (
            <Button color="error" onClick={() => setConfirmingEnd(true)} disabled={busy} sx={{ mr: 'auto' }}>
              Dar de baja
            </Button>
          )}
          <Button onClick={onClose} disabled={busy}>
            Cancelar
          </Button>
          <Button variant="contained" onClick={save} disabled={busy}>
            Guardar
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={confirmingEnd}
        title="Dar de baja"
        confirmLabel="Dar de baja"
        busy={busy}
        onConfirm={end}
        onCancel={() => setConfirmingEnd(false)}
      >
        {member?.firstName} {member?.lastName} deja el cargo con fecha de hoy. No se borra: pasa a la lista de anteriores y el cargo queda libre.
      </ConfirmDialog>
    </>
  )
}
