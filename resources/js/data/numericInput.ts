// Helpers de limite para inputs numéricos. maxlength é ignorado em
// <input type="number">, então capamos via JS no @input.

// Preço: até 6 dígitos inteiros + 2 casas decimais (ex.: 999999.99).
export function capPrice(raw: string): string {
  const hasDot = raw.includes('.')
  const [int = '', dec] = raw.split('.')
  let v = int.replace(/\D/g, '').slice(0, 6)
  if (hasDot) {
    v += '.' + (dec ?? '').replace(/\D/g, '').slice(0, 2)
  }
  return v
}
