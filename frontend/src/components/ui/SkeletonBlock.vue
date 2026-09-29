<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; height?: string }>(), { rows: 3, height: 'h-4' })
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="row in rows"
      :key="row"
      class="anim-shimmer rounded-lg bg-white/5"
      :class="height"
      :style="{ width: `${100 - (row % 3) * 12}%` }"
    />
  </div>
</template>
