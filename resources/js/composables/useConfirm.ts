import { ref } from 'vue'

export interface ConfirmDialog {
  id: string
  title: string
  message: string
  confirmText: string
  cancelText: string
  destructive: boolean
  promise: Promise<boolean>
  resolve: (value: boolean) => void
}

const dialogs = ref<ConfirmDialog[]>([])

export const useConfirm = () => {
  const ask = (
    title: string,
    message: string,
    options?: {
      confirmText?: string
      cancelText?: string
      destructive?: boolean
    }
  ): Promise<boolean> => {
    return new Promise((resolve) => {
      const id = `${Date.now()}-${Math.random()}`
      const dialog: ConfirmDialog = {
        id,
        title,
        message,
        confirmText: options?.confirmText || 'Confirmar',
        cancelText: options?.cancelText || 'Cancelar',
        destructive: options?.destructive || false,
        promise: Promise.resolve(false),
        resolve: (value: boolean) => {
          dialogs.value = dialogs.value.filter(d => d.id !== id)
          resolve(value)
        },
      }

      dialogs.value.push(dialog)
    })
  }

  return {
    dialogs,
    ask,
  }
}
