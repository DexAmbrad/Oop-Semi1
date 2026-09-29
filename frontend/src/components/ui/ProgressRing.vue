<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    value: number | null
    size?: number
    thickness?: number
    label?: string
  }>(),
  { size: 120, thickness: 9 },
)

const radius = computed(() => (props.size - props.thickness) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const pct = computed(() => Math.max(0, Math.min(100, props.value ?? 0)))
const offset = computed(() => circumference.value - (pct.value / 100) * circumference.value)

const stroke = computed(() => {
  if (props.value === null) return '#6d6d86'
  if (pct.value >= 90) return '#34d399'
  if (pct.value >= 80) return '#38bdf8'
  if (pct.value >= 70) return '#fbbf24'
  return '#fb7185'
})
</script>

<template>
  <div class="relative grid place-items-center" :style="{ width: `${size}px`, height: `${size}px` }">
    <svg :width="size" :height="size" class="-rotate-90">
      <defs>
        <linearGradient :id="`ring-${size}-${thickness}`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" :stop-color="stroke" />
          <stop offset="100%" stop-color="#a78bfa" />
        </linearGradient>
      </defs>
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        stroke="rgba(255,255,255,0.08)"
        :stroke-width="thickness"
      />
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        :stroke="`url(#ring-${size}-${thickness})`"
        :stroke-width="thickness"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        style="transition: stroke-dashoffset 0.7s cubic-bezier(0.2, 0.8, 0.2, 1)"
      />
    </svg>

    <div class="absolute inset-0 grid place-items-center">
      <slot>
        <div class="text-center">
          <p class="font-display text-2xl font-semibold text-white">
            {{ value === null ? '—' : Math.round(value) }}<span class="text-sm text-fog-400">%</span>
          </p>
          <p v-if="label" class="mt-0.5 text-[0.65rem] font-medium tracking-wide text-fog-500 uppercase">{{ label }}</p>
        </div>
      </slot>
    </div>
  </div>
</template>
