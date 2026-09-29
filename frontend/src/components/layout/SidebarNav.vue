<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
  Activity,
  BookOpen,
  CalendarDays,
  LayoutDashboard,
  LogOut,
  MessagesSquare,
  Settings,
  Sparkles,
  Users,
  X,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import { ROLE_LABEL } from '@/lib/accents'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()

const { user, stats } = storeToRefs(auth)

interface NavLink {
  name: string
  label: string
  icon: typeof BookOpen
  badge?: number
  roles?: string[]
}

const links = computed<NavLink[]>(() => [
  { name: 'dashboard', label: 'Overview', icon: LayoutDashboard },
  { name: 'courses', label: 'Classrooms', icon: BookOpen },
  { name: 'calendar', label: 'Timeline', icon: CalendarDays },
  { name: 'messages', label: 'Messages', icon: MessagesSquare, badge: stats.value?.unread_messages },
  { name: 'admin-users', label: 'People directory', icon: Users, roles: ['admin'] },
  { name: 'admin-activity', label: 'System activity', icon: Activity, roles: ['admin'] },
])

const visibleLinks = computed(() => links.value.filter((link) => !link.roles || (user.value && link.roles.includes(user.value.role))))

function isActive(name: string): boolean {
  return route.name === name
}

async function signOut() {
  await auth.logoutRemote()
  toast.info('Signed out', 'See you next session.')
  router.push({ name: 'login' })
}
</script>

<template>
  <!-- Desktop rail -->
  <aside
    class="sticky top-0 hidden h-screen w-[16.5rem] shrink-0 flex-col border-r border-white/6 bg-ink-900/70 px-4 py-5 backdrop-blur-xl lg:flex"
  >
    <RouterLink :to="{ name: 'dashboard' }" class="mb-6 flex items-center gap-3 px-2">
      <div
        class="grid h-10 w-10 place-items-center rounded-xl2 bg-gradient-to-br from-violet-500 via-violet-400 to-amber-300 shadow-[0_12px_30px_-14px_rgba(139,92,246,0.9)]"
      >
        <Sparkles class="h-5 w-5 text-ink-950" />
      </div>
      <div class="leading-tight">
        <p class="font-display text-[0.95rem] font-semibold text-white">Lumen</p>
        <p class="text-[0.68rem] tracking-wide text-fog-500">Classroom OS</p>
      </div>
    </RouterLink>

    <nav class="flex flex-1 flex-col gap-1">
      <RouterLink
        v-for="link in visibleLinks"
        :key="link.name"
        :to="{ name: link.name }"
        class="nav-item"
        :class="isActive(link.name) ? 'is-active' : ''"
        @click="emit('close')"
      >
        <component :is="link.icon" class="h-[1.05rem] w-[1.05rem]" />
        <span class="flex-1">{{ link.label }}</span>
        <span
          v-if="link.badge"
          class="rounded-full bg-violet-500 px-1.5 py-0.5 text-[0.62rem] font-bold text-white"
        >
          {{ link.badge }}
        </span>
      </RouterLink>
    </nav>

    <div class="panel-quiet mt-4 p-3">
      <p class="mb-2.5 text-[0.65rem] font-semibold tracking-[0.14em] text-fog-500 uppercase">Signed in as</p>
      <div class="flex items-center gap-3">
        <BaseAvatar v-if="user" :name="user.name" :role="user.role" size="sm" ring />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-white">{{ user?.name }}</p>
          <p class="truncate text-[0.7rem] text-fog-500">{{ user ? ROLE_LABEL[user.role] : '' }}</p>
        </div>
      </div>
      <div class="mt-3 flex gap-2">
        <RouterLink
          :to="{ name: 'profile' }"
          class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-white/8 px-2 py-1.5 text-[0.7rem] font-medium text-fog-300 transition hover:border-white/20 hover:text-white"
        >
          <Settings class="h-3.5 w-3.5" />
          Profile
        </RouterLink>
        <button
          class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-white/8 px-2 py-1.5 text-[0.7rem] font-medium text-fog-300 transition hover:border-rose-400/40 hover:text-rose-300"
          @click="signOut"
        >
          <LogOut class="h-3.5 w-3.5" />
          Sign out
        </button>
      </div>
    </div>
  </aside>

  <!-- Mobile drawer -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 lg:hidden">
        <div class="absolute inset-0 bg-ink-950/80 backdrop-blur-sm" @click="emit('close')" />
        <aside
          class="anim-slide-in absolute inset-y-0 left-0 flex w-[17rem] flex-col border-r border-white/8 bg-ink-900 px-4 py-5"
        >
          <div class="mb-6 flex items-center justify-between px-2">
            <div class="flex items-center gap-3">
              <div class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-amber-300">
                <Sparkles class="h-4 w-4 text-ink-950" />
              </div>
              <p class="font-display text-sm font-semibold text-white">Lumen</p>
            </div>
            <button
              class="grid h-8 w-8 place-items-center rounded-full text-fog-400 hover:bg-white/8 hover:text-white"
              @click="emit('close')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <nav class="flex flex-1 flex-col gap-1">
            <RouterLink
              v-for="link in visibleLinks"
              :key="link.name"
              :to="{ name: link.name }"
              class="nav-item"
              :class="isActive(link.name) ? 'is-active' : ''"
              @click="emit('close')"
            >
              <component :is="link.icon" class="h-[1.05rem] w-[1.05rem]" />
              <span class="flex-1">{{ link.label }}</span>
              <span
                v-if="link.badge"
                class="rounded-full bg-violet-500 px-1.5 py-0.5 text-[0.62rem] font-bold text-white"
              >
                {{ link.badge }}
              </span>
            </RouterLink>
          </nav>

          <button
            class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-white/8 px-3 py-2.5 text-xs font-semibold text-fog-300 transition hover:border-rose-400/40 hover:text-rose-300"
            @click="signOut"
          >
            <LogOut class="h-4 w-4" /> Sign out
          </button>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>
