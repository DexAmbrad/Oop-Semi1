<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ToastHost from '@/components/ui/ToastHost.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()

const bare = computed(() => route.meta.bare === true)
const useShell = computed(() => auth.isAuthenticated && !bare.value)
</script>

<template>
  <AppShell v-if="useShell">
    <RouterView v-slot="{ Component }">
      <Transition
        enter-active-class="transition duration-250 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
      >
        <component :is="Component" :key="route.name === 'course' ? String(route.params.id) : route.fullPath" />
      </Transition>
    </RouterView>
  </AppShell>

  <RouterView v-else />

  <ToastHost />
</template>
