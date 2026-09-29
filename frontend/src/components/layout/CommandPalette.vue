<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
  Activity,
  ArrowRight,
  BookOpen,
  CalendarDays,
  LayoutDashboard,
  MessagesSquare,
  Search,
  Settings,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/courses'
import { accentOf } from '@/lib/accents'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const router = useRouter()
const auth = useAuthStore()
const courses = useCourseStore()

const { user } = storeToRefs(auth)
const { items } = storeToRefs(courses)

const query = ref('')
const cursor = ref(0)
const input = ref<HTMLInputElement | null>(null)

interface Entry {
  id: string
  label: string
  hint?: string
  icon: typeof BookOpen
  accent?: string
  run: () => void
}

const navEntries = computed<Entry[]>(() => {
  const base: Entry[] = [
    { id: 'nav-dashboard', label: 'Go to Overview', icon: LayoutDashboard, run: () => router.push({ name: 'dashboard' }) },
    { id: 'nav-courses', label: 'Go to Classrooms', icon: BookOpen, run: () => router.push({ name: 'courses' }) },
    { id: 'nav-calendar', label: 'Go to Timeline', icon: CalendarDays, run: () => router.push({ name: 'calendar' }) },
    { id: 'nav-messages', label: 'Go to Messages', icon: MessagesSquare, run: () => router.push({ name: 'messages' }) },
    { id: 'nav-profile', label: 'Open your profile', icon: Settings, run: () => router.push({ name: 'profile' }) },
  ]

  if (user.value?.role === 'admin') {
    base.push(
      { id: 'nav-admin-users', label: 'People directory', icon: Users, hint: 'Admin', run: () => router.push({ name: 'admin-users' }) },
      { id: 'nav-admin-activity', label: 'System activity', icon: Activity, hint: 'Admin', run: () => router.push({ name: 'admin-activity' }) },
    )
  }

  return base
})

const entries = computed<Entry[]>(() => {
  const term = query.value.trim().toLowerCase()

  const courseEntries: Entry[] = items.value.map((course) => ({
    id: `course-${course.id}`,
    label: course.title,
    hint: `${course.code} · ${course.subject ?? 'General'}`,
    icon: BookOpen,
    accent: course.accent,
    run: () => router.push({ name: 'course', params: { id: course.id } }),
  }))

  const pool = [...navEntries.value, ...courseEntries]

  if (!term) return pool.slice(0, 9)

  return pool
    .filter((entry) => `${entry.label} ${entry.hint ?? ''}`.toLowerCase().includes(term))
    .slice(0, 10)
})

watch(
  () => props.open,
  async (open) => {
    if (open) {
      query.value = ''
      cursor.value = 0
      await nextTick()
      input.value?.focus()
    }
  },
)

watch(query, () => {
  cursor.value = 0
})

function activate(entry: Entry | undefined) {
  if (!entry) return
  entry.run()
  emit('close')
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    cursor.value = (cursor.value + 1) % Math.max(1, entries.value.length)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    cursor.value = (cursor.value - 1 + entries.value.length) % Math.max(1, entries.value.length)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    activate(entries.value[cursor.value])
  } else if (event.key === 'Escape') {
    emit('close')
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[12vh]">
        <div class="absolute inset-0 bg-ink-950/85 backdrop-blur-md" @click="emit('close')" />

        <div class="panel panel-glow anim-pop relative z-10 w-full max-w-lg overflow-hidden">
          <div class="flex items-center gap-3 border-b border-white/7 px-4 py-3.5">
            <Search class="h-4 w-4 shrink-0 text-fog-500" />
            <input
              ref="input"
              v-model="query"
              class="w-full bg-transparent text-sm text-white outline-none"
              placeholder="Jump to a classroom, page or action…"
              @keydown="onKeydown"
            />
            <kbd class="shrink-0 rounded-md border border-white/10 bg-white/5 px-1.5 py-0.5 text-[0.62rem] text-fog-400">
              ESC
            </kbd>
          </div>

          <div class="max-h-[52vh] overflow-y-auto p-2">
            <p v-if="!entries.length" class="px-3 py-8 text-center text-sm text-fog-500">No matches found.</p>

            <button
              v-for="(entry, index) in entries"
              :key="entry.id"
              class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
              :class="index === cursor ? 'bg-white/8' : 'hover:bg-white/5'"
              @mouseenter="cursor = index"
              @click="activate(entry)"
            >
              <span
                class="grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-white/8"
                :style="entry.accent ? { background: accentOf(entry.accent).soft, color: accentOf(entry.accent).text } : undefined"
              >
                <component :is="entry.icon" class="h-3.5 w-3.5" />
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-white">{{ entry.label }}</span>
                <span v-if="entry.hint" class="block truncate text-[0.7rem] text-fog-500">{{ entry.hint }}</span>
              </span>
              <ArrowRight class="h-3.5 w-3.5 shrink-0" :class="index === cursor ? 'text-violet-300' : 'text-fog-500'" />
            </button>
          </div>

          <div class="flex items-center justify-between border-t border-white/7 px-4 py-2.5 text-[0.68rem] text-fog-500">
            <span>Navigate with ↑ ↓ · open with ↵</span>
            <span>Lumen Classroom</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
