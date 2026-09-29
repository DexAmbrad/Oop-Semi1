<script setup lang="ts">
import { computed, ref } from 'vue'
import { Crown, GraduationCap, Mail, Search, ShieldCheck, Users } from 'lucide-vue-next'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import type { Course } from '@/types'
import { ROLE_TONE, accentOf } from '@/lib/accents'

const props = defineProps<{ course: Course; canTeach: boolean }>()

const search = ref('')

const roles = ['admin', 'teacher', 'student'] as const

const roster = computed(() => props.course.roster ?? [])

const teachers = computed(() => roster.value.filter((person) => person.role_in_course === 'assistant_teacher'))

const students = computed(() => {
  const term = search.value.trim().toLowerCase()
  return roster.value
    .filter((person) => person.role_in_course !== 'assistant_teacher')
    .filter((person) => !term || person.name.toLowerCase().includes(term) || person.email.toLowerCase().includes(term))
    .sort((a, b) => a.name.localeCompare(b.name))
})

const capacityUsed = computed(() =>
  props.course.capacity ? Math.min(100, (props.course.students_count / props.course.capacity) * 100) : 0,
)
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_17rem]">
    <div class="space-y-5">
      <!-- Instructor -->
      <section class="panel p-5">
        <div class="mb-4 flex items-center gap-2">
          <Crown class="h-4 w-4 text-amber-300" />
          <h2 class="font-display text-base font-semibold text-white">Instructor</h2>
        </div>

        <div v-if="course.teacher" class="flex items-center gap-4">
          <BaseAvatar :name="course.teacher.name" :initials="course.teacher.initials" :accent="course.accent" size="lg" ring />
          <div class="min-w-0">
            <p class="font-display text-base font-semibold text-white">{{ course.teacher.name }}</p>
            <p class="mt-0.5 text-xs text-fog-400">{{ course.teacher.headline ?? 'Faculty' }}</p>
            <a
              :href="`mailto:${course.teacher.email}`"
              class="mt-1.5 inline-flex items-center gap-1.5 text-[0.72rem] text-violet-300 transition hover:text-violet-200"
            >
              <Mail class="h-3 w-3" />
              {{ course.teacher.email }}
            </a>
          </div>
        </div>
      </section>

      <!-- Assistant teachers -->
      <section v-if="teachers.length" class="panel p-5">
        <div class="mb-4 flex items-center gap-2">
          <ShieldCheck class="h-4 w-4 text-sky-300" />
          <h2 class="font-display text-base font-semibold text-white">Assistant teachers</h2>
          <span class="chip ml-auto">{{ teachers.length }}</span>
        </div>

        <div class="grid gap-2.5 sm:grid-cols-2">
          <div v-for="person in teachers" :key="person.id" class="flex items-center gap-3 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-2.5">
            <BaseAvatar :name="person.name" :initials="person.initials" accent="sky" size="sm" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ person.name }}</p>
              <p class="truncate text-[0.68rem] text-fog-500">{{ person.email }}</p>
            </div>
            <BaseBadge tone="sky" size="xs">Staff</BaseBadge>
          </div>
        </div>
      </section>

      <!-- Students -->
      <section class="panel p-5">
        <div class="mb-4 flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2">
            <GraduationCap class="h-4 w-4 text-violet-300" />
            <h2 class="font-display text-base font-semibold text-white">Learners</h2>
            <span class="chip">{{ students.length }}</span>
          </div>

          <div class="relative ml-auto min-w-[12rem] flex-1 sm:flex-none">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-fog-500" />
            <input v-model="search" type="search" placeholder="Filter learners" class="field h-9 py-0 pl-9 text-sm" />
          </div>
        </div>

        <EmptyState
          v-if="!students.length"
          :icon="Users"
          compact
          :title="search ? 'No learners match' : 'Nobody enrolled yet'"
          :description="search ? 'Try a different name or email.' : 'Share the room code to fill this classroom.'"
        />

        <div v-else class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="person in students"
            :key="person.id"
            class="flex items-center gap-3 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-2.5 transition hover:border-white/16 hover:bg-white/5"
          >
            <BaseAvatar :name="person.name" :initials="person.initials" :role="'student'" size="sm" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ person.name }}</p>
              <p class="truncate text-[0.68rem] text-fog-500">{{ person.email }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>

    <aside class="space-y-4">
      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">Enrolment</h3>
        <p class="mt-3 font-display text-3xl font-semibold text-white">
          {{ course.students_count }}<span class="text-base text-fog-500"> / {{ course.capacity }}</span>
        </p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/6">
          <div
            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-amber-300 transition-all duration-500"
            :style="{ width: `${capacityUsed}%` }"
          />
        </div>
        <p class="mt-2 text-[0.7rem] text-fog-500">
          {{ course.students_count >= course.capacity ? 'Classroom is at capacity.' : `${Math.max(0, course.capacity - course.students_count)} seats remaining.` }}
        </p>
      </div>

      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">Breakdown</h3>
        <dl class="mt-4 space-y-3 text-xs">
          <div class="flex items-center justify-between">
            <dt class="inline-flex items-center gap-2 text-fog-400"><span class="h-2 w-2 rounded-full bg-amber-400" />Instructor</dt>
            <dd class="font-semibold text-white">1</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="inline-flex items-center gap-2 text-fog-400"><span class="h-2 w-2 rounded-full bg-sky-400" />Assistants</dt>
            <dd class="font-semibold text-white">{{ teachers.length }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="inline-flex items-center gap-2 text-fog-400">
              <span class="h-2 w-2 rounded-full" :style="{ background: accentOf('violet').base }" />Students
            </dt>
            <dd class="font-semibold text-white">{{ course.students_count }}</dd>
          </div>
        </dl>
      </div>

      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">Role colours</h3>
        <div class="mt-3 space-y-2">
          <div v-for="role in roles" :key="role" class="flex items-center justify-between text-xs">
            <span class="text-fog-400 capitalize">{{ role }}</span>
            <BaseBadge :tone="ROLE_TONE[role]" size="xs">{{ role }}</BaseBadge>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>
