<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  Activity,
  ChevronLeft,
  ChevronRight,
  GraduationCap,
  MessageSquare,
  RefreshCw,
  Search,
  Settings2,
  ShieldCheck,
  Sparkles,
  UserPlus,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useToastStore } from '@/stores/toast'
import { errorMessage, request } from '@/lib/api'
import { ROLE_TONE } from '@/lib/accents'
import { dayjs, formatDate, fromNow, humanAction } from '@/lib/format'
import type { Accent, Paginated, Role, User } from '@/types'

interface ActivityEntry {
  id: number
  action: string
  subject: string | null
  properties: Record<string, unknown> | null
  user: User | null
  created_at: string | null
}

const toast = useToastStore()

const entries = ref<ActivityEntry[]>([])
const meta = ref<Paginated<ActivityEntry>['meta'] | null>(null)
const loading = ref(true)
const page = ref(1)
const search = ref('')
const scope = ref<'all' | 'courses' | 'users' | 'grading' | 'messages'>('all')

const scopes = [
  { value: 'all', label: 'Everything' },
  { value: 'courses', label: 'Classrooms' },
  { value: 'users', label: 'People' },
  { value: 'grading', label: 'Grading' },
  { value: 'messages', label: 'Messages' },
] as const

const scopePrefix: Record<typeof scope.value, string | undefined> = {
  all: undefined,
  courses: 'course',
  users: 'user',
  grading: 'submission',
  messages: 'message',
}

const groups = computed(() => {
  const map = new Map<string, ActivityEntry[]>()

  for (const entry of entries.value) {
    const day = dayjs(entry.created_at).format('YYYY-MM-DD')
    map.set(day, [...(map.get(day) ?? []), entry])
  }

  return [...map.entries()]
})

function visual(action: string): { icon: typeof Activity; tone: Accent } {
  if (action.startsWith('course')) return { icon: GraduationCap, tone: 'violet' }
  if (action.startsWith('user')) return { icon: UserPlus, tone: 'sky' }
  if (action.startsWith('submission') || action.startsWith('assignment')) return { icon: ShieldCheck, tone: 'emerald' }
  if (action.startsWith('message')) return { icon: MessageSquare, tone: 'rose' }
  return { icon: Activity, tone: 'amber' }
}

async function load() {
  loading.value = true

  try {
    const payload = await request<Paginated<ActivityEntry>>({
      url: '/admin/activity',
      params: {
        page: page.value,
        per_page: 20,
        search: search.value || undefined,
        action: scopePrefix[scope.value],
      },
    })

    entries.value = payload.data
    meta.value = payload.meta ?? null
  } catch (error) {
    toast.error('Could not load the activity log', errorMessage(error))
  } finally {
    loading.value = false
  }
}

function changePage(step: number) {
  if (!meta.value) return
  const next = page.value + step
  if (next < 1 || next > meta.value.last_page) return
  page.value = next
  load()
}

let debounce: number | undefined

watch(search, () => {
  window.clearTimeout(debounce)
  debounce = window.setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

watch(scope, () => {
  page.value = 1
  load()
})

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      eyebrow="Administration"
      title="System activity"
      description="An audit trail of everything that happened across the platform."
    >
      <template #actions>
        <BaseButton size="sm" variant="outline" :loading="loading" @click="load">
          <template #icon><RefreshCw class="h-3.5 w-3.5" /></template>
          Refresh
        </BaseButton>
      </template>
    </PageHeader>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="flex flex-wrap items-center gap-1 rounded-full border border-white/8 bg-white/3 p-1">
        <button
          v-for="option in scopes"
          :key="option.value"
          class="rounded-full px-3 py-1.5 text-xs font-medium transition"
          :class="scope === option.value ? 'bg-white/12 text-white' : 'text-fog-400 hover:text-white'"
          @click="scope = option.value"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="relative ml-auto min-w-[13rem] flex-1 sm:max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-fog-500" />
        <input v-model="search" type="search" placeholder="Search actions" class="field h-9 py-0 pl-9 text-sm" />
      </div>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="n in 6" :key="n" class="panel p-4"><SkeletonBlock :rows="1" /></div>
    </div>

    <EmptyState
      v-else-if="!entries.length"
      :icon="Activity"
      title="Nothing logged yet"
      description="Actions across classrooms, grading, and accounts will appear here as they happen."
    />

    <div v-else class="space-y-6">
      <section v-for="[day, rows] in groups" :key="day">
        <h2 class="mb-2.5 flex items-center gap-2 text-xs font-semibold tracking-wide text-fog-500 uppercase">
          <span>{{ formatDate(day, 'dddd D MMMM') }}</span>
          <span class="chip">{{ rows.length }}</span>
        </h2>

        <ul class="space-y-2">
          <li
            v-for="entry in rows"
            :key="entry.id"
            class="panel flex items-start gap-3.5 px-4 py-3.5 transition hover:border-white/16"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-xl2"
              :class="{
                'bg-violet-400/12 text-violet-300': visual(entry.action).tone === 'violet',
                'bg-sky-400/12 text-sky-300': visual(entry.action).tone === 'sky',
                'bg-emerald-400/12 text-emerald-300': visual(entry.action).tone === 'emerald',
                'bg-rose-400/12 text-rose-300': visual(entry.action).tone === 'rose',
                'bg-amber-400/12 text-amber-300': visual(entry.action).tone === 'amber',
              }"
            >
              <component :is="visual(entry.action).icon" class="h-4 w-4" />
            </span>

            <div class="min-w-0 flex-1">
              <p class="text-sm text-fog-200">
                <span class="font-medium text-white">{{ entry.user?.name ?? 'System' }}</span>
                {{ humanAction(entry.action).toLowerCase() }}
                <span v-if="entry.subject" class="font-medium text-white">{{ entry.subject }}</span>
              </p>

              <div v-if="entry.properties && Object.keys(entry.properties).length" class="mt-1.5 flex flex-wrap gap-1.5">
                <span
                  v-for="(value, key) in entry.properties"
                  :key="key"
                  class="rounded-full border border-white/8 bg-white/3 px-2 py-0.5 text-[0.62rem] text-fog-400"
                >
                  <span class="text-fog-500">{{ key }}:</span> {{ String(value) }}
                </span>
              </div>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-1.5">
              <span class="text-[0.66rem] text-fog-500">{{ fromNow(entry.created_at) }}</span>
              <BaseBadge
                v-if="entry.user"
                :tone="ROLE_TONE[(entry.user.role as Role) ?? 'student']"
                size="xs"
              >
                {{ entry.user.role }}
              </BaseBadge>
            </div>
          </li>
        </ul>
      </section>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-5 flex items-center justify-between">
      <p class="text-[0.7rem] text-fog-500">
        Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} events
      </p>
      <div class="flex items-center gap-1">
        <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white disabled:opacity-30" :disabled="page <= 1" @click="changePage(-1)">
          <ChevronLeft class="h-4 w-4" />
        </button>
        <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white disabled:opacity-30" :disabled="page >= meta.last_page" @click="changePage(1)">
          <ChevronRight class="h-4 w-4" />
        </button>
      </div>
    </div>

    <p class="mt-6 flex items-center justify-center gap-1.5 text-[0.68rem] text-fog-600">
      <Sparkles class="h-3 w-3" />
      <Settings2 class="h-3 w-3" />
      Logs are retained for 90 days.
    </p>
  </div>
</template>
