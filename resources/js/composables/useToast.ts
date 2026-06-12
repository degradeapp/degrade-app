import { ref } from 'vue'

export interface Toast {
  id: string
  message: string
  type: 'success' | 'error' | 'info'
  duration: number
}

const toasts = ref<Toast[]>([])

export const useToast = () => {
  const show = (message: string, type: 'success' | 'error' | 'info' = 'info', duration: number = 3000) => {
    const id = `${Date.now()}-${Math.random()}`
    const toast: Toast = { id, message, type, duration }

    toasts.value.push(toast)

    if (duration > 0) {
      setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id)
      }, duration)
    }
  }

  const success = (message: string, duration: number = 3000) => {
    show(message, 'success', duration)
  }

  const error = (message: string, duration: number = 4000) => {
    show(message, 'error', duration)
  }

  const info = (message: string, duration: number = 3000) => {
    show(message, 'info', duration)
  }

  const remove = (id: string) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return {
    toasts,
    show,
    success,
    error,
    info,
    remove,
  }
}
