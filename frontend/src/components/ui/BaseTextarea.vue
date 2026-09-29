<script setup lang="ts">
defineProps<{
  label?: string
  placeholder?: string
  error?: string
  hint?: string
  rows?: number
  disabled?: boolean
}>()

const model = defineModel<string | undefined>()
</script>

<template>
  <div class="w-full">
    <label v-if="label" class="field-label">{{ label }}</label>
    <textarea
      :value="model ?? ''"
      :placeholder="placeholder"
      :rows="rows ?? 4"
      :disabled="disabled"
      class="field resize-y leading-relaxed"
      :class="error ? 'border-rose-400/60' : ''"
      @input="model = ($event.target as HTMLTextAreaElement).value"
    />
    <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-300">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-fog-500">{{ hint }}</p>
  </div>
</template>
