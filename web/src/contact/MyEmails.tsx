import { ApiError, apiRequest, CardTitle, messageOf } from '@ampa/ui'
import AlternateEmailRounded from '@mui/icons-material/AlternateEmailRounded'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { useState, type FormEvent } from 'react'
import type { PortalUser } from '../types'
import { ContactFields } from './ContactFields'
import { contactPayload, type ContactValue } from './contactValue'

interface Props {
  user: PortalUser
  /** Con lo que contesta el servidor, para que el resto del portal lo vea ya. */
  onSaved: (user: PortalUser) => void
  onUnauthorized: () => void
}

/**
 * "Mis correos": cada uno elige su segundo correo y a dónde le llegan los
 * avisos de todas las aplicaciones. Es lo único de su ficha que puede tocar
 * él mismo; los permisos, no.
 */
export function MyEmails({ user, onSaved, onUnauthorized }: Props) {
  const [value, setValue] = useState<ContactValue>({ secondaryEmail: user.secondaryEmail ?? '', notify: user.notify })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  const save = async (event: FormEvent) => {
    event.preventDefault()
    setBusy(true)
    setError(null)
    setSaved(false)
    try {
      onSaved(await apiRequest<PortalUser>('/api/me/contacto', { method: 'PUT', body: contactPayload(value) }))
      setSaved(true)
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        onUnauthorized()
        return
      }
      setError(messageOf(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card>
      <CardContent sx={{ p: 3 }}>
        <CardTitle icon={<AlternateEmailRounded />}>Mis correos</CardTitle>

        <Stack component="form" spacing={3} onSubmit={save} noValidate sx={{ mt: 1.5 }}>
          <Typography variant="body2" color="text.secondary">
            Entras con <strong>{user.email}</strong>. Las aplicaciones del AMPA te mandan los avisos (una solicitud de
            vacaciones, por ejemplo) al correo que elijas aquí.
          </Typography>

          <ContactFields
            primaryEmail={user.email}
            value={value}
            onChange={(next) => {
              setValue(next)
              setSaved(false)
            }}
            disabled={busy}
          />

          {error && <Alert severity="error">{error}</Alert>}
          {saved && <Alert severity="success">Guardado.</Alert>}

          <Button type="submit" variant="contained" disabled={busy} sx={{ alignSelf: 'flex-start' }}>
            Guardar
          </Button>
        </Stack>
      </CardContent>
    </Card>
  )
}
