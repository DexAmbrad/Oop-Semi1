<script setup lang="ts" generic="T extends string | number">
import { ChevronDown } from 'lucide-vue-next'

export interface SelectOption<V extends string | number = string> {
  value: V
  label: string
}

defineProps<{
  label?: string
  options: SelectOption<T>[]
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
}>()

const model = defineModel<T | undefined>()
</script>

<template>
  <div class="w-full">
    <label v-if="label" class="field-label">{{ label }}</label>
    <div class="relative">
      <select
        :value="model ?? ''"
        :disabled="disabled"
        class="field cursor-pointer appearance-none pr-9"
        :class="error ? 'border-rose-400/60' : ''"
        @change="model = ($event.target as HTMLSelectElement).value as T"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option v-for="option in options" :key="option.value" :value="option.value" class="bg-ink-860">
          {{ option.label }}
        </option>
      </select>
      <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-fog-500" />
    </div>
    <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-300">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-fog-500">{{ hint }}</p>
  </div>
</template>
