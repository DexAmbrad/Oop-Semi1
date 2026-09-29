import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastTone = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  title: string
  description?: string
  tone: ToastTone
}

let counter = 0

export const useToastStore = defineStore('toast', () => {
  const items = ref<Toast[]>([])

  function push(title: string, tone: ToastTone = 'info', description?: string) {
    const id = ++counter
    items.value.push({ id, title, description, tone })

    window.setTimeout(() => dismiss(id), 5200)

    return id
  }

  function dismiss(id: number) {
    items.value = items.value.filter((toast) => toast.id !== id)
  }

  const success = (title: string, description?: string) => push(title, 'success', description)
  const error = (title: string, description?: string) => push(title, 'error', description)
  const info = (title: string, description?: string) => push(title, 'info', description)

  return { items, push, dismiss, success, error, info }
})
