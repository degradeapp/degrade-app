// Serviços comuns de barbearia no Brasil — usados no onboarding e na tela de
// Serviços para o dono marcar os que oferece e aplicar um preço base.
export interface CommonService {
  name: string
  preselected: boolean
}

export const COMMON_SERVICES: CommonService[] = [
  { name: 'Corte de cabelo', preselected: true },
  { name: 'Corte (degradê/fade)', preselected: true },
  { name: 'Corte social', preselected: true },
  { name: 'Corte na tesoura', preselected: false },
  { name: 'Barba', preselected: true },
  { name: 'Corte + Barba', preselected: true },
  { name: 'Pezinho', preselected: true },
  { name: 'Sobrancelha', preselected: false },
  { name: 'Pigmentação', preselected: false },
  { name: 'Hidratação', preselected: false },
]
