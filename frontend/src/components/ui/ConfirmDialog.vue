<script setup lang="ts">
import { computed, ref } from 'vue'
import BaseModal from './BaseModal.vue'
import BaseButton from './BaseButton.vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    confirmLabel?: string
    tone?: 'primary' | 'danger'
  }>(),
  { confirmLabel: 'Confirm', tone: 'primary' },
)

const emit = defineEmits<{ close: []; confirm: [] }>()

const busy = ref(false)

const variant = computed(() => (props.tone === 'danger' ? 'danger' : 'primary'))

function confirm() {
  emit('confirm')
}

defineExpose({ busy })
</script>

<template>
  <BaseModal :open="open" :title="title" size="sm" @close="emit('close')">
    <p class="text-sm leading-relaxed text-fog-300">
      <slot>{{ description }}</slot>
    </p>

    <template #footer>
      <BaseButton variant="ghost" @click="emit('close')">Cancel</BaseButton>
      <BaseButton :variant="variant" :loading="busy" @click="confirm">{{ confirmLabel }}</BaseButton>
    </template>
  </BaseModal>
</template>
