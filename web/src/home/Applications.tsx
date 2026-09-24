import Alert from '@mui/material/Alert'
import Card from '@mui/material/Card'
import CardActionArea from '@mui/material/CardActionArea'
import CardContent from '@mui/material/CardContent'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import type { ReachableApplication } from '../types'

interface Props {
  applications: ReachableApplication[]
}

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
    <Stack spacing={2}>
      {applications.map((application) => (
        <Card key={application.code} variant="outlined">
          <CardActionArea href={application.url}>
            <CardContent>
              <Typography variant="h6" component="h2">
                {application.name}
              </Typography>
            </CardContent>
          </CardActionArea>
        </Card>
      ))}
    </Stack>
  )
}
