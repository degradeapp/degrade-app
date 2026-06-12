export const useFormatting = () => {
  // BRL: 1250.50 → "R$ 1.250,50"
  const formatBRL = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return 'R$ 0,00'
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value)
  }

  // "R$ 1.250,50" → 1250.50
  const parseBRL = (formatted: string): number => {
    const cleaned = formatted.replace(/[^\d,]/g, '').replace(',', '.')
    return parseFloat(cleaned) || 0
  }

  // "85987654321" → "(85) 98765-4321"
  const formatPhone = (value: string | null | undefined): string => {
    if (!value) return ''
    const cleaned = value.replace(/\D/g, '')
    if (cleaned.length < 10) return cleaned
    if (cleaned.length === 10) {
      return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 6)}-${cleaned.slice(6)}`
    }
    if (cleaned.length === 11) {
      return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 7)}-${cleaned.slice(7)}`
    }
    return cleaned
  }

  // "(85) 98765-4321" → "85987654321"
  const parsePhone = (formatted: string): string => {
    return formatted.replace(/\D/g, '')
  }

  // "2026-05-22" → "22/05/2026"
  const formatDateBR = (date: Date | string | null | undefined): string => {
    if (!date) return ''
    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()

    return `${day}/${month}/${year}`
  }

  // "2026-05-22" → "22/05/26"
  const formatDateBRShort = (date: Date | string | null | undefined): string => {
    if (!date) return ''
    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = String(d.getFullYear()).slice(-2)

    return `${day}/${month}/${year}`
  }

  // "14:30:00" → "14:30"
  const formatTimeBR = (time: string | null | undefined): string => {
    if (!time) return ''
    const [hours, minutes] = time.split(':')
    return `${hours}:${minutes || '00'}`
  }

  // Próximas datas: "Hoje", "Amanhã", "Em 2h", "22/05/2026"
  const formatDateRelative = (date: Date | string | null | undefined): string => {
    if (!date) return ''

    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const today = new Date()
    today.setHours(0, 0, 0, 0)

    const tomorrow = new Date(today)
    tomorrow.setDate(tomorrow.getDate() + 1)

    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)

    const dateToCheck = new Date(d)
    dateToCheck.setHours(0, 0, 0, 0)

    // Mesmo dia - mostra "Em Xh"
    if (dateToCheck.getTime() === today.getTime()) {
      const now = new Date()
      const diffMs = d.getTime() - now.getTime()
      const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
      const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60))

      if (diffMins < 0) return 'Agora'
      if (diffHours === 0) return `Em ${diffMins}min`
      if (diffHours === 1) return 'Em 1h'
      return `Em ${diffHours}h`
    }

    if (dateToCheck.getTime() === tomorrow.getTime()) return 'Amanhã'
    if (dateToCheck.getTime() === yesterday.getTime()) return 'Ontem'

    return formatDateBR(d)
  }

  // Datas passadas: "Há 2h", "Há 3 dias"
  const formatDateRelativePast = (date: Date | string | null | undefined): string => {
    if (!date) return ''

    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const now = new Date()
    const diffMs = now.getTime() - d.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMs / 3600000)
    const diffDays = Math.floor(diffMs / 86400000)

    if (diffMins < 1) return 'Agora'
    if (diffMins < 60) return `Há ${diffMins}min`
    if (diffHours < 24) return `Há ${diffHours}h`
    if (diffDays === 1) return 'Ontem'
    if (diffDays < 7) return `Há ${diffDays} dias`

    return formatDateBR(d)
  }

  // "21 de maio, quinta"
  const formatDateLongBR = (date: Date | string | null | undefined): string => {
    if (!date) return ''

    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const days = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado']
    const months = [
      'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
      'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
    ]

    const day = d.getDate()
    const month = months[d.getMonth()]
    const dayName = days[d.getDay()]

    return `${day} de ${month}, ${dayName}`
  }

  // "21/05/26 14:30"
  const formatDateTimeBR = (date: Date | string | null | undefined): string => {
    if (!date) return ''
    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const datePart = formatDateBRShort(d)
    const timePart = formatTimeBR(`${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`)

    return `${datePart} ${timePart}`
  }

  // "Sexta-feira, 23 de maio"
  const formatDateLong = (date: Date | string | null | undefined): string => {
    if (!date) return ''
    const d = typeof date === 'string' ? new Date(date) : date
    if (isNaN(d.getTime())) return ''

    const s = new Intl.DateTimeFormat('pt-BR', {
      day: 'numeric',
      month: 'long',
      weekday: 'long',
    }).format(d)
    return s.charAt(0).toUpperCase() + s.slice(1)
  }

  return {
    formatBRL,
    parseBRL,
    formatPhone,
    parsePhone,
    formatDateBR,
    formatDateBRShort,
    formatTimeBR,
    formatDateRelative,
    formatDateRelativePast,
    formatDateLongBR,
    formatDateLong,
    formatDateTimeBR,
  }
}
