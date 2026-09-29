<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ArrowLeft, Compass, Home, Sparkles } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center px-6 py-16 text-center">
    <div class="relative mb-8">
      <div class="absolute inset-0 -z-10 rounded-full bg-violet-500/25 blur-3xl" />
      <span class="grid h-20 w-20 place-items-center rounded-3xl border border-white/10 bg-white/5 text-violet-200">
        <Compass class="h-9 w-9" />
      </span>
    </div>

    <p class="font-display text-6xl font-semibold tracking-tight text-white">404</p>
    <h1 class="mt-4 font-display text-2xl font-semibold text-white">This page wandered off</h1>
    <p class="mt-3 max-w-md text-sm leading-relaxed text-fog-400">
      The link may be outdated, or the classroom might have been archived. Nothing was deleted, it is just not here.
    </p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
      <BaseButton variant="ghost" @click="router.back()">
        <template #icon><ArrowLeft class="h-4 w-4" /></template>
        Go back
      </BaseButton>

      <BaseButton v-if="auth.isAuthenticated" to="/dashboard">
        <template #icon><Home class="h-4 w-4" /></template>
        Your dashboard
      </BaseButton>

      <BaseButton v-else to="/">
        <template #icon><Sparkles class="h-4 w-4" /></template>
        Back to Lumen
      </BaseButton>
    </div>
  </div>
</template>
