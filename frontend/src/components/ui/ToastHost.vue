<script setup lang="ts">
import { CheckCircle2, Info, XCircle } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()
const { items } = storeToRefs(toast)

const icons = { success: CheckCircle2, error: XCircle, info: Info }

const tones = {
  success: 'border-emerald-400/30',
  error: 'border-rose-400/30',
  info: 'border-violet-400/30',
}

const iconTones = {
  success: 'text-emerald-300',
  error: 'text-rose-300',
  info: 'text-violet-300',
}
</script>

<template>
  <Teleport to="body">
    <div class="pointer-events-none fixed inset-x-0 bottom-5 z-[60] flex flex-col items-center gap-2 px-4">
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4 scale-[0.97]"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-for="item in items"
          :key="item.id"
          class="panel panel-glow pointer-events-auto flex w-full max-w-sm items-start gap-3 px-4 py-3"
          :class="tones[item.tone]"
        >
          <component :is="icons[item.tone]" class="mt-0.5 h-4 w-4 shrink-0" :class="iconTones[item.tone]" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-white">{{ item.title }}</p>
            <p v-if="item.description" class="mt-0.5 text-xs leading-relaxed text-fog-400">
              {{ item.description }}
            </p>
          </div>
          <button
            class="shrink-0 text-fog-500 transition hover:text-white"
            aria-label="Dismiss"
            @click="toast.dismiss(item.id)"
          >
            <XCircle class="h-4 w-4" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
