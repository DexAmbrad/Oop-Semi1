<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { GraduationCap, Presentation, Sparkles, UserPlus } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors } from '@/lib/api'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  headline: '',
  password: '',
  password_confirmation: '',
  role: 'student' as 'student' | 'teacher',
})

const busy = ref(false)
const errors = ref<Record<string, string>>({})

const passwordMismatch = computed(
  () => form.value.password_confirmation.length > 0 && form.value.password !== form.value.password_confirmation,
)

const canSubmit = computed(
  () =>
    form.value.name.length > 1 &&
    form.value.email.includes('@') &&
    form.value.password.length >= 8 &&
    !passwordMismatch.value,
)

const roles = [
  {
    value: 'student' as const,
    label: 'Student',
    blurb: 'Join classrooms with a code and track your own work.',
    icon: GraduationCap,
  },
  {
    value: 'teacher' as const,
    label: 'Teacher',
    blurb: 'Create classrooms, publish work and grade submissions.',
    icon: Presentation,
  },
]

async function submit() {
  busy.value = true
  errors.value = {}

  try {
    const user = await auth.register({ ...form.value })
    toast.success(`Welcome, ${user.name.split(' ')[0]}.`, 'Your workspace is ready.')
    router.push({ name: 'dashboard' })
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Registration failed', errorMessage(error))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="grain-safe relative flex min-h-screen items-center justify-center px-5 py-12">
    <div class="absolute -left-24 top-10 h-96 w-96 rounded-full bg-violet-600/20 blur-[7rem]" />
    <div class="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-sky-500/12 blur-[6rem]" />

    <div class="panel panel-glow relative w-full max-w-lg p-7 sm:p-9">
      <RouterLink to="/" class="mb-7 inline-flex items-center gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-xl2 bg-gradient-to-br from-violet-500 via-violet-400 to-amber-300">
          <Sparkles class="h-5 w-5 text-ink-950" />
        </div>
        <div class="leading-tight">
          <p class="font-display text-[0.95rem] font-semibold text-white">Lumen</p>
          <p class="text-[0.68rem] tracking-wide text-fog-500">Classroom OS</p>
        </div>
      </RouterLink>

      <h1 class="font-display text-2xl font-semibold text-white">Create your account</h1>
      <p class="mt-1.5 text-sm text-fog-400">Choose how you will use Lumen. You can be re-assigned later.</p>

      <div class="mt-6 grid gap-3 sm:grid-cols-2">
        <button
          v-for="option in roles"
          :key="option.value"
          type="button"
          class="rounded-xl2 border p-4 text-left transition"
          :class="
            form.role === option.value
              ? 'border-violet-400/60 bg-violet-500/12 shadow-[0_0_0_3px_rgba(139,92,246,0.15)]'
              : 'border-white/8 bg-white/3 hover:border-white/20'
          "
          @click="form.role = option.value"
        >
          <span
            class="grid h-9 w-9 place-items-center rounded-xl"
            :class="form.role === option.value ? 'bg-violet-500/25 text-violet-100' : 'bg-white/6 text-fog-400'"
          >
            <component :is="option.icon" class="h-4 w-4" />
          </span>
          <span class="mt-3 block font-display text-sm font-semibold text-white">{{ option.label }}</span>
          <span class="mt-1 block text-[0.7rem] leading-relaxed text-fog-500">{{ option.blurb }}</span>
        </button>
      </div>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <BaseInput v-model="form.name" label="Full name" placeholder="Alex Mercer" :error="errors.name" autocomplete="name" />
        <BaseInput v-model="form.email" label="Email" type="email" placeholder="you@lumen.test" :error="errors.email" autocomplete="email" />
        <BaseInput
          v-model="form.headline"
          label="Headline (optional)"
          placeholder="Year 2 student · Computer Science"
          :error="errors.headline"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="form.password"
            label="Password"
            type="password"
            placeholder="At least 8 characters"
            :error="errors.password"
            autocomplete="new-password"
          />
          <BaseInput
            v-model="form.password_confirmation"
            label="Confirm password"
            type="password"
            placeholder="Repeat password"
            :error="passwordMismatch ? 'Passwords do not match.' : undefined"
            autocomplete="new-password"
          />
        </div>

        <BaseButton type="submit" block size="lg" :loading="busy" :disabled="!canSubmit">
          <template #icon><UserPlus class="h-4 w-4" /></template>
          Create account
        </BaseButton>
      </form>

      <p class="mt-6 text-center text-sm text-fog-400">
        Already have an account?
        <RouterLink :to="{ name: 'login' }" class="link-quiet ml-1 font-medium">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
