import type { Contact, NotificationTarget } from '../types'

/** Los correos tal cual están en el formulario. */
export interface ContactValue {
  /** Tal cual está en el campo: vacío = sin segundo correo. */
  secondaryEmail: string
  notify: NotificationTarget
}

/** Lo que espera la API: un segundo correo vacío es "ninguno". */
export function contactPayload(value: ContactValue): Contact {
  const secondaryEmail = value.secondaryEmail.trim()

  return { secondaryEmail: secondaryEmail === '' ? null : secondaryEmail, notify: value.notify }
}
