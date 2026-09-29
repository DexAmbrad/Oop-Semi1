<script setup lang="ts">
withDefaults(
  defineProps<{
    eyebrow?: string
    title: string
    description?: string
    compact?: boolean
  }>(),
  { compact: false },
)
</script>

<template>
  <header class="flex flex-wrap items-end justify-between gap-5" :class="compact ? 'mb-5' : 'mb-8'">
    <div class="min-w-0">
      <p v-if="eyebrow" class="mb-2 text-[0.68rem] font-semibold tracking-[0.18em] text-violet-300/80 uppercase">
        {{ eyebrow }}
      </p>
      <h1 class="font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-2xl text-sm leading-relaxed text-fog-400">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
