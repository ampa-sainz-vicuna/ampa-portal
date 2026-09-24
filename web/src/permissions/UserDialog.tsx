import { ApiError, apiRequest, messageOf } from '@ampa/ui'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Checkbox from '@mui/material/Checkbox'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormGroup from '@mui/material/FormGroup'
import FormLabel from '@mui/material/FormLabel'
import Stack from '@mui/material/Stack'
import Switch from '@mui/material/Switch'
import TextField from '@mui/material/TextField'
import { useState } from 'react'
import { ContactFields } from '../contact/ContactFields'
import { contactPayload, type ContactValue } from '../contact/contactValue'
import type { CatalogApplication, Grants, SuiteUser } from '../types'

interface Props {
  /** null para dar de alta. */
  user: SuiteUser | null
  applications: CatalogApplication[]
  /** Si es la ficha de quien la está editando: no puede quitarse la gestión ni desactivarse. */
  isMe: boolean
  onClose: () => void
  onSaved: () => void
  onUnauthorized: () => void
}

/**
 * La ficha de una persona: nombre, correos, si está activa y una casilla por
 * cada rol de cada aplicación. Guardar sustituye la ficha entera.
 *
 * El correo de la cuenta solo se escribe al dar de alta: es su identidad en
 * Google. Si cambia de cuenta, es otra persona (alta nueva y desactivar la
 * vieja).
 */
export function UserDialog({ user, applications, isMe, onClose, onSaved, onUnauthorized }: Props) {
  const [email, setEmail] = useState(user?.email ?? '')
  const [name, setName] = useState(user?.name ?? '')
  const [active, setActive] = useState(user?.active ?? true)
  const [grants, setGrants] = useState<Grants>(user?.grants ?? {})
  const [contact, setContact] = useState<ContactValue>({
    secondaryEmail: user?.secondaryEmail ?? '',
    notify: user?.notify ?? 'primary',
  })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const has = (application: string, role: string) => (grants[application] ?? []).includes(role)

  const toggle = (application: string, role: string) => {
    const current = grants[application] ?? []
    const next = has(application, role) ? current.filter((candidate) => candidate !== role) : [...current, role]
    setGrants({ ...grants, [application]: next })
  }

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      const body = { name, active, grants, ...contactPayload(contact) }
      if (user === null) {
        await apiRequest('/api/admin/users', { method: 'POST', body: { email, ...body } })
      } else {
        await apiRequest(`/api/admin/users/${user.id}`, { method: 'PUT', body })
      }
      onSaved()
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        onUnauthorized()
        return
      }
      // 409 (ya existe, o quitarse a sí mismo la gestión) y 422 (un correo
      // que no vale…): el mensaje del servidor lo explica.
      setError(messageOf(e))
      setBusy(false)
    }
  }

  return (
    <Dialog open onClose={busy ? undefined : onClose} fullWidth maxWidth="sm" aria-labelledby="ficha-titulo">
      <DialogTitle id="ficha-titulo">{user === null ? 'Dar de alta' : user.name}</DialogTitle>

      <DialogContent>
        <Stack spacing={3} sx={{ pt: 1 }}>
          <TextField
            label="Correo de la cuenta de Google"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            disabled={busy || user !== null}
            helperText={user === null ? 'Con el que entrará. No se puede cambiar después.' : undefined}
            required
            fullWidth
          />
          <TextField
            label="Nombre"
            value={name}
            onChange={(event) => setName(event.target.value)}
            disabled={busy}
            helperText="El que se verá en las aplicaciones."
            required
            fullWidth
          />

          {user !== null && (
            <FormControlLabel
              control={<Switch checked={active} onChange={(event) => setActive(event.target.checked)} />}
              label={active ? 'Activo' : 'Desactivado: no entra en ninguna aplicación'}
              disabled={busy || isMe}
            />
          )}

          <Divider />

          {applications.map((application) => (
            <FormGroup key={application.code}>
              <FormLabel component="legend">{application.name}</FormLabel>
              {application.roles.map((role) => (
                <FormControlLabel
                  key={role.code}
                  control={<Checkbox checked={has(application.code, role.code)} onChange={() => toggle(application.code, role.code)} />}
                  label={role.name}
                  // Quitarse a uno mismo la gestión de permisos dejaría la
                  // suite sin nadie que pueda darlos. El servidor también lo impide.
                  disabled={busy || (isMe && application.code === 'portal' && role.code === 'admin')}
                />
              ))}
            </FormGroup>
          ))}

          <Divider />

          <ContactFields primaryEmail={email || 'la cuenta'} value={contact} onChange={setContact} disabled={busy} />

          {error && <Alert severity="error">{error}</Alert>}
        </Stack>
      </DialogContent>

      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancelar
        </Button>
        <Button variant="contained" onClick={() => void save()} disabled={busy}>
          Guardar
        </Button>
      </DialogActions>
    </Dialog>
  )
}
