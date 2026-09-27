import { ApiError, apiRequest, CardTitle, messageOf } from '@ampa/ui'
import AddRounded from '@mui/icons-material/AddRounded'
import DeleteOutlineRounded from '@mui/icons-material/DeleteOutlineRounded'
import EventOutlined from '@mui/icons-material/EventOutlined'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import IconButton from '@mui/material/IconButton'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useEffect, useState } from 'react'
import type { CalendarPeriod, DayKind, SchoolYear } from '../types'
import { countSchoolDays, schoolYearOf } from './schoolDays'

interface Props {
  onUnauthorized: () => void
}

const KINDS: { value: DayKind; label: string }[] = [
  { value: 'holiday', label: 'Festivo' },
  { value: 'non_school', label: 'No lectivo' },
]

/** Un curso sin cargar todavía: todo en blanco. */
function blank(code: string): SchoolYear {
  return { schoolYear: code, classesStart: '', classesEnd: '', periods: [] }
}

/**
 * El calendario escolar común de la suite: cuándo hay clase y qué días son
 * festivos o no lectivos. Se carga una vez por curso y lo piden las
 * aplicaciones (fichajes, los festivos; facturación, los días lectivos de
 * los desayunos). Solo la ve quien gestiona los permisos.
 *
 * Guardar sustituye el curso entero.
 */
export function Calendar({ onUnauthorized }: Props) {
  const [years, setYears] = useState<SchoolYear[] | null>(null)
  const [selected, setSelected] = useState<string>(() => schoolYearOf(new Date()))
  const [draft, setDraft] = useState<SchoolYear>(() => blank(schoolYearOf(new Date())))
  const [loadError, setLoadError] = useState<string | null>(null)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    apiRequest<SchoolYear[]>('/api/admin/calendario')
      .then((loaded) => {
        if (cancelled) {
          return
        }
        setYears(loaded)
        const current = loaded.find((year) => year.schoolYear === schoolYearOf(new Date()))
        if (current !== undefined) {
          setDraft(current)
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

  if (loadError !== null) {
    return <Alert severity="error">{loadError}</Alert>
  }

  if (years === null) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}>
        <CircularProgress aria-label="Cargando" />
      </Box>
    )
  }

  // Los cursos cargados, más el actual y el siguiente aunque no lo estén.
  const thisYear = schoolYearOf(new Date())
  const nextYear = schoolYearOf(new Date(new Date().getFullYear() + 1, new Date().getMonth(), 1))
  const codes = [...new Set([nextYear, thisYear, ...years.map((year) => year.schoolYear)])].sort().reverse()

  const choose = (code: string) => {
    setSelected(code)
    setDraft(years.find((year) => year.schoolYear === code) ?? blank(code))
    setSaveError(null)
    setSaved(false)
  }

  const change = (next: Partial<SchoolYear>) => {
    setDraft({ ...draft, ...next })
    setSaved(false)
  }

  const changePeriod = (index: number, next: Partial<CalendarPeriod>) => {
    change({
      periods: draft.periods.map((period, i) => {
        if (i !== index) {
          return period
        }
        const updated = { ...period, ...next }
        // Un día suelto es lo más frecuente: al poner "desde", "hasta" es el mismo día.
        if (next.from !== undefined && (period.to === '' || period.to < next.from)) {
          updated.to = next.from
        }
        return updated
      }),
    })
  }

  const save = async () => {
    setBusy(true)
    setSaveError(null)
    try {
      const stored = await apiRequest<SchoolYear>(`/api/admin/calendario/${draft.schoolYear}`, {
        method: 'PUT',
        body: { classesStart: draft.classesStart, classesEnd: draft.classesEnd, periods: draft.periods },
      })
      setYears([...years.filter((year) => year.schoolYear !== stored.schoolYear), stored])
      setDraft(stored)
      setSaved(true)
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        onUnauthorized()
        return
      }
      setSaveError(messageOf(e))
    } finally {
      setBusy(false)
    }
  }

  const schoolDays = countSchoolDays(draft.classesStart, draft.classesEnd, draft.periods)
  const isLoaded = years.some((year) => year.schoolYear === selected)

  return (
    <Card>
      <CardContent sx={{ p: 3 }}>
        <CardTitle
          icon={<EventOutlined />}
          subtitle="Lo usan las aplicaciones: fichajes, los festivos; facturación, los días de clase."
        >
          Calendario escolar
        </CardTitle>

        <Stack spacing={3} sx={{ mt: 3 }}>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ alignItems: { sm: 'center' } }}>
            <TextField
              select
              label="Curso"
              value={selected}
              onChange={(event) => choose(event.target.value)}
              sx={{ minWidth: 180 }}
            >
              {codes.map((code) => (
                <MenuItem key={code} value={code}>
                  {code.replace('-', '/')}
                  {years.some((year) => year.schoolYear === code) ? '' : ' (sin cargar)'}
                </MenuItem>
              ))}
            </TextField>
            {!isLoaded && (
              <Typography variant="body2" color="text.secondary">
                Este curso aún no está cargado: las aplicaciones no lo ven hasta que se guarde.
              </Typography>
            )}
          </Stack>

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
            <TextField
              type="date"
              label="Primer día de clase"
              value={draft.classesStart}
              onChange={(event) => change({ classesStart: event.target.value })}
              slotProps={{ inputLabel: { shrink: true } }}
              fullWidth
            />
            <TextField
              type="date"
              label="Último día de clase"
              value={draft.classesEnd}
              onChange={(event) => change({ classesEnd: event.target.value })}
              slotProps={{ inputLabel: { shrink: true } }}
              fullWidth
            />
          </Stack>

          <Divider />

          <Box>
            <Typography variant="subtitle1" component="h3" sx={{ fontWeight: 500 }}>
              Días sin clase
            </Typography>
            <Typography variant="body2" color="text.secondary">
              <strong>Festivo</strong>: ni hay clase ni se trabaja (12 de octubre, fiesta local…). <strong>No lectivo</strong>: no
              hay clase, pero es laborable (vacaciones de Navidad y Semana Santa, días no lectivos del colegio). Los fines de
              semana no hace falta apuntarlos.
            </Typography>
          </Box>

          {draft.periods.length === 0 && (
            <Typography variant="body2" color="text.secondary">
              Ninguno apuntado.
            </Typography>
          )}

          {draft.periods.map((period, index) => (
            <Stack
              // Sin identificador propio: son filas de un formulario que se guarda entero.
              key={index}
              direction={{ xs: 'column', md: 'row' }}
              spacing={1.5}
              sx={{ alignItems: { md: 'center' } }}
              role="group"
              aria-label={period.name === '' ? `Día sin clase ${index + 1}` : period.name}
            >
              <TextField
                label="Nombre"
                value={period.name}
                onChange={(event) => changePeriod(index, { name: event.target.value })}
                sx={{ flex: 2 }}
                size="small"
              />
              <TextField
                type="date"
                label="Desde"
                value={period.from}
                onChange={(event) => changePeriod(index, { from: event.target.value })}
                slotProps={{ inputLabel: { shrink: true } }}
                sx={{ flex: 1 }}
                size="small"
              />
              <TextField
                type="date"
                label="Hasta"
                value={period.to}
                onChange={(event) => changePeriod(index, { to: event.target.value })}
                slotProps={{ inputLabel: { shrink: true } }}
                sx={{ flex: 1 }}
                size="small"
              />
              <TextField
                select
                label="Tipo"
                value={period.kind}
                onChange={(event) => changePeriod(index, { kind: event.target.value as DayKind })}
                sx={{ flex: 1, minWidth: 130 }}
                size="small"
              >
                {KINDS.map((kind) => (
                  <MenuItem key={kind.value} value={kind.value}>
                    {kind.label}
                  </MenuItem>
                ))}
              </TextField>
              <IconButton
                aria-label={`Quitar ${period.name === '' ? 'este día' : period.name}`}
                onClick={() => change({ periods: draft.periods.filter((_, i) => i !== index) })}
                sx={{ alignSelf: { xs: 'flex-end', md: 'center' } }}
              >
                <DeleteOutlineRounded />
              </IconButton>
            </Stack>
          ))}

          <Box>
            <Button
              startIcon={<AddRounded />}
              onClick={() => change({ periods: [...draft.periods, { from: '', to: '', kind: 'holiday', name: '' }] })}
            >
              Añadir día sin clase
            </Button>
          </Box>

          <Divider />

          {saveError !== null && <Alert severity="error">{saveError}</Alert>}
          {saved && <Alert severity="success">Guardado. Las aplicaciones ya lo ven.</Alert>}

          <Stack direction={{ xs: 'column-reverse', sm: 'row' }} spacing={2} sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between' }}>
            <Typography variant="body2" color="text.secondary">
              {schoolDays === null ? 'Pon el primer y el último día de clase.' : `${schoolDays} días de clase en el curso.`}
            </Typography>
            <Button variant="contained" onClick={save} disabled={busy}>
              Guardar el curso {draft.schoolYear.replace('-', '/')}
            </Button>
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  )
}
