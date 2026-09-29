<script setup lang="ts">
import { ArrowRight, Bell, CalendarClock, CheckCircle2, Circle, Trophy } from 'lucide-vue-next'
import type { StudentDashboard } from '@/types'
import { accentOf } from '@/lib/accents'
import { formatDue, fromNow, relativeDue } from '@/lib/format'
import StatCard from '@/components/ui/StatCard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ProgressRing from '@/components/ui/ProgressRing.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { iconFor } from '@/lib/icons'

defineProps<{ data: StudentDashboard }>()
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard
        v-for="metric in data.metrics"
        :key="metric.label"
        :label="metric.label"
        :value="metric.value"
        :suffix="metric.suffix"
        :tone="metric.tone"
        :icon="iconFor(metric.icon)"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
      <!-- Upcoming work -->
      <section class="panel p-5">
        <div class="mb-4 flex items-center gap-2">
          <CalendarClock class="h-4 w-4 text-violet-300" />
          <h2 class="font-display text-base font-semibold text-white">Your work</h2>
        </div>

        <div v-if="!data.upcoming.length">
          <EmptyState
            :icon="CheckCircle2"
            compact
            title="Nothing due"
            description="You are all caught up across your classrooms."
          />
        </div>

        <div v-else class="space-y-2.5">
          <RouterLink
            v-for="item in data.upcoming"
            :key="item.id"
            :to="{ name: 'work', params: { courseId: item.course_id, workId: item.id } }"
            class="group flex items-center gap-4 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-3 transition hover:border-white/16 hover:bg-white/5"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-xl"
              :style="{ background: accentOf(item.accent).soft, color: accentOf(item.accent).text }"
            >
              <CheckCircle2 v-if="item.submitted" class="h-4 w-4" />
              <Circle v-else class="h-4 w-4" />
            </span>

            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-2">
                <span class="truncate text-sm font-medium text-white">{{ item.title }}</span>
                <BaseBadge v-if="item.submitted" tone="emerald" size="xs">Submitted</BaseBadge>
                <BaseBadge v-else-if="item.is_overdue" tone="rose" size="xs">Late</BaseBadge>
                <BaseBadge v-else-if="relativeDue(item.due_at).includes('in')" tone="amber" size="xs">
                  {{ relativeDue(item.due_at) }}
                </BaseBadge>
              </span>
              <span class="mt-0.5 block truncate text-[0.72rem] text-fog-500">
                {{ item.course_title }} · {{ formatDue(item.due_at) }}
              </span>
            </span>

            <span v-if="item.score !== null && item.score !== undefined" class="shrink-0 text-right">
              <span class="font-display text-sm font-semibold text-emerald-300">{{ item.score }}</span>
              <span class="block text-[0.66rem] text-fog-500">/ {{ item.max_points }}</span>
            </span>
            <ArrowRight v-else class="h-4 w-4 shrink-0 text-fog-500 transition group-hover:translate-x-0.5 group-hover:text-violet-300" />
          </RouterLink>
        </div>
      </section>

      <!-- Grade summary -->
      <section class="panel p-5">
        <div class="mb-4 flex items-center gap-2">
          <Trophy class="h-4 w-4 text-amber-300" />
          <h2 class="font-display text-base font-semibold text-white">Grade health</h2>
        </div>

        <div v-if="!data.grade_summary.length" class="rounded-xl2 border border-dashed border-white/10 px-4 py-10 text-center text-sm text-fog-500">
          No grades recorded yet.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="row in data.grade_summary"
            :key="row.course_id"
            class="flex items-center gap-4"
          >
            <ProgressRing :value="row.percentage" :size="58" :thickness="6" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ row.title }}</p>
              <p class="mt-0.5 text-[0.7rem] text-fog-500">{{ row.graded_count }} graded items</p>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/6">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :style="{ width: `${row.percentage ?? 0}%`, background: accentOf(row.accent).gradient }"
                />
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Announcements -->
    <section class="panel p-5">
      <div class="mb-4 flex items-center gap-2">
        <Bell class="h-4 w-4 text-sky-300" />
        <h2 class="font-display text-base font-semibold text-white">From your classrooms</h2>
      </div>

      <div v-if="!data.announcements.length" class="rounded-xl2 border border-dashed border-white/10 px-4 py-10 text-center text-sm text-fog-500">
        No announcements yet.
      </div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <RouterLink
          v-for="note in data.announcements"
          :key="note.id"
          :to="{ name: 'course', params: { id: note.course_id }, query: { tab: 'stream' } }"
          class="group relative overflow-hidden rounded-xl2 border border-white/7 bg-white/2 p-4 transition hover:border-white/18 hover:bg-white/5"
        >
          <div class="absolute inset-y-0 left-0 w-0.5" :style="{ background: accentOf(note.accent).gradient }" />
          <div class="flex items-center justify-between gap-2">
            <p class="truncate text-[0.68rem] font-semibold tracking-wide text-fog-500 uppercase">{{ note.course_title }}</p>
            <BaseBadge v-if="note.pinned" tone="amber" size="xs">Pinned</BaseBadge>
          </div>
          <p class="mt-2 line-clamp-1 text-sm font-semibold text-white">{{ note.title }}</p>
          <p class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-fog-400">{{ note.body }}</p>
          <p class="mt-3 text-[0.68rem] text-fog-500">{{ note.author }} · {{ fromNow(note.published_at) }}</p>
        </RouterLink>
      </div>
    </section>
  </div>
</template>
