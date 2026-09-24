import FormControl from '@mui/material/FormControl'
import FormControlLabel from '@mui/material/FormControlLabel'
import FormLabel from '@mui/material/FormLabel'
import Radio from '@mui/material/Radio'
import RadioGroup from '@mui/material/RadioGroup'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import type { NotificationTarget } from '../types'
import type { ContactValue } from './contactValue'

interface Props {
  /** La cuenta con la que entra, para que se vea a qué correo se refiere "el de la cuenta". */
  primaryEmail: string
  value: ContactValue
  onChange: (value: ContactValue) => void
  disabled?: boolean
}

/**
 * El segundo correo y a dónde llegan los avisos. Lo usan "Mis correos" y la
 * ficha de la pantalla de permisos, para que se vea y se entienda igual en
 * los dos sitios.
 *
 * La junta entra con su cuenta del AMPA (a veces la del cargo), pero muchos
 * prefieren leer los avisos en su correo personal: para eso es el segundo.
 */
export function ContactFields({ primaryEmail, value, onChange, disabled = false }: Props) {
  const hasSecondary = value.secondaryEmail.trim() !== ''

  const changeSecondary = (secondaryEmail: string) => {
    // Sin segundo correo, los avisos solo pueden ir a la cuenta: se corrige
    // aquí para no dejar elegida una opción que el servidor rechazaría.
    const notify = secondaryEmail.trim() === '' ? 'primary' : value.notify
    onChange({ secondaryEmail, notify })
  }

  return (
    <Stack spacing={2}>
      <TextField
        label="Segundo correo"
        type="email"
        value={value.secondaryEmail}
        onChange={(event) => changeSecondary(event.target.value)}
        helperText="Opcional. Por ejemplo, tu correo personal."
        disabled={disabled}
        fullWidth
      />

      <FormControl disabled={disabled}>
        <FormLabel id="avisos">Los avisos, ¿a qué correo?</FormLabel>
        <RadioGroup
          aria-labelledby="avisos"
          value={value.notify}
          onChange={(event) => onChange({ ...value, notify: event.target.value as NotificationTarget })}
        >
          <FormControlLabel value="primary" control={<Radio />} label={`Al de la cuenta (${primaryEmail})`} />
          <FormControlLabel value="secondary" control={<Radio />} label="Al segundo correo" disabled={!hasSecondary} />
          <FormControlLabel value="both" control={<Radio />} label="A los dos" disabled={!hasSecondary} />
        </RadioGroup>
      </FormControl>
    </Stack>
  )
}
