<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, KeyRound, Sparkles, Zap } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors } from '@/lib/api'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const showPassword = ref(false)

const demos = [
  { role: 'Administrator', email: 'admin@lumen.test', accent: '#fbbf24' },
  { role: 'Teacher', email: 'ada@lumen.test', accent: '#a78bfa' },
  { role: 'Student', email: 'jonah.beck@student.lumen.test', accent: '#38bdf8' },
]

const canSubmit = computed(() => email.value.length > 3 && password.value.length > 0)

function useDemo(demoEmail: string) {
  email.value = demoEmail
  password.value = 'password'
  toast.info('Demo credentials filled', 'Press sign in to continue.')
}

async function submit() {
  busy.value = true
  errors.value = {}

  try {
    const user = await auth.login(email.value, password.value)
    toast.success(`Welcome back, ${user.name.split(' ')[0]}.`)

    const redirect = route.query.redirect as string | undefined
    router.push(redirect ?? { name: 'dashboard' })
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Sign in failed', errorMessage(error))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="grain-safe relative grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
    <!-- Brand panel -->
    <section class="relative hidden flex-col justify-between overflow-hidden border-r border-white/6 p-10 lg:flex">
      <div class="absolute -left-24 top-1/4 h-96 w-96 rounded-full bg-violet-600/25 blur-[7rem]" />
      <div class="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-amber-400/15 blur-[6rem]" />

      <RouterLink to="/" class="relative flex items-center gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-xl2 bg-gradient-to-br from-violet-500 via-violet-400 to-amber-300">
          <Sparkles class="h-5 w-5 text-ink-950" />
        </div>
        <div class="leading-tight">
          <p class="font-display text-[0.95rem] font-semibold text-white">Lumen</p>
          <p class="text-[0.68rem] tracking-wide text-fog-500">Classroom OS</p>
        </div>
      </RouterLink>

      <div class="relative max-w-md">
        <h1 class="font-display text-4xl leading-tight font-semibold text-white">
          Pick up where the last session left off.
        </h1>
        <p class="mt-5 text-sm leading-relaxed text-fog-300">
          Your stream, your deadlines, your gradebook, exactly as you left them. Lumen keeps the institution in
          sync without getting in the way of teaching.
        </p>

        <ul class="mt-8 space-y-3">
          <li
            v-for="point in [
              'Accent-coded classrooms with room codes that just work',
              'Deadlines that flow into a single shared timeline',
              'Feedback that lands beside the work it describes',
            ]"
            :key="point"
            class="flex items-start gap-3 text-sm text-fog-300"
          >
            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-violet-500/15 text-violet-300">
              <Zap class="h-3 w-3" />
            </span>
            {{ point }}
          </li>
        </ul>
      </div>

      <p class="relative text-[0.7rem] text-fog-500">Laravel 12 · Vue 3 · TypeScript</p>
    </section>

    <!-- Form panel -->
    <section class="flex flex-col justify-center px-5 py-12 sm:px-10">
      <div class="mx-auto w-full max-w-sm">
        <RouterLink to="/" class="mb-8 inline-flex items-center gap-3 lg:hidden">
          <div class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-amber-300">
            <Sparkles class="h-4 w-4 text-ink-950" />
          </div>
          <p class="font-display text-sm font-semibold text-white">Lumen</p>
        </RouterLink>

        <h2 class="font-display text-2xl font-semibold text-white">Sign in</h2>
        <p class="mt-1.5 text-sm text-fog-400">Use your institution account to continue.</p>

        <form class="mt-7 space-y-4" @submit.prevent="submit">
          <BaseInput
            v-model="email"
            label="Email"
            type="email"
            placeholder="you@lumen.test"
            autocomplete="email"
            :error="errors.email"
          />

          <div>
            <BaseInput
              v-model="password"
              label="Password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              autocomplete="current-password"
              :error="errors.password"
            />
            <button
              type="button"
              class="mt-2 text-[0.7rem] font-medium text-fog-500 transition hover:text-violet-300"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'Hide password' : 'Show password' }}
            </button>
          </div>

          <BaseButton type="submit" block size="lg" :loading="busy" :disabled="!canSubmit">
            <template #icon><KeyRound class="h-4 w-4" /></template>
            Sign in
          </BaseButton>
        </form>

        <div class="my-7 flex items-center gap-3">
          <div class="divider flex-1" />
          <span class="text-[0.68rem] tracking-wide text-fog-500 uppercase">or try a demo</span>
          <div class="divider flex-1" />
        </div>

        <div class="space-y-2">
          <button
            v-for="demo in demos"
            :key="demo.email"
            class="panel-quiet flex w-full items-center gap-3 px-3.5 py-2.5 text-left transition hover:border-white/20 hover:bg-white/6"
            @click="useDemo(demo.email)"
          >
            <span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: demo.accent }" />
            <span class="min-w-0 flex-1">
              <span class="block text-xs font-semibold text-white">{{ demo.role }}</span>
              <span class="block truncate font-mono text-[0.66rem] text-fog-500">{{ demo.email }}</span>
            </span>
            <ArrowRight class="h-3.5 w-3.5 shrink-0 text-fog-500" />
          </button>
        </div>

        <p class="mt-8 text-center text-sm text-fog-400">
          No account yet?
          <RouterLink :to="{ name: 'register' }" class="link-quiet ml-1 font-medium">Create one</RouterLink>
        </p>
      </div>
    </section>
  </div>
</template>
