import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getToken, request, setToken } from '@/lib/api'
import type { AuthPayload, User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const stats = ref<{ courses_count: number; taught_courses_count: number; unread_messages: number } | null>(null)
  const token = ref<string | null>(getToken())
  const ready = ref(false)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isTeacher = computed(() => user.value?.role === 'teacher')
  const isStudent = computed(() => user.value?.role === 'student')
  const role = computed(() => user.value?.role ?? null)

  async function login(email: string, password: string) {
    const payload = await request<AuthPayload>({
      url: '/auth/login',
      method: 'POST',
      data: { email, password },
    })

    setToken(payload.token)
    token.value = payload.token
    user.value = payload.user
    await refresh()

    return payload.user
  }

  async function register(data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    role: 'teacher' | 'student'
    headline?: string
  }) {
    const payload = await request<AuthPayload>({
      url: '/auth/register',
      method: 'POST',
      data,
    })

    setToken(payload.token)
    token.value = payload.token
    user.value = payload.user
    await refresh()

    return payload.user
  }

  async function bootstrap() {
    if (ready.value) return

    if (!token.value) {
      ready.value = true
      return
    }

    try {
      await refresh()
    } catch {
      logout()
    } finally {
      ready.value = true
    }
  }

  async function refresh() {
    const payload = await request<{
      user: User
      stats: { courses_count: number; taught_courses_count: number; unread_messages: number }
    }>({ url: '/auth/me' })

    user.value = payload.user
    stats.value = payload.stats
    return payload.user
  }

  async function updateProfile(data: Partial<Pick<User, 'name' | 'email' | 'headline' | 'phone'>>) {
    const payload = await request<{ message: string; user: User }>({
      url: '/auth/profile',
      method: 'PATCH',
      data,
    })

    user.value = payload.user
    return payload.message
  }

  async function changePassword(current_password: string, password: string, password_confirmation: string) {
    const payload = await request<{ message: string }>({
      url: '/auth/password',
      method: 'PATCH',
      data: { current_password, password, password_confirmation },
    })

    return payload.message
  }

  function logout() {
    setToken(null)
    token.value = null
    user.value = null
    stats.value = null
  }

  async function logoutRemote() {
    try {
      await request({ url: '/auth/logout', method: 'POST' })
    } catch {
      // token may already be invalid — clearing locally is enough
    } finally {
      logout()
    }
  }

  return {
    user,
    stats,
    token,
    ready,
    loading,
    isAuthenticated,
    isAdmin,
    isTeacher,
    isStudent,
    role,
    login,
    register,
    bootstrap,
    refresh,
    updateProfile,
    changePassword,
    logout,
    logoutRemote,
  }
})
