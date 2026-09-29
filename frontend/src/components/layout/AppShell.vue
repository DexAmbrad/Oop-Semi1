<script setup lang="ts">
import { onMounted, ref } from 'vue'
import SidebarNav from './SidebarNav.vue'
import TopBar from './TopBar.vue'
import CommandPalette from './CommandPalette.vue'
import QuickCreateModal from '@/components/course/QuickCreateModal.vue'
import { useCourseStore } from '@/stores/courses'

const courses = useCourseStore()

const drawerOpen = ref(false)
const paletteOpen = ref(false)
const quickCreateOpen = ref(false)

onMounted(() => {
  courses.fetchAll().catch(() => undefined)
})
</script>

<template>
  <div class="grain-safe relative flex min-h-screen">
    <SidebarNav :open="drawerOpen" @close="drawerOpen = false" />

    <div class="flex min-w-0 flex-1 flex-col">
      <TopBar
        :on-menu="() => (drawerOpen = true)"
        :on-palette="() => (paletteOpen = true)"
        :on-quick-create="() => (quickCreateOpen = true)"
      />

      <main class="mx-auto w-full max-w-[86rem] flex-1 px-4 py-6 sm:px-6 sm:py-8">
        <slot />
      </main>

      <footer class="border-t border-white/6 px-6 py-5 text-center text-[0.7rem] text-fog-500">
        Lumen Classroom · built with Laravel, Vue and TypeScript
      </footer>
    </div>

    <CommandPalette :open="paletteOpen" @close="paletteOpen = false" />
    <QuickCreateModal :open="quickCreateOpen" @close="quickCreateOpen = false" />
  </div>
</template>
