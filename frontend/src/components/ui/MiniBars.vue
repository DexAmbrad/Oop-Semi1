<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ data: number[]; labels?: string[]; height?: number }>(), {
  height: 120,
})

const max = computed(() => Math.max(1, ...props.data))

function barHeight(value: number): number {
  return Math.max(4, (value / max.value) * 100)
}
</script>

<template>
  <div class="flex items-end gap-2" :style="{ height: `${height}px` }">
    <div v-for="(value, index) in data" :key="index" class="group flex h-full flex-1 flex-col justify-end gap-2">
      <div class="relative flex-1 overflow-hidden rounded-lg bg-white/4">
        <div
          class="absolute inset-x-0 bottom-0 rounded-lg bg-gradient-to-t from-violet-600/70 via-violet-500/80 to-amber-300/70 transition-all duration-500"
          :style="{ height: `${barHeight(value)}%` }"
        />
        <div
          class="absolute inset-0 rounded-lg opacity-0 ring-1 ring-violet-300/40 transition group-hover:opacity-100"
        />
      </div>
      <p class="text-center text-[0.62rem] font-medium tracking-wide text-fog-500 uppercase">
        {{ labels?.[index] ?? index + 1 }}
      </p>
      <p class="text-center text-[0.7rem] font-semibold text-fog-300">{{ value }}</p>
    </div>
  </div>
</template>
