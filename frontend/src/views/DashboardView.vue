<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RefreshCw } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import AdminDashboard from '@/components/dashboard/AdminDashboard.vue'
import TeacherDashboard from '@/components/dashboard/TeacherDashboard.vue'
import StudentDashboard from '@/components/dashboard/StudentDashboard.vue'
import { useAuthStore } from '@/stores/auth'
import { request, errorMessage } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { AdminDashboard as AdminShape, Dashboard, StudentDashboard as StudentShape, TeacherDashboard as TeacherShape } from '@/types'

const auth = useAuthStore()
const toast = useToastStore()
const { user } = storeToRefs(auth)

const data = ref<Dashboard | null>(null)
const loading = ref(true)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
})

const description = computed(() => {
  const role = user.value?.role
  if (role === 'admin') return 'Institution-wide signal: enrolment, load and live activity.'
  if (role === 'teacher') return 'Your classrooms at a glance, plus what still needs your attention.'
  return 'Everything due soon, and how your grades are tracking across classrooms.'
})

async function load() {
  loading.value = true
  try {
    data.value = await request<Dashboard>({ url: '/dashboard' })
  } catch (error) {
    toast.error('Could not load your dashboard', errorMessage(error))
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => user.value?.id, load)
</script>

<template>
  <div>
    <PageHeader
      :eyebrow="`${greeting}${user ? `, ${user.name.split(' ')[0]}` : ''}`"
      title="Your workspace"
      :description="description"
    >
      <template #actions>
        <BaseButton variant="outline" size="sm" :loading="loading" @click="load">
          <template #icon><RefreshCw class="h-3.5 w-3.5" /></template>
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <div v-if="loading && !data" class="space-y-6">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="n in 4" :key="n" class="panel p-5"><SkeletonBlock :rows="2" /></div>
      </div>
      <div class="panel p-6"><SkeletonBlock :rows="5" /></div>
    </div>

    <template v-else-if="data">
      <AdminDashboard v-if="data.role === 'admin'" :data="data as AdminShape" />
      <TeacherDashboard v-else-if="data.role === 'teacher'" :data="data as TeacherShape" />
      <StudentDashboard v-else :data="data as StudentShape" />
    </template>
  </div>
</template>
