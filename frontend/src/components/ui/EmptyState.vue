<script setup lang="ts">
import type { Component } from 'vue'

withDefaults(
  defineProps<{
    icon?: Component
    title: string
    description?: string
    compact?: boolean
  }>(),
  { compact: false },
)
</script>

<template>
  <div
    class="flex flex-col items-center justify-center rounded-xl2 border border-dashed border-white/10 text-center"
    :class="compact ? 'px-6 py-10' : 'px-6 py-16'"
  >
    <div
      v-if="icon"
      class="mb-4 grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/5 text-fog-400"
    >
      <component :is="icon" class="h-5 w-5" />
    </div>
    <p class="font-display text-base font-semibold text-fog-100">{{ title }}</p>
    <p v-if="description" class="mt-1.5 max-w-sm text-sm leading-relaxed text-fog-500">{{ description }}</p>
    <div v-if="$slots.action" class="mt-5">
      <slot name="action" />
    </div>
  </div>
</template>
