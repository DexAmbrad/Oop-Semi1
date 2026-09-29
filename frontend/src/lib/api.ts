import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'

const TOKEN_KEY = 'lumen.token'

export const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

type UnauthorizedHandler = () => void
let onUnauthorized: UnauthorizedHandler | null = null

export function setUnauthorizedHandler(handler: UnauthorizedHandler) {
  onUnauthorized = handler
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      onUnauthorized?.()
    }
    return Promise.reject(error)
  },
)

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export function errorMessage(error: unknown): string {
  if (error instanceof AxiosError) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined

    if (data?.errors) {
      const first = Object.values(data.errors)[0]
      if (first?.length) return first[0]
    }

    if (data?.message) return data.message
    if (error.code === 'ERR_NETWORK') return 'Cannot reach the server. Is the API running?'
  }

  if (error instanceof Error) return error.message

  return 'Something went wrong. Please try again.'
}

export function fieldErrors(error: unknown): Record<string, string> {
  if (error instanceof AxiosError) {
    const data = error.response?.data as { errors?: Record<string, string[]> } | undefined
    if (data?.errors) {
      return Object.fromEntries(Object.entries(data.errors).map(([key, value]) => [key, value[0]]))
    }
  }
  return {}
}

export async function request<T>(config: AxiosRequestConfig): Promise<T> {
  const response = await api.request<T>(config)
  return response.data
}
