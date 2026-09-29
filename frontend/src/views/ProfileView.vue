<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  AtSign,
  BadgeCheck,
  KeyRound,
  LogOut,
  Phone,
  Save,
  ShieldCheck,
  Sparkles,
  UserRound,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors } from '@/lib/api'
import { ROLE_LABEL, ROLE_TONE } from '@/lib/accents'
import { formatDate, fromNow } from '@/lib/format'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const savingProfile = ref(false)
const savingPassword = ref(false)
const signingOut = ref(false)
const confirmSignOut = ref(false)
const profileErrors = ref<Record<string, string>>({})
const passwordErrors = ref<Record<string, string>>({})

const profile = reactive({ name: '', email: '', headline: '', phone: '' })
const password = reactive({ current_password: '', password: '', password_confirmation: '' })

const initials = computed(() => auth.user?.initials ?? '?')

function hydrate() {
  profile.name = auth.user?.name ?? ''
  profile.email = auth.user?.email ?? ''
  profile.headline = auth.user?.headline ?? ''
  profile.phone = auth.user?.phone ?? ''
}

hydrate()

async function saveProfile() {
  savingProfile.value = true
  profileErrors.value = {}

  try {
    const message = await auth.updateProfile({
      name: profile.name,
      email: profile.email,
      headline: profile.headline || null,
      phone: profile.phone || null,
    })

    toast.success(message)
    hydrate()
  } catch (error) {
    profileErrors.value = fieldErrors(error)
    toast.error('Could not save your profile', errorMessage(error))
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  passwordErrors.value = {}

  try {
    const message = await auth.changePassword(
      password.current_password,
      password.password,
      password.password_confirmation,
    )

    toast.success(message)
    Object.assign(password, { current_password: '', password: '', password_confirmation: '' })
  } catch (error) {
    passwordErrors.value = fieldErrors(error)
    toast.error('Could not change your password', errorMessage(error))
  } finally {
    savingPassword.value = false
  }
}

async function signOut() {
  confirmSignOut.value = false
  signingOut.value = true

  await auth.logoutRemote()
  toast.info('Signed out', 'See you soon.')
  router.push({ name: 'login' })
}
</script>

<template>
  <div>
    <PageHeader eyebrow="Account" title="Your profile" description="How you appear to teachers, classmates, and administrators." />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
      <div class="space-y-5">
        <section class="panel p-6">
          <header class="mb-5 flex items-center gap-4 border-b border-white/7 pb-5">
            <BaseAvatar :name="auth.user?.name ?? ''" :initials="initials" :role="auth.role ?? 'student'" size="xl" ring />
            <div class="min-w-0">
              <h2 class="truncate font-display text-lg font-semibold text-white">{{ auth.user?.name }}</h2>
              <p class="truncate text-xs text-fog-400">{{ auth.user?.headline ?? 'No headline yet' }}</p>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <BaseBadge :tone="ROLE_TONE[auth.role ?? 'student']" size="xs">
                  <template #icon><ShieldCheck class="h-2.5 w-2.5" /></template>
                  {{ ROLE_LABEL[auth.role ?? 'student'] }}
                </BaseBadge>
                <BaseBadge v-if="auth.user?.last_seen_at" tone="emerald" size="xs">Active {{ fromNow(auth.user.last_seen_at) }}</BaseBadge>
              </div>
            </div>
          </header>

          <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
            <BaseInput v-model="profile.name" label="Full name" :error="profileErrors.name">
              <template #prefix><UserRound class="h-3.5 w-3.5" /></template>
            </BaseInput>

            <BaseInput v-model="profile.email" label="Email" type="email" :error="profileErrors.email">
              <template #prefix><AtSign class="h-3.5 w-3.5" /></template>
            </BaseInput>

            <BaseInput
              v-model="profile.headline"
              label="Headline"
              placeholder="Studio Lead, Visual Communication"
              :error="profileErrors.headline"
            />

            <BaseInput v-model="profile.phone" label="Phone" placeholder="Optional" :error="profileErrors.phone">
              <template #prefix><Phone class="h-3.5 w-3.5" /></template>
            </BaseInput>

            <div class="flex justify-end sm:col-span-2">
              <BaseButton type="submit" :loading="savingProfile">
                <template #icon><Save class="h-4 w-4" /></template>
                Save profile
              </BaseButton>
            </div>
          </form>
        </section>

        <section class="panel p-6">
          <header class="mb-5 flex items-center gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-xl2 bg-amber-400/12 text-amber-300">
              <KeyRound class="h-4 w-4" />
            </span>
            <div>
              <h2 class="font-display text-base font-semibold text-white">Password</h2>
              <p class="text-xs text-fog-500">At least 8 characters. Use something you have not reused.</p>
            </div>
          </header>

          <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="savePassword">
            <BaseInput
              v-model="password.current_password"
              label="Current password"
              type="password"
              autocomplete="current-password"
              class="sm:col-span-2"
              :error="passwordErrors.current_password"
            />
            <BaseInput
              v-model="password.password"
              label="New password"
              type="password"
              autocomplete="new-password"
              :error="passwordErrors.password"
            />
            <BaseInput
              v-model="password.password_confirmation"
              label="Confirm new password"
              type="password"
              autocomplete="new-password"
              :error="passwordErrors.password_confirmation"
            />

            <div class="flex justify-end sm:col-span-2">
              <BaseButton
                type="submit"
                :loading="savingPassword"
                :disabled="!password.current_password || password.password.length < 8"
              >
                <template #icon><KeyRound class="h-4 w-4" /></template>
                Update password
              </BaseButton>
            </div>
          </form>
        </section>
      </div>

      <aside class="space-y-4">
        <div class="panel p-5">
          <h3 class="flex items-center gap-2 font-display text-sm font-semibold text-white">
            <Sparkles class="h-4 w-4 text-violet-300" />At a glance
          </h3>
          <dl class="mt-4 space-y-3 text-xs">
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Classrooms</dt>
              <dd class="font-semibold text-white">{{ auth.stats?.courses_count ?? 0 }}</dd>
            </div>
            <div v-if="auth.isTeacher" class="flex items-center justify-between">
              <dt class="text-fog-500">Teaching</dt>
              <dd class="font-semibold text-white">{{ auth.stats?.taught_courses_count ?? 0 }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Unread messages</dt>
              <dd class="font-semibold text-white">{{ auth.stats?.unread_messages ?? 0 }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Joined</dt>
              <dd class="font-semibold text-white">{{ formatDate(auth.user?.created_at) }}</dd>
            </div>
          </dl>
        </div>

        <div class="panel p-5">
          <h3 class="flex items-center gap-2 font-display text-sm font-semibold text-white">
            <BadgeCheck class="h-4 w-4 text-emerald-300" />Account status
          </h3>
          <p class="mt-3 text-xs leading-relaxed text-fog-400">
            <template v-if="auth.user?.is_active">Your account is in good standing.</template>
            <template v-else>Your account is currently restricted. Contact an administrator.</template>
          </p>
        </div>

        <BaseButton variant="danger" class="w-full" :loading="signingOut" @click="confirmSignOut = true">
          <template #icon><LogOut class="h-4 w-4" /></template>
          Sign out
        </BaseButton>
      </aside>
    </div>

    <ConfirmDialog
      :open="confirmSignOut"
      title="Sign out of Lumen?"
      description="You will need your email and password to get back in."
      confirm-label="Sign out"
      @confirm="signOut"
      @close="confirmSignOut = false"
    />
  </div>
</template>
