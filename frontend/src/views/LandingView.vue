<script setup lang="ts">
import { ref } from 'vue'
import {
  ArrowRight,
  BarChart3,
  BookOpen,
  ClipboardCheck,
  Globe2,
  Layers,
  MessagesSquare,
  ShieldCheck,
  Sparkles,
  Zap,
} from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'

const activeFeature = ref(0)

const features = [
  {
    icon: BookOpen,
    title: 'Classrooms that breathe',
    body: 'Accent-coded spaces with a stream, a roster and a resource shelf. Everything a cohort needs, nothing they don\'t.',
  },
  {
    icon: ClipboardCheck,
    title: 'Work worth grading',
    body: 'Assignments with deadlines, late policies and inline feedback. Bulk grading for the times you need speed.',
  },
  {
    icon: BarChart3,
    title: 'A gradebook you trust',
    body: 'A live matrix of students against items, with weighted totals and class averages computed as you type.',
  },
  {
    icon: MessagesSquare,
    title: 'Conversations with context',
    body: 'Direct threads between staff and learners that know which classroom they belong to.',
  },
]

const stats = [
  { value: '9', label: 'Live classrooms' },
  { value: '46', label: 'People onboarded' },
  { value: '100%', label: 'Laravel API' },
  { value: '0', label: 'Spreadsheets' },
]
</script>

<template>
  <div class="grain-safe relative min-h-screen overflow-x-hidden">
    <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
      <div class="flex items-center gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-xl2 bg-gradient-to-br from-violet-500 via-violet-400 to-amber-300 shadow-[0_12px_30px_-14px_rgba(139,92,246,0.9)]">
          <Sparkles class="h-5 w-5 text-ink-950" />
        </div>
        <div class="leading-tight">
          <p class="font-display text-[0.95rem] font-semibold text-white">Lumen</p>
          <p class="text-[0.68rem] tracking-wide text-fog-500">Classroom OS</p>
        </div>
      </div>

      <nav class="flex items-center gap-2">
        <BaseButton variant="ghost" size="sm" to="/login">Sign in</BaseButton>
        <BaseButton size="sm" to="/register">Get started</BaseButton>
      </nav>
    </header>

    <section class="mx-auto max-w-6xl px-5 pt-10 pb-16 sm:px-8 sm:pt-16">
      <div class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="anim-rise">
          <span class="chip border-violet-400/25 bg-violet-500/10 text-violet-200">
            <Zap class="h-3 w-3" />
            Built for teaching, not administration
          </span>

          <h1 class="mt-6 font-display text-4xl leading-[1.08] font-semibold tracking-tight text-white text-balance sm:text-5xl lg:text-6xl">
            The classroom you wish
            <span class="bg-gradient-to-br from-violet-300 via-violet-400 to-amber-300 bg-clip-text text-transparent"> you had</span>
            as a student.
          </h1>

          <p class="mt-6 max-w-xl text-base leading-relaxed text-fog-300">
            Lumen replaces the tab-hoarding, spreadsheet-driven chaos of course management with one deliberate
            space: a stream for announcements, a timeline for deadlines, a gradebook that actually adds up.
          </p>

          <div class="mt-8 flex flex-wrap items-center gap-3">
            <BaseButton size="lg" to="/register">
              Create your account
              <ArrowRight class="h-4 w-4" />
            </BaseButton>
            <BaseButton size="lg" variant="outline" to="/login">
              <ShieldCheck class="h-4 w-4" />
              Explore a demo account
            </BaseButton>
          </div>

          <dl class="mt-10 grid max-w-lg grid-cols-2 gap-4 sm:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label">
              <dt class="font-display text-2xl font-semibold text-white">{{ stat.value }}</dt>
              <dd class="mt-0.5 text-[0.7rem] tracking-wide text-fog-500 uppercase">{{ stat.label }}</dd>
            </div>
          </dl>
        </div>

        <div class="anim-rise relative" style="animation-delay: 90ms">
          <div class="absolute -inset-6 rounded-[2.5rem] bg-gradient-to-br from-violet-600/25 via-transparent to-amber-400/15 blur-3xl" />

          <div class="panel panel-glow relative overflow-hidden p-4">
            <div class="flex items-center gap-2 pb-3">
              <span class="h-2.5 w-2.5 rounded-full bg-rose-400/70" />
              <span class="h-2.5 w-2.5 rounded-full bg-amber-400/70" />
              <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70" />
              <span class="ml-3 text-[0.68rem] text-fog-500">lumen.classroom/dashboard</span>
            </div>

            <div class="space-y-3">
              <div class="grid grid-cols-3 gap-3">
                <div
                  v-for="(tile, index) in [
                    { label: 'My courses', value: '4', tone: 'from-violet-600 to-violet-400' },
                    { label: 'Due this week', value: '6', tone: 'from-amber-500 to-amber-300' },
                    { label: 'Average', value: '82%', tone: 'from-sky-600 to-sky-400' },
                  ]"
                  :key="tile.label"
                  class="panel-quiet overflow-hidden p-3"
                  :style="{ animationDelay: `${120 + index * 60}ms` }"
                >
                  <p class="text-[0.6rem] tracking-wide text-fog-500 uppercase">{{ tile.label }}</p>
                  <p class="mt-1.5 font-display text-xl font-semibold text-white">{{ tile.value }}</p>
                  <div class="mt-2 h-1 rounded-full bg-gradient-to-r" :class="tile.tone" />
                </div>
              </div>

              <div class="panel-quiet space-y-2.5 p-3.5">
                <div v-for="(row, index) in [
                  { title: 'Algorithms & Data Structures', meta: 'Problem Set 4 · due tomorrow', tone: 'violet' },
                  { title: 'Cell Biology Laboratory', meta: 'Lab Report · 2 submissions to grade', tone: 'emerald' },
                  { title: 'Visual Identity Studio', meta: 'Studio Critique 2 · 14 responses', tone: 'rose' },
                ]" :key="row.title" class="flex items-center gap-3">
                  <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/6 text-fog-300">
                    <Layers class="h-3.5 w-3.5" />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-medium text-white">{{ row.title }}</span>
                    <span class="block truncate text-[0.68rem] text-fog-500">{{ row.meta }}</span>
                  </span>
                  <span class="h-6 w-1 rounded-full bg-violet-400/60" :style="{ opacity: 1 - index * 0.22 }" />
                </div>
              </div>

              <div class="flex items-center justify-between rounded-xl2 border border-white/8 bg-white/4 px-4 py-3">
                <div class="flex items-center gap-2 text-[0.7rem] text-fog-400">
                  <Globe2 class="h-3.5 w-3.5 text-violet-300" />
                  Room code <span class="font-mono font-semibold text-white">K7QT2M</span>
                </div>
                <span class="chip border-emerald-400/25 bg-emerald-400/10 text-emerald-300">Live</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 pb-20 sm:px-8">
      <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-violet-300/80 uppercase">What is inside</p>
          <h2 class="mt-2 font-display text-2xl font-semibold text-white sm:text-3xl">Four surfaces, one rhythm</h2>
        </div>
        <p class="max-w-sm text-sm leading-relaxed text-fog-400">
          Every role gets the same foundation. Administrators see the whole institution, teachers see their
          classrooms, students see what is due next.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <button
          v-for="(feature, index) in features"
          :key="feature.title"
          class="panel group relative overflow-hidden p-6 text-left transition duration-200 hover:-translate-y-0.5"
          :class="activeFeature === index ? 'panel-glow' : ''"
          @mouseenter="activeFeature = index"
        >
          <div
            class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-violet-500/15 blur-3xl transition group-hover:bg-violet-500/25"
          />
          <div class="relative flex items-start gap-4">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-violet-400/25 bg-violet-500/12 text-violet-200">
              <component :is="feature.icon" class="h-5 w-5" />
            </span>
            <div>
              <h3 class="font-display text-base font-semibold text-white">{{ feature.title }}</h3>
              <p class="mt-2 text-sm leading-relaxed text-fog-400">{{ feature.body }}</p>
            </div>
          </div>
        </button>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 pb-24 sm:px-8">
      <div class="panel panel-glow relative overflow-hidden px-6 py-10 text-center sm:px-12 sm:py-14">
        <div class="absolute inset-0 bg-gradient-to-br from-violet-600/20 via-transparent to-amber-400/10" />
        <div class="relative">
          <h2 class="font-display text-2xl font-semibold text-white sm:text-3xl">Three demo doors, one click each</h2>
          <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-fog-300">
            The database ships pre-loaded with an administrator, five teachers and forty students. Passwords are
            simply <span class="font-mono text-white">password</span>.
          </p>

          <div class="mt-8 grid gap-3 sm:grid-cols-3">
            <RouterLink
              v-for="demo in [
                { role: 'Administrator', email: 'admin@lumen.test', accent: 'amber', blurb: 'Institution-wide metrics' },
                { role: 'Teacher', email: 'ada@lumen.test', accent: 'violet', blurb: 'Three live classrooms' },
                { role: 'Student', email: 'jonah.beck@student.lumen.test', accent: 'sky', blurb: 'Four enrolments' },
              ]"
              :key="demo.role"
              to="/login"
              class="panel-quiet group p-4 text-left transition hover:border-white/20 hover:bg-white/6"
            >
              <p class="font-display text-sm font-semibold text-white">{{ demo.role }}</p>
              <p class="mt-1 text-[0.7rem] text-fog-500">{{ demo.blurb }}</p>
              <p class="mt-3 font-mono text-[0.68rem] break-all text-fog-400 group-hover:text-violet-200">{{ demo.email }}</p>
            </RouterLink>
          </div>

          <div class="mt-8">
            <BaseButton size="lg" to="/login">
              Sign in and look around
              <ArrowRight class="h-4 w-4" />
            </BaseButton>
          </div>
        </div>
      </div>
    </section>

    <footer class="border-t border-white/6 px-6 py-8 text-center text-[0.7rem] text-fog-500">
      Lumen Classroom · Laravel 12 · Vue 3 · TypeScript · Tailwind CSS
    </footer>
  </div>
</template>
