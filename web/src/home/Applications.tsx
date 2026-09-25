import type { ReactNode } from 'react'
import AccessTimeRounded from '@mui/icons-material/AccessTimeRounded'
import AccountBalanceWalletRounded from '@mui/icons-material/AccountBalanceWalletRounded'
import AppsRounded from '@mui/icons-material/AppsRounded'
import ArrowForwardRounded from '@mui/icons-material/ArrowForwardRounded'
import FactCheckRounded from '@mui/icons-material/FactCheckRounded'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardActionArea from '@mui/material/CardActionArea'
import Typography from '@mui/material/Typography'
import { alpha } from '@mui/material/styles'
import type { ReachableApplication } from '../types'

interface Props {
  applications: ReachableApplication[]
}

interface Look {
  icon: ReactNode
  description: string
  color: 'primary' | 'secondary'
}

/**
 * Cómo se ve cada aplicación. Va aquí y no en el catálogo del servidor porque
 * es solo presentación; una aplicación nueva que no esté se ve con el icono
 * genérico hasta que se le ponga el suyo.
 */
const LOOKS: Record<string, Look> = {
  fichajes: { icon: <AccessTimeRounded />, description: 'Registro de jornada y bolsa de horas', color: 'primary' },
  listados: { icon: <FactCheckRounded />, description: 'Listados de las extraescolares para los monitores', color: 'secondary' },
  facturacion: { icon: <AccountBalanceWalletRounded />, description: 'Contabilidad y tesorería: caja, banco y cierres', color: 'primary' },
}

const DEFAULT_LOOK: Look = { icon: <AppsRounded />, description: 'Aplicación del AMPA', color: 'secondary' }

/**
 * Una tarjeta por aplicación a la que puede ir. Entrar en ella no pide nada
 * más: la sesión del portal vale para todas.
 */
export function Applications({ applications }: Props) {
  if (applications.length === 0) {
    // Solo gestiona permisos, sin ninguna aplicación propia.
    return <Alert severity="info">No tienes acceso a ninguna aplicación. Puedes gestionar los permisos en su pestaña.</Alert>
  }

  return (
    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' } }}>
      {applications.map((application) => {
        const look = LOOKS[application.code] ?? DEFAULT_LOOK

        return (
          <Card
            key={application.code}
            variant="outlined"
            sx={(theme) => ({
              borderRadius: 3,
              // La franja de arriba, del color de la aplicación.
              borderTop: `4px solid ${theme.palette[look.color].main}`,
              transition: 'box-shadow 150ms, transform 150ms, border-color 150ms',
              '&:hover': {
                transform: 'translateY(-2px)',
                boxShadow: theme.shadows[4],
                borderColor: theme.palette[look.color].main,
              },
              '&:hover .arrow': { transform: 'translateX(4px)', color: theme.palette[look.color].main },
            })}
          >
            {/* aria-label: que el enlace se anuncie por el nombre, sin la descripción. */}
            <CardActionArea
              href={application.url}
              aria-label={application.name}
              sx={{ display: 'flex', alignItems: 'center', gap: 2, p: 2.5, height: '100%' }}
            >
              <Box
                sx={(theme) => ({
                  flexShrink: 0,
                  width: 56,
                  height: 56,
                  borderRadius: '50%',
                  display: 'grid',
                  placeItems: 'center',
                  color: theme.palette[look.color].main,
                  bgcolor: alpha(theme.palette[look.color].main, 0.1),
                  '& svg': { fontSize: 30 },
                })}
              >
                {look.icon}
              </Box>
              <Box sx={{ flexGrow: 1, minWidth: 0 }}>
                <Typography variant="h6" component="h2" sx={{ fontWeight: 600, lineHeight: 1.3 }}>
                  {application.name}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {look.description}
                </Typography>
              </Box>
              <ArrowForwardRounded className="arrow" sx={{ color: 'text.disabled', transition: 'transform 150ms, color 150ms' }} />
            </CardActionArea>
          </Card>
        )
      })}
    </Box>
  )
}
