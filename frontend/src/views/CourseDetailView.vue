<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  BookMarked,
  Check,
  ClipboardList,
  Copy,
  FolderOpen,
  Megaphone,
  Settings2,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/courses'
import { useToastStore } from '@/stores/toast'
import { errorMessage } from '@/lib/api'
import { accentOf, courseIcon } from '@/lib/accents'
import { formatDate } from '@/lib/format'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import StreamTab from '@/components/classroom/StreamTab.vue'
import WorkTab from '@/components/classroom/WorkTab.vue'
import PeopleTab from '@/components/classroom/PeopleTab.vue'
import GradesTab from '@/components/classroom/GradesTab.vue'
import ResourcesTab from '@/components/classroom/ResourcesTab.vue'
import SettingsTab from '@/components/classroom/SettingsTab.vue'
import type { Course } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courses = useCourseStore()
const toast = useToastStore()

const course = ref<Course | null>(null)
const loading = ref(true)
const copied = ref(false)

const courseId = computed(() => Number(route.params.id))

const canTeach = computed(() => !!course.value && (course.value.is_teacher || auth.isAdmin))
const theme = computed(() => accentOf(course.value?.accent))

type TabKey = 'stream' | 'work' | 'people' | 'grades' | 'resources' | 'settings'

const tabs = computed(() => {
  const base: { key: TabKey; label: string; icon: typeof Megaphone; count?: number }[] = [
    { key: 'stream', label: 'Stream', icon: Megaphone },
    { key: 'work', label: 'Work', icon: ClipboardList, count: course.value?.assignments_count },
    { key: 'people', label: 'People', icon: Users, count: course.value?.students_count },
    { key: 'grades', label: 'Grades', icon: BookMarked },
    { key: 'resources', label: 'Resources', icon: FolderOpen, count: course.value?.materials_count },
  ]

  if (canTeach.value) {
    base.push({ key: 'settings', label: 'Settings', icon: Settings2 })
  }

  return base
})

const activeTab = computed<TabKey>(() => {
  const requested = route.query.tab as TabKey | undefined
  const valid = tabs.value.some((tab) => tab.key === requested)
  return valid ? (requested as TabKey) : 'stream'
})

function setTab(key: TabKey) {
  router.replace({ query: { ...route.query, tab: key } })
}

async function load() {
  loading.value = true
  try {
    course.value = await courses.fetchOne(courseId.value)
  } catch (error) {
    toast.error('Could not open this classroom', errorMessage(error))
    router.replace({ name: 'courses' })
  } finally {
    loading.value = false
  }
}

async function copyCode() {
  if (!course.value) return
  try {
    await navigator.clipboard.writeText(course.value.room_code)
    copied.value = true
    toast.success('Room code copied', course.value.room_code)
    window.setTimeout(() => (copied.value = false), 2000)
  } catch {
    toast.info('Room code', course.value.room_code)
  }
}

async function refreshCourse() {
  try {
    course.value = await courses.fetchOne(courseId.value)
  } catch {
    // keep previous snapshot
  }
}

function handleUpdated() {
  refreshCourse()
}

onMounted(load)
watch(courseId, load)
</script>

<template>
  <div v-if="loading && !course">
    <div class="panel mb-6 p-6"><SkeletonBlock :rows="3" /></div>
    <div class="panel p-6"><SkeletonBlock :rows="6" /></div>
  </div>

  <div v-else-if="course">
    <!-- Hero -->
    <div class="panel relative mb-6 overflow-hidden">
      <div class="relative h-36 sm:h-44" :style="{ background: theme.gradient }">
        <div class="absolute inset-0 opacity-25 mix-blend-overlay" style="background-image: radial-gradient(circle at 78% 22%, #fff, transparent 46%)" />
        <div class="absolute -right-10 -top-16 h-52 w-52 rounded-full bg-black/20 blur-3xl" />

        <RouterLink
          :to="{ name: 'courses' }"
          class="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full bg-black/25 px-3 py-1.5 text-[0.7rem] font-medium text-white backdrop-blur-sm transition hover:bg-black/40"
        >
          <ArrowLeft class="h-3.5 w-3.5" />
          Classrooms
        </RouterLink>

        <div class="absolute bottom-4 right-4 flex items-center gap-2">
          <button
            class="inline-flex items-center gap-2 rounded-full bg-black/25 px-3 py-1.5 font-mono text-[0.72rem] font-semibold text-white backdrop-blur-sm transition hover:bg-black/40"
            @click="copyCode"
          >
            <component :is="copied ? Check : Copy" class="h-3.5 w-3.5" />
            {{ course.room_code }}
          </button>
        </div>
      </div>

      <div class="relative px-5 pb-5 pt-4 sm:px-6">
        <div class="-mt-12 mb-4 flex flex-wrap items-end gap-4">
          <div
            class="grid h-20 w-20 shrink-0 place-items-center rounded-xl3 bg-ink-880 text-white ring-4 ring-ink-880"
            :style="{ background: theme.gradient }"
          >
            <component :is="courseIcon(course.icon)" class="h-8 w-8" />
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip font-mono">{{ course.code }}</span>
              <span v-if="course.term" class="chip">{{ course.term }}</span>
              <span v-if="course.status === 'archived'" class="chip border-amber-400/25 text-amber-300">Archived</span>
            </div>
            <h1 class="mt-2 font-display text-xl font-semibold text-white sm:text-2xl">{{ course.title }}</h1>
            <p class="mt-1 text-sm text-fog-400">
              {{ course.subject ?? 'General studies' }} · {{ course.students_count }} learners
              <span v-if="course.starts_at"> · {{ formatDate(course.starts_at) }} → {{ formatDate(course.ends_at) }}</span>
            </p>
          </div>

          <div v-if="course.teacher" class="flex items-center gap-3 rounded-xl2 border border-white/8 bg-white/3 px-3 py-2">
            <BaseAvatar :name="course.teacher.name" :initials="course.teacher.initials" :accent="course.accent" size="sm" />
            <div class="leading-tight">
              <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Instructor</p>
              <p class="text-xs font-semibold text-white">{{ course.teacher.name }}</p>
            </div>
          </div>
        </div>

        <p v-if="course.description" class="max-w-3xl text-sm leading-relaxed text-fog-300">{{ course.description }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6 flex gap-1 overflow-x-auto border-b border-white/7 pb-px">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="relative inline-flex shrink-0 items-center gap-2 px-4 py-3 text-sm font-medium transition"
        :class="activeTab === tab.key ? 'text-white' : 'text-fog-400 hover:text-fog-100'"
        @click="setTab(tab.key)"
      >
        <component :is="tab.icon" class="h-4 w-4" />
        {{ tab.label }}
        <span
          v-if="tab.count"
          class="rounded-full bg-white/8 px-1.5 py-0.5 text-[0.62rem] font-semibold"
          :class="activeTab === tab.key ? 'bg-white/16 text-white' : 'text-fog-400'"
        >
          {{ tab.count }}
        </span>
        <span
          v-if="activeTab === tab.key"
          class="absolute inset-x-2 -bottom-px h-0.5 rounded-full"
          :style="{ background: theme.gradient }"
        />
      </button>
    </div>

    <StreamTab v-if="activeTab === 'stream'" :course="course" :can-teach="canTeach" @changed="refreshCourse" />
    <WorkTab v-else-if="activeTab === 'work'" :course="course" :can-teach="canTeach" @changed="refreshCourse" />
    <PeopleTab v-else-if="activeTab === 'people'" :course="course" :can-teach="canTeach" />
    <GradesTab v-else-if="activeTab === 'grades'" :course="course" :can-teach="canTeach" />
    <ResourcesTab v-else-if="activeTab === 'resources'" :course="course" :can-teach="canTeach" @changed="refreshCourse" />
    <SettingsTab
      v-else-if="activeTab === 'settings'"
      :course="course"
      :can-teach="canTeach"
      :is-enrolled="course.my_enrollment.is_enrolled"
      @changed="handleUpdated"
    />
  </div>
</template>
