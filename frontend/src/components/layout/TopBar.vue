<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Bell, ChevronRight, Command, Menu, Plus, Search } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/courses'
import { accentOf } from '@/lib/accents'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps<{ onMenu: () => void; onPalette: () => void; onQuickCreate: () => void }>()

const auth = useAuthStore()
const courses = useCourseStore()
const route = useRoute()
const router = useRouter()

const { user, stats } = storeToRefs(auth)
const { items: courseItems } = storeToRefs(courses)

const search = ref('')
const showResults = ref(false)
const searchRoot = ref<HTMLElement | null>(null)

const results = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (term.length < 2) return []

  return courseItems.value
    .filter(
      (course) =>
        course.title.toLowerCase().includes(term) ||
        course.code.toLowerCase().includes(term) ||
        (course.subject ?? '').toLowerCase().includes(term),
    )
    .slice(0, 6)
})

const crumbs = computed(() => {
  const list: { label: string; to?: string }[] = []
  const title = route.meta.title as string | undefined

  if (title && route.name !== 'dashboard') list.push({ label: title })
  return list
})

function go(courseId: number) {
  showResults.value = false
  search.value = ''
  router.push({ name: 'course', params: { id: courseId } })
}

function onDocumentClick(event: MouseEvent) {
  if (searchRoot.value && !searchRoot.value.contains(event.target as Node)) {
    showResults.value = false
  }
}

function onShortcut(event: KeyboardEvent) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    props.onPalette()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  window.addEventListener('keydown', onShortcut)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  window.removeEventListener('keydown', onShortcut)
})
</script>

<template>
  <header
    class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-white/6 bg-ink-950/70 px-4 backdrop-blur-xl sm:px-6"
  >
    <button
      class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-white/8 text-fog-300 transition hover:border-white/20 hover:text-white lg:hidden"
      aria-label="Open navigation"
      @click="onMenu"
    >
      <Menu class="h-4 w-4" />
    </button>

    <nav class="hidden min-w-0 items-center gap-1.5 text-xs text-fog-500 md:flex">
      <RouterLink :to="{ name: 'dashboard' }" class="transition hover:text-fog-200">Lumen</RouterLink>
      <template v-for="crumb in crumbs" :key="crumb.label">
        <ChevronRight class="h-3.5 w-3.5 opacity-50" />
        <span class="truncate font-medium text-fog-200">{{ crumb.label }}</span>
      </template>
    </nav>

    <div ref="searchRoot" class="relative ml-auto w-full max-w-xs">
      <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-fog-500" />
      <input
        v-model="search"
        type="search"
        placeholder="Search classrooms"
        class="field h-9 py-0 pl-9 pr-16 text-sm"
        @focus="showResults = true"
        @input="showResults = true"
      />
      <kbd
        class="pointer-events-none absolute right-2.5 top-1/2 hidden -translate-y-1/2 items-center gap-0.5 rounded-md border border-white/10 bg-white/5 px-1.5 py-0.5 text-[0.62rem] font-medium text-fog-400 sm:flex"
      >
        <Command class="h-2.5 w-2.5" />K
      </kbd>

      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        leave-active-class="transition duration-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showResults && results.length" class="panel panel-glow absolute inset-x-0 top-11 z-40 overflow-hidden p-1.5">
          <button
            v-for="course in results"
            :key="course.id"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-white/6"
            @click="go(course.id)"
          >
            <span
              class="grid h-7 w-7 shrink-0 place-items-center rounded-lg font-display text-[0.62rem] font-bold text-white"
              :style="{ background: accentOf(course.accent).soft, color: accentOf(course.accent).text }"
            >
              {{ course.code.slice(0, 2) }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-white">{{ course.title }}</span>
              <span class="block truncate text-[0.7rem] text-fog-500">{{ course.code }} · {{ course.subject ?? 'General' }}</span>
            </span>
          </button>
        </div>
      </Transition>
    </div>

    <button
      class="relative grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-white/8 text-fog-300 transition hover:border-white/20 hover:text-white"
      aria-label="Notifications"
      @click="router.push({ name: 'messages' })"
    >
      <Bell class="h-4 w-4" />
      <span
        v-if="stats?.unread_messages"
        class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-violet-500 px-1 text-[0.58rem] font-bold text-white"
      >
        {{ stats.unread_messages }}
      </span>
    </button>

    <BaseButton class="hidden shrink-0 sm:inline-flex" size="sm" @click="onQuickCreate">
      <template #icon><Plus class="h-3.5 w-3.5" /></template>
      New
    </BaseButton>

    <RouterLink :to="{ name: 'profile' }" class="shrink-0">
      <BaseAvatar v-if="user" :name="user.name" :role="user.role" size="sm" ring />
    </RouterLink>
  </header>
</template>
