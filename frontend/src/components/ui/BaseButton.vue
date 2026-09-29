<script setup lang="ts">
import { computed } from 'vue'
import { Loader2 } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'ghost' | 'outline' | 'subtle' | 'danger'
    size?: 'sm' | 'md' | 'lg'
    type?: 'button' | 'submit' | 'reset'
    loading?: boolean
    disabled?: boolean
    block?: boolean
    to?: string
    icon?: boolean
  }>(),
  {
    variant: 'primary',
    size: 'md',
    type: 'button',
    loading: false,
    disabled: false,
    block: false,
    icon: false,
  },
)

const classes = computed(() => {
  const base =
    'relative inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-ink-950'

  const sizes: Record<string, string> = {
    sm: props.icon ? 'h-8 w-8 text-xs' : 'h-8 px-3.5 text-xs',
    md: props.icon ? 'h-10 w-10 text-sm' : 'h-10 px-4 text-sm',
    lg: props.icon ? 'h-12 w-12 text-base' : 'h-12 px-6 text-sm',
  }

  const variants: Record<string, string> = {
    primary:
      'bg-gradient-to-br from-violet-500 to-violet-600 text-white shadow-[0_10px_30px_-12px_rgba(124,58,237,0.9)] hover:from-violet-400 hover:to-violet-500 active:scale-[0.98]',
    ghost: 'text-fog-300 hover:bg-white/6 hover:text-white',
    outline: 'border border-white/12 bg-white/4 text-fog-100 hover:border-white/25 hover:bg-white/8',
    subtle: 'bg-white/8 text-white hover:bg-white/14',
    danger: 'border border-rose-400/30 bg-rose-400/10 text-rose-300 hover:bg-rose-400/20',
  }

  return [base, sizes[props.size], variants[props.variant], props.block ? 'w-full' : '']
})
</script>

<template>
  <component
    :is="to ? 'router-link' : 'button'"
    :to="to"
    :type="to ? undefined : type"
    :disabled="to ? undefined : disabled || loading"
    :class="classes"
  >
    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
    <slot v-else name="icon" />
    <span v-if="!icon || $slots.default" :class="loading ? 'opacity-70' : ''">
      <slot />
    </span>
  </component>
</template>
