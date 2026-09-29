<script setup lang="ts">
import { computed } from 'vue'
import { Activity, ArrowUpRight, PieChart, Users } from 'lucide-vue-next'
import type { AdminDashboard } from '@/types'
import { accentOf, ROLE_TONE } from '@/lib/accents'
import { fromNow, humanAction, titleCase } from '@/lib/format'
import StatCard from '@/components/ui/StatCard.vue'
import MiniBars from '@/components/ui/MiniBars.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { iconFor } from '@/lib/icons'

const props = defineProps<{ data: AdminDashboard }>()

const monthLabels = computed(() => props.data.signups_by_month.map((row) => row.label))
const monthValues = computed(() => props.data.signups_by_month.map((row) => row.total))

const roleSplit = computed(() => {
  const split = props.data.role_split
  const total = Object.values(split).reduce((sum, value) => sum + Number(value), 0) || 1

  return (['admin', 'teacher', 'student'] as const).map((role) => ({
    role,
    count: Number(split[role] ?? 0),
    share: (Number(split[role] ?? 0) / total) * 100,
  }))
})

const maxLoad = computed(() => Math.max(1, ...props.data.course_load.map((row) => row.students)))
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

    <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
      <!-- Signups -->
      <section class="panel p-5">
        <div class="mb-5 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <ArrowUpRight class="h-4 w-4 text-emerald-300" />
            <h2 class="font-display text-base font-semibold text-white">Onboarding trend</h2>
          </div>
          <span class="chip">Last 6 months</span>
        </div>
        <MiniBars :data="monthValues" :labels="monthLabels" :height="150" />
      </section>

      <!-- Role split -->
      <section class="panel p-5">
        <div class="mb-5 flex items-center gap-2">
          <PieChart class="h-4 w-4 text-violet-300" />
          <h2 class="font-display text-base font-semibold text-white">Who is on the platform</h2>
        </div>

        <div class="flex h-[150px] items-stretch overflow-hidden rounded-xl2 border border-white/7">
          <div
            v-for="segment in roleSplit"
            :key="segment.role"
            class="relative flex flex-col justify-end p-3"
            :style="{
              width: `${segment.share}%`,
              background: accentOf(ROLE_TONE[segment.role]).gradient,
            }"
          >
            <p v-if="segment.share > 8" class="font-display text-lg font-bold text-white drop-shadow">{{ segment.count }}</p>
            <p v-if="segment.share > 8" class="text-[0.62rem] font-medium tracking-wide text-white/80 uppercase">
              {{ segment.role }}
            </p>
          </div>
        </div>

        <div class="mt-4 space-y-2">
          <div v-for="segment in roleSplit" :key="segment.role" class="flex items-center justify-between text-xs">
            <span class="inline-flex items-center gap-2 text-fog-300">
              <span class="h-2 w-2 rounded-full" :style="{ background: accentOf(ROLE_TONE[segment.role]).base }" />
              {{ titleCase(segment.role) }}s
            </span>
            <span class="font-medium text-fog-400">{{ segment.count }} · {{ segment.share.toFixed(0) }}%</span>
          </div>
        </div>
      </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">
      <!-- Course load -->
      <section class="panel p-5">
        <div class="mb-5 flex items-center gap-2">
          <Users class="h-4 w-4 text-sky-300" />
          <h2 class="font-display text-base font-semibold text-white">Heaviest classrooms</h2>
        </div>

        <div class="space-y-3.5">
          <RouterLink
            v-for="course in data.course_load"
            :key="course.id"
            :to="{ name: 'course', params: { id: course.id } }"
            class="group block"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-white group-hover:text-violet-200">{{ course.title }}</p>
                <p class="text-[0.68rem] text-fog-500">{{ course.code }} · {{ course.teacher }}</p>
              </div>
              <span class="shrink-0 font-display text-sm font-semibold text-white">{{ course.students }}</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/6">
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: `${(course.students / maxLoad) * 100}%`, background: accentOf(course.accent).gradient }"
              />
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- Recent activity -->
      <section class="panel p-5">
        <div class="mb-5 flex items-center gap-2">
          <Activity class="h-4 w-4 text-amber-300" />
          <h2 class="font-display text-base font-semibold text-white">Live activity</h2>
        </div>

        <ol class="relative space-y-4 border-l border-white/8 pl-5">
          <li v-for="(log, index) in data.recent_activity" :key="index" class="relative">
            <span class="absolute -left-[1.6rem] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-ink-880 bg-violet-400" />
            <p class="text-xs font-medium text-white">{{ humanAction(log.action) }}</p>
            <p class="mt-0.5 text-[0.7rem] text-fog-500">
              {{ log.subject ?? '—' }} · {{ log.user ?? 'system' }} · {{ fromNow(log.created_at) }}
            </p>
          </li>
        </ol>
      </section>
    </div>

    <!-- Newest people -->
    <section class="panel p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-display text-base font-semibold text-white">Newest arrivals</h2>
        <RouterLink :to="{ name: 'admin-users' }" class="text-[0.72rem] font-medium text-fog-400 transition hover:text-violet-300">
          Manage directory
        </RouterLink>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="person in data.recent_users"
          :key="person.id"
          class="flex items-center gap-3 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-3"
        >
          <BaseAvatar :name="person.name" :initials="person.initials" :role="person.role" size="sm" />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-white">{{ person.name }}</p>
            <p class="truncate text-[0.7rem] text-fog-500">{{ person.email }}</p>
          </div>
          <BaseBadge :tone="ROLE_TONE[person.role]" size="xs">{{ person.role }}</BaseBadge>
        </div>
      </div>
    </section>
  </div>
</template>
