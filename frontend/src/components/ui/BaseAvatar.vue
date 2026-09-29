<script setup lang="ts">
import { computed } from 'vue'
import { accentOf } from '@/lib/accents'
import type { Accent, Role } from '@/types'
import { ROLE_TONE } from '@/lib/accents'
import { initialsOf } from '@/lib/format'

const props = withDefaults(
  defineProps<{
    name: string
    initials?: string
    accent?: Accent
    role?: Role
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
    ring?: boolean
  }>(),
  { size: 'md', ring: false },
)

const sizes: Record<string, string> = {
  xs: 'h-6 w-6 text-[0.6rem]',
  sm: 'h-8 w-8 text-[0.7rem]',
  md: 'h-10 w-10 text-xs',
  lg: 'h-14 w-14 text-base',
  xl: 'h-20 w-20 text-2xl',
}

const theme = computed(() => accentOf(props.role ? ROLE_TONE[props.role] : props.accent))

const label = computed(() => props.initials || initialsOf(props.name))
</script>

<template>
  <div
    class="relative grid shrink-0 place-items-center rounded-full font-display font-semibold tracking-tight text-white select-none"
    :class="[sizes[size], ring ? 'ring-2' : '']"
    :style="{
      background: theme.gradient,
      boxShadow: `0 8px 22px -12px ${theme.glow}`,
      '--tw-ring-color': theme.ring,
    }"
    :title="name"
  >
    <span class="drop-shadow-sm">{{ label }}</span>
    <span class="absolute inset-0 rounded-full ring-1 ring-inset ring-white/20" />
  </div>
</template>
