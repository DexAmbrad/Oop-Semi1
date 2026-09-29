<script setup lang="ts">
import {
  ArrowRight,
  BookOpen,
  Clock,
  ClipboardList,
  GraduationCap,
  Plus,
  Sparkles,
  Users,
} from 'lucide-vue-next'
import type { Metric, TeacherDashboard } from '@/types'
import { accentOf, courseIcon } from '@/lib/accents'
import { formatDue, relativeDue } from '@/lib/format'
import StatCard from '@/components/ui/StatCard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { iconFor } from '@/lib/icons'

defineProps<{ data: TeacherDashboard }>()

const metricIcon = (metric: Metric) => iconFor(metric.icon)
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard
        v-for="(metric, index) in data.metrics"
        :key="metric.label"
        :label="metric.label"
        :value="metric.value"
        :suffix="metric.suffix"
        :tone="metric.tone"
        :icon="metricIcon(metric)"
        :style="{ animationDelay: `${index * 50}ms` }"
        class="anim-rise"
      />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
      <!-- Classrooms -->
      <section class="panel p-5">
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <BookOpen class="h-4 w-4 text-violet-300" />
            <h2 class="font-display text-base font-semibold text-white">Your classrooms</h2>
          </div>
          <RouterLink :to="{ name: 'courses' }" class="inline-flex items-center gap-1 text-[0.72rem] font-medium text-fog-400 transition hover:text-violet-300">
            View all <ArrowRight class="h-3 w-3" />
          </RouterLink>
        </div>

        <div v-if="!data.courses.length">
          <EmptyState
            :icon="GraduationCap"
            compact
            title="No classrooms yet"
            description="Create your first classroom and share the room code with your cohort."
          >
            <template #action>
              <RouterLink :to="{ name: 'courses' }">
                <span class="inline-flex items-center gap-2 rounded-full bg-violet-500 px-4 py-2 text-xs font-semibold text-white">
                  <Plus class="h-3.5 w-3.5" /> Create classroom
                </span>
              </RouterLink>
            </template>
          </EmptyState>
        </div>

        <div v-else class="space-y-2.5">
          <RouterLink
            v-for="course in data.courses"
            :key="course.id"
            :to="{ name: 'course', params: { id: course.id } }"
            class="group flex items-center gap-3.5 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-3 transition hover:border-white/16 hover:bg-white/5"
          >
            <span
              class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white"
              :style="{ background: accentOf(course.accent).gradient }"
            >
              <component :is="courseIcon(course.icon)" class="h-4 w-4" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-semibold text-white">{{ course.title }}</span>
              <span class="mt-0.5 flex items-center gap-3 text-[0.7rem] text-fog-500">
                <span class="font-mono">{{ course.code }}</span>
                <span class="inline-flex items-center gap-1"><Users class="h-3 w-3" />{{ course.students_count }}</span>
                <span class="inline-flex items-center gap-1"><ClipboardList class="h-3 w-3" />{{ course.assignments_count }}</span>
              </span>
            </span>
            <ArrowRight class="h-4 w-4 shrink-0 text-fog-500 transition group-hover:translate-x-0.5 group-hover:text-violet-300" />
          </RouterLink>
        </div>
      </section>

      <!-- Needs grading -->
      <section class="panel p-5">
        <div class="mb-4 flex items-center gap-2">
          <Sparkles class="h-4 w-4 text-amber-300" />
          <h2 class="font-display text-base font-semibold text-white">Waiting on your feedback</h2>
        </div>

        <div v-if="!data.needs_grading.length" class="rounded-xl2 border border-dashed border-white/10 px-4 py-10 text-center">
          <p class="font-display text-sm font-semibold text-emerald-300">Inbox zero</p>
          <p class="mt-1 text-xs text-fog-500">Every submission has a grade. Enjoy it while it lasts.</p>
        </div>

        <div v-else class="space-y-2.5">
          <RouterLink
            v-for="item in data.needs_grading"
            :key="item.id"
            :to="{ name: 'work', params: { courseId: item.course.id, workId: item.id }, query: { tab: 'grading' } }"
            class="block rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-3 transition hover:border-amber-300/30 hover:bg-white/5"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-white">{{ item.title }}</p>
                <p class="mt-0.5 truncate text-[0.7rem] text-fog-500">{{ item.course.title }}</p>
              </div>
              <BaseBadge :tone="item.ungraded > 4 ? 'rose' : 'amber'" size="xs">
                {{ item.ungraded }} left
              </BaseBadge>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/6">
              <div
                class="h-full rounded-full bg-gradient-to-r from-amber-400 to-emerald-400 transition-all duration-500"
                :style="{ width: `${item.submitted ? ((item.submitted - item.ungraded) / item.submitted) * 100 : 0}%` }"
              />
            </div>
            <p class="mt-2 text-[0.68rem] text-fog-500">
              {{ item.submitted - item.ungraded }} of {{ item.submitted }} graded · due {{ formatDue(item.due_at) }}
            </p>
          </RouterLink>
        </div>
      </section>
    </div>

    <!-- Upcoming -->
    <section class="panel p-5">
      <div class="mb-4 flex items-center gap-2">
        <Clock class="h-4 w-4 text-sky-300" />
        <h2 class="font-display text-base font-semibold text-white">Coming up</h2>
      </div>

      <div v-if="!data.upcoming.length" class="rounded-xl2 border border-dashed border-white/10 px-4 py-10 text-center text-sm text-fog-500">
        Nothing scheduled ahead.
      </div>

      <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="item in data.upcoming"
          :key="item.id"
          :to="{ name: 'work', params: { courseId: item.course_id, workId: item.id } }"
          class="group relative overflow-hidden rounded-xl2 border border-white/7 bg-white/2 p-4 transition hover:border-white/18 hover:bg-white/5"
        >
          <div
            class="absolute inset-x-0 top-0 h-0.5"
            :style="{ background: accentOf(item.accent).gradient }"
          />
          <div class="flex items-start justify-between gap-2">
            <p class="text-[0.68rem] font-semibold tracking-wide text-fog-500 uppercase">{{ item.type }}</p>
            <p class="font-mono text-[0.66rem] text-fog-500">{{ relativeDue(item.due_at) }}</p>
          </div>
          <p class="mt-2 line-clamp-2 text-sm font-medium text-white">{{ item.title }}</p>
          <p class="mt-2 truncate text-[0.7rem] text-fog-500">{{ item.course_title }}</p>
          <p class="mt-3 text-[0.7rem] font-medium text-fog-300">{{ formatDue(item.due_at) }}</p>
        </RouterLink>
      </div>
    </section>
  </div>
</template>
