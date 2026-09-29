<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    description?: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
  }>(),
  { size: 'md' },
)

const emit = defineEmits<{ close: [] }>()

const panel = ref<HTMLElement | null>(null)

const widths: Record<string, string> = {
  sm: 'max-w-md',
  md: 'max-w-xl',
  lg: 'max-w-3xl',
  xl: 'max-w-5xl',
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.open) {
    emit('close')
  }
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
    if (open) {
      window.setTimeout(() => panel.value?.focus(), 30)
    }
  },
)

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8">
        <div class="fixed inset-0 bg-ink-950/80 backdrop-blur-sm" @click="emit('close')" />

        <Transition
          appear
          enter-active-class="transition duration-250 ease-out"
          enter-from-class="opacity-0 translate-y-5 scale-[0.98]"
        >
          <div
            ref="panel"
            tabindex="-1"
            class="panel panel-glow anim-rise relative z-10 my-auto w-full outline-none"
            :class="widths[size]"
          >
            <div class="flex items-start justify-between gap-4 border-b border-white/7 px-6 py-5">
              <div>
                <h3 v-if="title" class="font-display text-lg font-semibold text-white">{{ title }}</h3>
                <p v-if="description" class="mt-1 text-sm text-fog-400">{{ description }}</p>
              </div>
              <button
                class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white"
                @click="emit('close')"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
              <slot />
            </div>

            <div v-if="$slots.footer" class="flex items-center justify-end gap-3 border-t border-white/7 px-6 py-4">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
