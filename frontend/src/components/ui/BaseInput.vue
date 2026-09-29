<script setup lang="ts" generic="T extends string | number">
defineProps<{
  label?: string
  type?: string
  placeholder?: string
  error?: string
  hint?: string
  autocomplete?: string
  disabled?: boolean
  required?: boolean
  id?: string
}>()

const model = defineModel<T | undefined>()
</script>

<template>
  <div class="w-full">
    <label v-if="label" class="field-label" :for="id">{{ label }}</label>
    <div class="relative">
      <span
        v-if="$slots.prefix"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fog-500"
      >
        <slot name="prefix" />
      </span>
      <input
        :id="id"
        :value="model ?? ''"
        :type="type ?? 'text'"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :disabled="disabled"
        :required="required"
        class="field"
        :class="[$slots.prefix ? 'pl-9' : '', error ? 'border-rose-400/60' : '']"
        @input="model = ($event.target as HTMLInputElement).value as T"
      />
    </div>
    <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-300">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-fog-500">{{ hint }}</p>
  </div>
</template>
