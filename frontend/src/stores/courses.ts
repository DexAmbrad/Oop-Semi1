import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { request } from '@/lib/api'
import type { Course, Paginated } from '@/types'

export const useCourseStore = defineStore('courses', () => {
  const items = ref<Course[]>([])
  const meta = ref<Paginated<Course>['meta'] | null>(null)
  const loading = ref(false)
  const loaded = ref(false)

  const active = computed(() => items.value.filter((course) => course.status === 'active'))
  const archived = computed(() => items.value.filter((course) => course.status === 'archived'))

  async function fetchAll(params: { status?: string; search?: string; per_page?: number } = {}, force = false) {
    if (loaded.value && !force && !params.status && !params.search) {
      return items.value
    }

    loading.value = true
    try {
      const payload = await request<Paginated<Course>>({
        url: '/courses',
        params: { per_page: 50, ...params },
      })

      items.value = payload.data
      meta.value = payload.meta ?? null
      loaded.value = true

      return items.value
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: number | string) {
    const payload = await request<{ data: Course }>({ url: `/courses/${id}` })
    const course = payload.data

    const index = items.value.findIndex((item) => item.id === course.id)
    if (index >= 0) {
      items.value[index] = { ...items.value[index], ...course }
    } else {
      items.value.push(course)
    }

    return course
  }

  function upsert(course: Course) {
    const index = items.value.findIndex((item) => item.id === course.id)
    if (index >= 0) {
      items.value[index] = { ...items.value[index], ...course }
    } else {
      items.value.unshift(course)
    }
  }

  function remove(id: number) {
    items.value = items.value.filter((course) => course.id !== id)
  }

  function reset() {
    items.value = []
    meta.value = null
    loaded.value = false
  }

  return { items, meta, loading, loaded, active, archived, fetchAll, fetchOne, upsert, remove, reset }
})
