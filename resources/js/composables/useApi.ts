const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const csrfMeta = () =>
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

export interface ApiResponse<T = any> {
  ok: boolean
  status: number
  data: T | null
  errors?: Record<string, string[]> | null
  message?: string | null
}

const baseHeaders = (json = true): Record<string, string> => {
  const h: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const token = xsrf() || csrfMeta()
  if (token) h['X-XSRF-TOKEN'] = token
  if (token && !xsrf()) h['X-CSRF-TOKEN'] = token
  if (json) h['Content-Type'] = 'application/json'
  return h
}

const parse = async <T>(res: Response): Promise<ApiResponse<T>> => {
  const text = await res.text()
  let body: any = null
  if (text) {
    try {
      body = JSON.parse(text)
    } catch {
      body = text
    }
  }

  const data = body && typeof body === 'object' && 'data' in body ? body.data : body
  const errors = body && typeof body === 'object' && 'errors' in body ? body.errors : null
  const message = body && typeof body === 'object' && 'message' in body ? body.message : null

  return {
    ok: res.ok,
    status: res.status,
    data: data as T,
    errors,
    message,
  }
}

export const useApi = () => {
  const request = async <T = any>(
    method: string,
    path: string,
    payload?: any,
    init?: RequestInit
  ): Promise<ApiResponse<T>> => {
    const opts: RequestInit = {
      method,
      credentials: 'same-origin',
      headers: { ...baseHeaders(payload !== undefined), ...(init?.headers as any) },
      ...init,
    }
    if (payload !== undefined) {
      opts.body = JSON.stringify(payload)
    }

    try {
      const res = await fetch(path, opts)
      const parsed = await parse<T>(res)
      // Sessão perdida em qualquer chamada de API → volta pro login (evita tela "fantasma").
      if (parsed.status === 401 && typeof window !== 'undefined' && !window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
      return parsed
    } catch {
      return { ok: false, status: 0, data: null, message: 'Falha de rede.' }
    }
  }

  return {
    get: <T = any>(path: string, init?: RequestInit) => request<T>('GET', path, undefined, init),
    post: <T = any>(path: string, payload?: any, init?: RequestInit) => request<T>('POST', path, payload, init),
    put: <T = any>(path: string, payload?: any, init?: RequestInit) => request<T>('PUT', path, payload, init),
    patch: <T = any>(path: string, payload?: any, init?: RequestInit) => request<T>('PATCH', path, payload, init),
    delete: <T = any>(path: string, init?: RequestInit) => request<T>('DELETE', path, undefined, init),
  }
}
