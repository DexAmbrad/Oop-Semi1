<script setup lang="ts">
import { computed } from 'vue'
import type { Accent } from '@/types'
import { accentOf } from '@/lib/accents'
import { Sparkles } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    label: string
    value: number | null
    suffix?: string
    tone?: Accent
    icon?: unknown
    hint?: string
  }>(),
  { tone: 'violet' },
)

const theme = computed(() => accentOf(props.tone))
</script>

<template>
  <div class="panel group relative overflow-hidden p-4 transition hover:-translate-y-0.5">
    <div
      class="absolute -right-10 -top-12 h-32 w-32 rounded-full opacity-25 blur-3xl transition group-hover:opacity-45"
      :style="{ background: theme.base }"
    />

    <div class="relative flex items-start justify-between gap-3">
      <p class="text-[0.68rem] font-semibold tracking-[0.14em] text-fog-400 uppercase">{{ label }}</p>
      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" :style="{ background: theme.soft, color: theme.text }">
        <component :is="icon ?? Sparkles" class="h-4 w-4" />
      </span>
    </div>

    <p class="stat-value relative mt-3">
      {{ value === null ? '—' : value }}<span v-if="suffix" class="ml-0.5 text-base text-fog-400">{{ suffix }}</span>
    </p>

    <p v-if="hint" class="relative mt-1.5 text-[0.7rem] text-fog-500">{{ hint }}</p>
  </div>
</template>
