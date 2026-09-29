import { ApiError, apiRequest, CardTitle, messageOf } from '@ampa/ui'
import HelpOutlineRounded from '@mui/icons-material/HelpOutlineRounded'
import UploadFileRounded from '@mui/icons-material/UploadFileRounded'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Link from '@mui/material/Link'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { useEffect, useState, type ChangeEvent } from 'react'
import type { HelpSummary } from '../types'

interface Props {
  onUnauthorized: () => void
}

/** "28 de septiembre de 2026 a las 20:25", en hora de Madrid. */
function describeInstant(iso: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Europe/Madrid',
  }).format(new Date(iso))
}

/** "2026-09-28" → "28 de septiembre de 2026". En UTC: es un día, sin hora, y así no se corre al anterior. */
function describeDay(day: string): string {
  return new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(
    new Date(`${day}T00:00:00Z`),
  )
}

/**
 * La ayuda de la suite: las preguntas que salen en el botón «Ayuda» de todas
 * las aplicaciones. Se preparan fuera (el fichero faq.json, que no está en
 * ningún repositorio: son públicos) y aquí se carga el fichero entero, que
 * sustituye lo que hubiera. Solo la ve quien gestiona los permisos.
 *
 * El fichero se lee en el navegador y se manda tal cual: quien dice si vale
 * es el servidor, y si no, su mensaje dice qué pregunta y por qué.
 */
export function Help({ onUnauthorized }: Props) {
  const [summary, setSummary] = useState<HelpSummary | null>(null)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const [uploaded, setUploaded] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    apiRequest<HelpSummary>('/api/admin/ayuda')
      .then((loaded) => {
        if (!cancelled) {
          setSummary(loaded)
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
        setLoadError(messageOf(e))
      })

    return () => {
      cancelled = true
    }
  }, [onUnauthorized])

  const upload = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    // Vaciarlo: si no, volver a elegir el MISMO fichero (ya corregido) no avisaría de nada.
    event.target.value = ''
    if (file === undefined) {
      return
    }

    setUploadError(null)
    setUploaded(null)

    let content: unknown
    try {
      content = JSON.parse(await file.text())
    } catch {
      setUploadError(`«${file.name}» no es un fichero JSON válido.`)
      return
    }

    setBusy(true)
    try {
      const stored = await apiRequest<HelpSummary>('/api/admin/ayuda', { method: 'PUT', body: content })
      setSummary(stored)
      setUploaded(stored.total)
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        onUnauthorized()
        return
      }
      setUploadError(messageOf(e))
    } finally {
      setBusy(false)
    }
  }

  if (loadError !== null) {
    return <Alert severity="error">{loadError}</Alert>
  }

  if (summary === null) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
        <CircularProgress aria-label="Cargando" />
      </Box>
    )
  }

  return (
    <Card>
      <CardContent sx={{ p: 3 }}>
        <CardTitle icon={<HelpOutlineRounded />} subtitle="Las preguntas del botón «Ayuda» de todas las aplicaciones.">
          Ayuda
        </CardTitle>

        <Stack spacing={3} sx={{ mt: 3 }}>
          {summary.loadedAt === null ? (
            <Typography color="text.secondary">
              Todavía no se ha cargado ninguna ayuda: el botón «Ayuda» de las aplicaciones sale vacío.
            </Typography>
          ) : (
            <Box>
              <Typography>
                Cargada el {describeInstant(summary.loadedAt)}
                {summary.updatedAt !== null && ` (fichero revisado el ${describeDay(summary.updatedAt)})`}.
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {summary.total === 1 ? '1 pregunta' : `${summary.total} preguntas`} en total.
              </Typography>
            </Box>
          )}

          {summary.loadedAt !== null && (
            <Box component="dl" sx={{ m: 0 }} aria-label="Preguntas por aplicación">
              {summary.byApplication.map((count) => (
                <Stack
                  key={count.application}
                  direction="row"
                  sx={{ justifyContent: 'space-between', py: 0.75, borderBottom: 1, borderColor: 'divider' }}
                >
                  <Typography component="dt">{count.name}</Typography>
                  <Typography component="dd" sx={{ m: 0, color: count.entries === 0 ? 'text.secondary' : 'text.primary' }}>
                    {count.entries === 0 ? 'ninguna' : count.entries}
                  </Typography>
                </Stack>
              ))}
            </Box>
          )}

          {summary.loadedAt !== null && (
            <Stack spacing={0.5}>
              <Typography variant="body2">
                Cuaderno del asistente:{' '}
                {summary.notebookUrl === null ? (
                  'sin enlace'
                ) : (
                  <Link href={summary.notebookUrl} target="_blank" rel="noopener noreferrer">
                    abrir en NotebookLM
                  </Link>
                )}
              </Typography>
              <Typography variant="body2">Correo para dudas: {summary.contactEmail ?? 'sin correo'}</Typography>
            </Stack>
          )}

          <Divider />

          {uploadError !== null && <Alert severity="error">{uploadError}</Alert>}
          {uploaded !== null && (
            <Alert severity="success">
              Cargadas {uploaded === 1 ? '1 pregunta' : `${uploaded} preguntas`}. Las aplicaciones ya las ven.
            </Alert>
          )}

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between' }}>
            <Typography variant="body2" color="text.secondary">
              Sustituye todas las preguntas por las del fichero (faq.json).
            </Typography>
            <Button component="label" variant="contained" startIcon={<UploadFileRounded />} disabled={busy}>
              Cargar el fichero de preguntas
              {/* Escondido a la vista, no al teclado ni a los lectores de pantalla: el botón es su etiqueta. */}
              <Box
                component="input"
                type="file"
                accept=".json,application/json"
                onChange={upload}
                disabled={busy}
                sx={{ clip: 'rect(0 0 0 0)', clipPath: 'inset(50%)', height: '1px', overflow: 'hidden', position: 'absolute', whiteSpace: 'nowrap', width: '1px' }}
              />
            </Button>
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  )
}
