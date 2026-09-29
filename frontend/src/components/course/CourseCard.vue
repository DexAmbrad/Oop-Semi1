<script setup lang="ts">
import { computed } from 'vue'
import { ArrowUpRight, ClipboardList, Users } from 'lucide-vue-next'
import type { Course } from '@/types'
import { accentOf, courseIcon } from '@/lib/accents'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'

const props = defineProps<{ course: Course; compact?: boolean }>()

const theme = computed(() => accentOf(props.course.accent))
const icon = computed(() => courseIcon(props.course.icon))
</script>

<template>
  <RouterLink
    :to="{ name: 'course', params: { id: course.id } }"
    class="group panel panel-hover relative flex flex-col overflow-hidden transition duration-200 hover:-translate-y-0.5"
    :style="{ '--accent': theme.base }"
  >
    <div
      class="relative overflow-hidden"
      :class="compact ? 'h-20' : 'h-28'"
      :style="{ background: theme.gradient }"
    >
      <div class="absolute inset-0 opacity-30 mix-blend-overlay" :style="{ backgroundImage: 'radial-gradient(circle at 80% 20%, #fff, transparent 45%)' }" />
      <div class="absolute -right-6 -top-8 h-32 w-32 rounded-full bg-black/15 blur-2xl" />

      <div class="relative flex h-full items-start justify-between p-4">
        <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/20 text-white backdrop-blur-sm ring-1 ring-inset ring-white/30">
          <component :is="icon" class="h-5 w-5" />
        </div>
        <div class="text-right text-white">
          <p class="font-display text-sm font-bold tracking-wider drop-shadow-sm">{{ course.code }}</p>
          <p class="text-[0.65rem] font-medium opacity-80">{{ course.term ?? '—' }}</p>
        </div>
      </div>
    </div>

    <div class="flex flex-1 flex-col p-4">
      <div class="flex items-start justify-between gap-3">
        <h3 class="font-display text-[0.98rem] leading-snug font-semibold text-white group-hover:text-white">
          {{ course.title }}
        </h3>
        <ArrowUpRight class="h-4 w-4 shrink-0 text-fog-500 transition group-hover:text-violet-300" />
      </div>

      <p class="mt-1 text-xs text-fog-500">{{ course.subject ?? 'General studies' }}</p>

      <p v-if="!compact" class="mt-3 line-clamp-2 text-xs leading-relaxed text-fog-400">
        {{ course.description ?? 'No description yet.' }}
      </p>

      <div class="mt-4 flex items-center justify-between gap-3 border-t border-white/6 pt-3">
        <div class="flex items-center gap-3 text-[0.7rem] text-fog-400">
          <span class="inline-flex items-center gap-1.5">
            <Users class="h-3.5 w-3.5" />
            {{ course.students_count }}
          </span>
          <span class="inline-flex items-center gap-1.5">
            <ClipboardList class="h-3.5 w-3.5" />
            {{ course.assignments_count }}
          </span>
        </div>

        <div class="flex items-center gap-2">
          <span
            v-if="course.status === 'archived'"
            class="chip border-amber-400/25 text-amber-300"
          >
            Archived
          </span>
          <BaseAvatar
            v-else-if="course.teacher"
            :name="course.teacher.name"
            :initials="course.teacher.initials"
            size="xs"
            :accent="course.accent"
          />
        </div>
      </div>
    </div>
  </RouterLink>
</template>
