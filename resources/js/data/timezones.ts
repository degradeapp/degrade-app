// Fusos horários do Brasil oferecidos no cadastro da barbearia.
// Cobre os 4 fusos oficiais (UTC−2 a −5) com as zonas IANA distintas por região.
export const BR_TIMEZONES = [
  // GMT-3 — Brasília (maior parte do país: Sul, Sudeste, Nordeste, Centro-Oeste/DF)
  { value: 'America/Sao_Paulo', label: 'São Paulo / Brasília (GMT-3)' },
  { value: 'America/Bahia', label: 'Bahia (GMT-3)' },
  { value: 'America/Recife', label: 'Recife (GMT-3)' },
  { value: 'America/Fortaleza', label: 'Fortaleza (GMT-3)' },
  { value: 'America/Belem', label: 'Belém (GMT-3)' },
  { value: 'America/Araguaina', label: 'Tocantins (GMT-3)' },
  // GMT-4 — Amazônia / Centro-Oeste
  { value: 'America/Manaus', label: 'Manaus (GMT-4)' },
  { value: 'America/Cuiaba', label: 'Cuiabá (GMT-4)' },
  { value: 'America/Campo_Grande', label: 'Campo Grande (GMT-4)' },
  { value: 'America/Porto_Velho', label: 'Porto Velho (GMT-4)' },
  { value: 'America/Boa_Vista', label: 'Boa Vista (GMT-4)' },
  // GMT-5 — Acre e sudoeste do Amazonas
  { value: 'America/Rio_Branco', label: 'Rio Branco (GMT-5)' },
  // GMT-2 — Fernando de Noronha e ilhas do Atlântico
  { value: 'America/Noronha', label: 'Fernando de Noronha (GMT-2)' },
]
