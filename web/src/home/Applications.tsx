import { ApplicationIcon } from '@ampa/ui'
import ArrowForwardRounded from '@mui/icons-material/ArrowForwardRounded'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardActionArea from '@mui/material/CardActionArea'
import Typography from '@mui/material/Typography'
import { alpha } from '@mui/material/styles'
import type { ReachableApplication } from '../types'

interface Props {
  /** Quién ha entrado, para saludarle. */
  userName: string
  applications: ReachableApplication[]
}

interface Look {
  description: string
  color: 'primary' | 'secondary'
}

/**
 * Cómo se ve cada aplicación. Va aquí y no en el catálogo del servidor porque
 * es solo presentación; una aplicación nueva que no esté se ve con la
 * descripción genérica hasta que se le ponga la suya. El icono es el de
 * @ampa/ui (ApplicationIcon), el mismo que en el selector de la barra.
 */
const LOOKS: Record<string, Look> = {
  fichajes: { description: 'Registro de jornada y bolsa de horas', color: 'primary' },
  listados: { description: 'Listados de las extraescolares para los monitores', color: 'secondary' },
  facturacion: { description: 'Contabilidad y tesorería: caja, banco y cierres', color: 'primary' },
  tareas: { description: 'Tablero de tareas de la junta y Alberto', color: 'secondary' },
  crm: { description: 'A quién se llama para cada cosa y cómo fue', color: 'primary' },
  documentos: { description: 'Actas, contratos, seguros y demás papeles del AMPA', color: 'secondary' },
  familias: { description: 'Familias, socios, alumnos, extraescolares y cobros', color: 'primary' },
}

const DEFAULT_LOOK: Look = { description: 'Aplicación del AMPA', color: 'secondary' }

/** «lunes, 28 de septiembre», en la hora de Madrid, que es la del AMPA. */
const TODAY = new Intl.DateTimeFormat('es-ES', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'Europe/Madrid' })

/** La primera palabra del nombre: «Hola, Alberto», no «Hola, Alberto García López». */
function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name
}

/**
 * Una tarjeta por aplicación a la que puede ir, con un saludo encima. Entrar
 * en ella no pide nada más: la sesión del portal vale para todas.
 *
 * Desde el lavado de cara del 28/09/2026 (@ampa/ui 0.2.4), el icono va en un
 * cuadrado redondeado relleno del color de la aplicación, en vez de la franja
 * de arriba: el color sigue siendo lo primero que se ve, sin cortar la
 * esquina redonda de la tarjeta.
 */
export function Applications({ userName, applications }: Props) {
  return (
    <>
      <Box component="header" sx={{ mb: 3, mt: { xs: 0.5, sm: 1 } }}>
        <Typography variant="overline" color="text.secondary" component="p" sx={{ lineHeight: 1.6 }}>
          {TODAY.format(new Date())}
        </Typography>
        <Typography variant="h4" component="h1" sx={{ fontSize: { xs: '1.75rem', sm: '2.125rem' } }}>
          Hola, {firstName(userName)}
        </Typography>
        <Typography variant="body1" color="text.secondary" sx={{ mt: 0.5 }}>
          {applications.length === 0 ? 'Este es el portal del AMPA.' : '¿A qué aplicación vas? Con esta sesión entras en todas.'}
        </Typography>
      </Box>

      {applications.length === 0 ? (
        // Solo gestiona permisos, sin ninguna aplicación propia.
        <Alert severity="info">No tienes acceso a ninguna aplicación. Puedes gestionar los permisos en su pestaña.</Alert>
      ) : (
        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' } }}>
          {applications.map((application) => {
            const look = LOOKS[application.code] ?? DEFAULT_LOOK

            return (
              <Card
                key={application.code}
                sx={(theme) => {
                  const main = theme.palette[look.color].main
                  return {
                    transition: 'box-shadow 200ms, transform 200ms, border-color 200ms',
                    // Al pasar por encima se levanta, con la sombra teñida de su
                    // color como el resto de la suite, no gris.
                    '&:hover': {
                      transform: 'translateY(-3px)',
                      boxShadow: `0 4px 10px ${alpha(main, 0.1)}, 0 14px 32px ${alpha(main, 0.16)}`,
                      borderColor: alpha(main, 0.35),
                    },
                    '&:hover .arrow': { transform: 'translateX(4px)', color: main, bgcolor: alpha(main, 0.1) },
                  }
                }}
              >
                {/* aria-label: que el enlace se anuncie por el nombre, sin la descripción. */}
                <CardActionArea
                  href={application.url}
                  aria-label={application.name}
                  sx={{ display: 'flex', alignItems: 'center', gap: 2, p: 2.5, height: '100%' }}
                >
                  <Box
                    sx={(theme) => {
                      const main = theme.palette[look.color].main
                      return {
                        flexShrink: 0,
                        width: 56,
                        height: 56,
                        borderRadius: '16px',
                        display: 'grid',
                        placeItems: 'center',
                        color: theme.palette[look.color].contrastText,
                        background: `linear-gradient(135deg, ${alpha(main, 0.85)}, ${main})`,
                        boxShadow: `0 4px 12px ${alpha(main, 0.3)}`,
                        '& svg': { fontSize: 28 },
                      }
                    }}
                  >
                    <ApplicationIcon code={application.code} />
                  </Box>
                  <Box sx={{ flexGrow: 1, minWidth: 0 }}>
                    <Typography variant="h6" component="h2" sx={{ lineHeight: 1.3 }}>
                      {application.name}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.25 }}>
                      {look.description}
                    </Typography>
                  </Box>
                  <Box
                    className="arrow"
                    sx={{
                      flexShrink: 0,
                      width: 36,
                      height: 36,
                      borderRadius: '50%',
                      display: 'grid',
                      placeItems: 'center',
                      color: 'text.disabled',
                      transition: 'transform 200ms, color 200ms, background-color 200ms',
                    }}
                  >
                    <ArrowForwardRounded fontSize="small" />
                  </Box>
                </CardActionArea>
              </Card>
            )
          })}
        </Box>
      )}
    </>
  )
}
