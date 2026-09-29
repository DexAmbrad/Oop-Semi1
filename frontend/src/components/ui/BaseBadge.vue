<script setup lang="ts">
import { computed } from 'vue'
import { accentOf, type AccentTheme } from '@/lib/accents'
import type { Accent } from '@/types'

const props = withDefaults(
  defineProps<{
    tone?: Accent | AccentTheme
    outline?: boolean
    size?: 'xs' | 'sm'
  }>(),
  { outline: false, size: 'sm' },
)

const theme = computed<AccentTheme>(() =>
  typeof props.tone === 'object' ? props.tone : accentOf(props.tone ?? 'violet'),
)

const style = computed(() =>
  props.outline
    ? { borderColor: theme.value.ring, color: theme.value.text, background: 'transparent' }
    : { borderColor: 'transparent', color: theme.value.text, background: theme.value.soft },
)
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border font-semibold tracking-wide"
    :class="size === 'xs' ? 'px-2 py-0.5 text-[0.62rem]' : 'px-2.5 py-1 text-[0.68rem]'"
    :style="style"
  >
    <slot name="icon" />
    <slot />
  </span>
</template>
