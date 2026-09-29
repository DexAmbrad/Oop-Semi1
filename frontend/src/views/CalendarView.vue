<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock,
  Filter,
  LayoutList,
  Sparkles,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, request } from '@/lib/api'
import { accentOf } from '@/lib/accents'
import { dayjs, formatDate, fromNow } from '@/lib/format'
import type { Accent, Assignment, AssignmentType, Course } from '@/types'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const items = ref<Assignment[]>([])
const courses = ref<Course[]>([])
const loading = ref(true)
const mode = ref<'month' | 'agenda'>('month')
const cursor = ref(dayjs().startOf('month'))
const courseFilter = ref<number | 'all'>('all')
const typeFilter = ref<'all' | AssignmentType>('all')
const selectedDay = ref<string | null>(dayjs().format('YYYY-MM-DD'))

const typeTone: Record<AssignmentType, 'violet' | 'sky' | 'emerald' | 'amber'> = {
  assignment: 'violet',
  quiz: 'sky',
  project: 'emerald',
  reading: 'amber',
}

const filtered = computed(() =>
  items.value.filter((item) => {
    if (courseFilter.value !== 'all' && item.course_id !== courseFilter.value) return false
    if (typeFilter.value !== 'all' && item.type !== typeFilter.value) return false
    return true
  }),
)

const dated = computed(() => filtered.value.filter((item) => item.due_at))

const byDay = computed(() => {
  const map = new Map<string, Assignment[]>()

  for (const item of dated.value) {
    const key = dayjs(item.due_at).format('YYYY-MM-DD')
    map.set(key, [...(map.get(key) ?? []), item])
  }

  return map
})

const weeks = computed(() => {
  const start = cursor.value.startOf('month').startOf('week')
  const grid: { key: string; date: string; inMonth: boolean; isToday: boolean }[] = []

  for (let i = 0; i < 42; i += 1) {
    const date = start.add(i, 'day')
    grid.push({
      key: date.format('YYYY-MM-DD'),
      date: date.format('D'),
      inMonth: date.month() === cursor.value.month(),
      isToday: date.isSame(dayjs(), 'day'),
    })
  }

  return grid
})

const agenda = computed(() => {
  const rows = [...byDay.value.entries()].sort(([a], [b]) => a.localeCompare(b))
  const selected = selectedDay.value
  return selected ? rows.filter(([key]) => key === selected) : rows
})

const undated = computed(() => filtered.value.filter((item) => !item.due_at))

const stats = computed(() => ({
  week: dated.value.filter((item) => dayjs(item.due_at).isAfter(dayjs().subtract(1, 'day')) && dayjs(item.due_at).isBefore(dayjs().add(8, 'day'))).length,
  overdue: filtered.value.filter((item) => item.is_overdue).length,
  submitted: filtered.value.filter((item) => item.my_submission?.submitted_at).length,
}))

const monthLabel = computed(() => cursor.value.format('MMMM YYYY'))

async function load() {
  loading.value = true

  try {
    const [assignments, classrooms] = await Promise.all([
      request<{ data: Assignment[] }>({ url: '/assignments' }),
      request<{ data: Course[] }>({ url: '/courses', params: { per_page: 50 } }),
    ])

    items.value = assignments.data
    courses.value = classrooms.data
  } catch (error) {
    toast.error('Could not load your timeline', errorMessage(error))
  } finally {
    loading.value = false
  }
}

function shiftMonth(step: number) {
  cursor.value = cursor.value.add(step, 'month')
  selectedDay.value = null
}

function accentOfCourse(item: Assignment): Accent {
  return item.course?.accent ?? 'violet'
}

function open(item: Assignment) {
  router.push({ name: 'work', params: { courseId: item.course_id, workId: item.id } })
}

function clearFilters() {
  courseFilter.value = 'all'
  typeFilter.value = 'all'
  selectedDay.value = null
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      eyebrow="Timeline"
      title="Everything due, in one place"
      description="Deadlines across every classroom you are part of, colour-coded by course."
    >
      <template #actions>
        <div class="flex items-center gap-1 rounded-full border border-white/8 bg-white/3 p-1">
          <button
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition"
            :class="mode === 'month' ? 'bg-white/12 text-white' : 'text-fog-400 hover:text-white'"
            @click="mode = 'month'"
          >
            <CalendarDays class="h-3.5 w-3.5" />Month
          </button>
          <button
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition"
            :class="mode === 'agenda' ? 'bg-white/12 text-white' : 'text-fog-400 hover:text-white'"
            @click="mode = 'agenda'"
          >
            <LayoutList class="h-3.5 w-3.5" />Agenda
          </button>
        </div>
      </template>
    </PageHeader>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Due this week</p>
        <p class="mt-1 font-display text-2xl font-semibold text-white">{{ stats.week }}</p>
      </div>
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Overdue</p>
        <p class="mt-1 font-display text-2xl font-semibold" :class="stats.overdue ? 'text-rose-300' : 'text-white'">
          {{ stats.overdue }}
        </p>
      </div>
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">
          {{ auth.isTeacher ? 'Submitted by class' : 'You submitted' }}
        </p>
        <p class="mt-1 font-display text-2xl font-semibold text-emerald-300">{{ stats.submitted }}</p>
      </div>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-2">
      <div class="flex items-center gap-2">
        <Filter class="h-3.5 w-3.5 text-fog-500" />
        <select v-model="courseFilter" class="field h-9 w-auto py-0 text-xs">
          <option value="all">All classrooms</option>
          <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.code }} · {{ course.title }}</option>
        </select>
      </div>

      <select v-model="typeFilter" class="field h-9 w-auto py-0 text-xs">
        <option value="all">Every type</option>
        <option value="assignment">Assignments</option>
        <option value="quiz">Quizzes</option>
        <option value="project">Projects</option>
        <option value="reading">Readings</option>
      </select>

      <BaseButton v-if="courseFilter !== 'all' || typeFilter !== 'all' || selectedDay" size="sm" variant="ghost" @click="clearFilters">
        Reset
      </BaseButton>
    </div>

    <div v-if="loading" class="space-y-4">
      <div class="panel p-6"><SkeletonBlock :rows="4" /></div>
      <div class="panel p-6"><SkeletonBlock :rows="6" /></div>
    </div>

    <EmptyState
      v-else-if="!filtered.length"
      :icon="CalendarDays"
      title="No deadlines on the horizon"
      description="Once your teachers publish coursework with due dates, it will show up here."
    />

    <div v-else class="space-y-5">
      <!-- Month grid -->
      <section v-if="mode === 'month'" class="panel overflow-hidden">
        <header class="flex items-center justify-between border-b border-white/7 px-5 py-4">
          <h2 class="font-display text-base font-semibold text-white">{{ monthLabel }}</h2>
          <div class="flex items-center gap-1">
            <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white" @click="shiftMonth(-1)">
              <ChevronLeft class="h-4 w-4" />
            </button>
            <BaseButton size="sm" variant="ghost" @click="cursor = dayjs().startOf('month'); selectedDay = null">Today</BaseButton>
            <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white" @click="shiftMonth(1)">
              <ChevronRight class="h-4 w-4" />
            </button>
          </div>
        </header>

        <div class="grid grid-cols-7 border-b border-white/7 text-center text-[0.64rem] tracking-wide text-fog-500 uppercase">
          <span v-for="label in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="label" class="py-2.5">{{ label }}</span>
        </div>

        <div class="grid grid-cols-7">
          <button
            v-for="cell in weeks"
            :key="cell.key"
            class="group relative min-h-[4.5rem] border-r border-b border-white/4 p-2 text-left transition last:border-r-0 hover:bg-white/3"
            :class="[
              cell.inMonth ? '' : 'opacity-35',
              selectedDay === cell.key ? 'bg-violet-400/8' : '',
            ]"
            @click="selectedDay = selectedDay === cell.key ? null : cell.key"
          >
            <span
              class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold"
              :class="cell.isToday ? 'bg-violet-500 text-white' : 'text-fog-300'"
            >
              {{ cell.date }}
            </span>

            <div class="mt-1 flex flex-wrap gap-1">
              <span
                v-for="item in (byDay.get(cell.key) ?? []).slice(0, 3)"
                :key="item.id"
                class="h-1.5 w-1.5 rounded-full"
                :style="{ background: accentOf(accentOfCourse(item)).base }"
                :title="item.title"
              />
              <span
                v-if="(byDay.get(cell.key) ?? []).length > 3"
                class="text-[0.58rem] leading-none text-fog-500"
              >
                +{{ (byDay.get(cell.key) ?? []).length - 3 }}
              </span>
            </div>
          </button>
        </div>
      </section>

      <!-- Agenda -->
      <section class="space-y-4">
        <div v-if="!agenda.length" class="panel px-5 py-10 text-center text-sm text-fog-500">
          <template v-if="selectedDay">
            Nothing due on {{ formatDate(selectedDay, 'dddd D MMMM') }}.
          </template>
          <template v-else-if="mode === 'agenda'">Nothing scheduled in this view.</template>
        </div>

        <div v-for="[day, rows] in agenda" :key="day" class="panel overflow-hidden">
          <header class="flex items-center gap-3 border-b border-white/7 bg-white/2 px-5 py-3">
            <div class="text-center">
              <p class="text-[0.62rem] tracking-wide text-fog-500 uppercase">{{ dayjs(day).format('ddd') }}</p>
              <p
                class="font-display text-lg font-semibold"
                :class="dayjs(day).isSame(dayjs(), 'day') ? 'text-violet-300' : 'text-white'"
              >
                {{ dayjs(day).format('D') }}
              </p>
            </div>
            <div>
              <p class="text-sm font-semibold text-white">{{ dayjs(day).format('dddd D MMMM') }}</p>
              <p class="text-[0.68rem] text-fog-500">{{ fromNow(dayjs(day).format('YYYY-MM-DD')) }}</p>
            </div>
            <span class="chip ml-auto">{{ rows.length }}</span>
          </header>

          <ul class="divide-y divide-white/5">
            <li
              v-for="item in rows"
              :key="item.id"
              class="cursor-pointer px-5 py-3.5 transition hover:bg-white/3"
              @click="open(item)"
            >
              <div class="flex flex-wrap items-center gap-3">
                <span
                  class="h-8 w-1 shrink-0 rounded-full"
                  :style="{ background: accentOf(accentOfCourse(item)).gradient }"
                />
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate text-sm font-medium text-white">{{ item.title }}</p>
                    <BaseBadge :tone="typeTone[item.type]" size="xs">{{ item.type }}</BaseBadge>
                    <BaseBadge v-if="item.is_overdue" tone="rose" size="xs">Overdue</BaseBadge>
                    <BaseBadge v-else-if="item.my_submission?.submitted_at" tone="emerald" size="xs">
                      <template #icon><CheckCircle2 class="h-2.5 w-2.5" /></template>Submitted
                    </BaseBadge>
                  </div>
                  <p class="mt-0.5 truncate text-[0.7rem] text-fog-500">
                    {{ item.course?.title }} · {{ item.course?.code }}
                  </p>
                </div>

                <div class="flex items-center gap-3 text-[0.7rem] text-fog-400">
                  <span class="inline-flex items-center gap-1.5">
                    <Clock class="h-3.5 w-3.5" />{{ dayjs(item.due_at).format('HH:mm') }}
                  </span>
                  <span class="inline-flex items-center gap-1.5">
                    <Sparkles class="h-3.5 w-3.5" />{{ item.max_points }} pts
                  </span>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <div v-if="undated.length" class="panel p-5">
          <h3 class="mb-3 font-display text-sm font-semibold text-white">No due date</h3>
          <ul class="space-y-2">
            <li v-for="item in undated" :key="item.id">
              <button
                class="flex w-full items-center gap-3 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-2.5 text-left transition hover:border-white/16"
                @click="open(item)"
              >
                <span class="h-2 w-2 rounded-full" :style="{ background: accentOf(accentOfCourse(item)).base }" />
                <span class="min-w-0 flex-1 truncate text-xs font-medium text-white">{{ item.title }}</span>
                <span class="truncate text-[0.68rem] text-fog-500">{{ item.course?.code }}</span>
              </button>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </div>
</template>
