<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  Ban,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  Trash2,
  UserRound,
  Users,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { ROLE_LABEL, ROLE_TONE } from '@/lib/accents'
import { formatDate, fromNow } from '@/lib/format'
import type { Paginated, Role, User } from '@/types'

const auth = useAuthStore()
const toast = useToastStore()

const users = ref<User[]>([])
const meta = ref<Paginated<User>['meta'] | null>(null)
const loading = ref(true)
const page = ref(1)
const search = ref('')
const roleFilter = ref<'all' | Role>('all')
const statusFilter = ref<'all' | 'active' | 'suspended'>('all')

const editorOpen = ref(false)
const editing = ref<User | null>(null)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const target = ref<User | null>(null)
const confirmDelete = ref(false)
const confirmSuspend = ref(false)

const form = reactive({ name: '', email: '', password: '', role: 'student' as Role, headline: '', phone: '', status: 'active' })

const roleOptions = [
  { value: 'admin', label: 'Administrator' },
  { value: 'teacher', label: 'Teacher' },
  { value: 'student', label: 'Student' },
]

const statusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'suspended', label: 'Suspended' },
]

const title = computed(() => (editing.value ? `Edit ${editing.value.name}` : 'Add a person'))

const stats = computed(() => ({
  total: meta.value?.total ?? 0,
  teachers: users.value.filter((user) => user.role === 'teacher').length,
  students: users.value.filter((user) => user.role === 'student').length,
  suspended: users.value.filter((user) => user.status === 'suspended').length,
}))

async function load() {
  loading.value = true

  try {
    const payload = await request<Paginated<User>>({
      url: '/admin/users',
      params: {
        page: page.value,
        per_page: 15,
        search: search.value || undefined,
        role: roleFilter.value === 'all' ? undefined : roleFilter.value,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
      },
    })

    users.value = payload.data
    meta.value = payload.meta ?? null
  } catch (error) {
    toast.error('Could not load the directory', errorMessage(error))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', email: '', password: '', role: 'student', headline: '', phone: '', status: 'active' })
  errors.value = {}
  editorOpen.value = true
}

function openEdit(user: User) {
  editing.value = user
  Object.assign(form, {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    headline: user.headline ?? '',
    phone: user.phone ?? '',
    status: user.status,
  })
  errors.value = {}
  editorOpen.value = true
}

async function save() {
  busy.value = true
  errors.value = {}

  try {
    const payload = editing.value
      ? await request<{ message: string; user: User }>({
          url: `/admin/users/${editing.value.id}`,
          method: 'PATCH',
          data: { ...form, password: form.password || null },
        })
      : await request<{ message: string; user: User }>({
          url: '/admin/users',
          method: 'POST',
          data: form,
        })

    toast.success(payload.message)
    editorOpen.value = false
    await load()
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not save', errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function toggleStatus(user: User) {
  const next = user.status === 'active' ? 'suspended' : 'active'
  confirmSuspend.value = false

  try {
    const payload = await request<{ message: string; user: User }>({
      url: `/admin/users/${user.id}`,
      method: 'PATCH',
      data: { status: next },
    })

    user.status = payload.user.status
    toast.success(payload.message)
  } catch (error) {
    toast.error('Could not update status', errorMessage(error))
  }
}

async function remove() {
  confirmDelete.value = false
  if (!target.value) return

  try {
    await request({ url: `/admin/users/${target.value.id}`, method: 'DELETE' })
    toast.success(`${target.value.name} was removed`)
    target.value = null
    await load()
  } catch (error) {
    toast.error('Could not remove that account', errorMessage(error))
  }
}

function changePage(step: number) {
  if (!meta.value) return
  const next = page.value + step
  if (next < 1 || next > meta.value.last_page) return
  page.value = next
  load()
}

let debounce: number | undefined

watch(search, () => {
  window.clearTimeout(debounce)
  debounce = window.setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

watch([roleFilter, statusFilter], () => {
  page.value = 1
  load()
})

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      eyebrow="Administration"
      title="People directory"
      description="Every account in the system. Suspend, promote, or create people."
    >
      <template #actions>
        <BaseButton size="sm" @click="openCreate">
          <template #icon><Plus class="h-3.5 w-3.5" /></template>
          Add person
        </BaseButton>
      </template>
    </PageHeader>

    <div class="mb-5 grid gap-3 sm:grid-cols-4">
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Total people</p>
        <p class="mt-1 font-display text-2xl font-semibold text-white">{{ stats.total }}</p>
      </div>
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Teachers here</p>
        <p class="mt-1 font-display text-2xl font-semibold text-violet-300">{{ stats.teachers }}</p>
      </div>
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Students here</p>
        <p class="mt-1 font-display text-2xl font-semibold text-sky-300">{{ stats.students }}</p>
      </div>
      <div class="panel p-4">
        <p class="text-[0.66rem] tracking-wide text-fog-500 uppercase">Suspended</p>
        <p class="mt-1 font-display text-2xl font-semibold" :class="stats.suspended ? 'text-rose-300' : 'text-white'">
          {{ stats.suspended }}
        </p>
      </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="relative min-w-[13rem] flex-1 sm:max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-fog-500" />
        <input v-model="search" type="search" placeholder="Search name or email" class="field h-9 py-0 pl-9 text-sm" />
      </div>
      <BaseSelect v-model="roleFilter" class="w-40" :options="[{ value: 'all', label: 'Every role' }, ...roleOptions]" />
      <BaseSelect v-model="statusFilter" class="w-40" :options="[{ value: 'all', label: 'Any status' }, ...statusOptions]" />
    </div>

    <div class="panel overflow-hidden">
      <div v-if="loading" class="space-y-2 p-5">
        <div v-for="n in 6" :key="n" class="panel p-3"><SkeletonBlock :rows="1" /></div>
      </div>

      <EmptyState
        v-else-if="!users.length"
        :icon="Users"
        title="Nobody matches those filters"
        description="Try clearing the search or switching the role filter."
      />

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[48rem] border-collapse text-sm">
          <thead>
            <tr class="border-b border-white/7 text-left">
              <th class="px-5 py-3 text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Person</th>
              <th class="px-3 py-3 text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Role</th>
              <th class="px-3 py-3 text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Status</th>
              <th class="px-3 py-3 text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Last seen</th>
              <th class="px-3 py-3 text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Joined</th>
              <th class="px-5 py-3 text-right text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-b border-white/4 transition hover:bg-white/3">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <BaseAvatar :name="user.name" :initials="user.initials" :role="user.role" size="sm" />
                  <div class="min-w-0">
                    <p class="flex items-center gap-1.5 truncate text-sm font-medium text-white">
                      {{ user.name }}
                      <span v-if="user.id === auth.user?.id" class="chip border-violet-400/30 text-violet-200">You</span>
                    </p>
                    <p class="truncate text-[0.68rem] text-fog-500">{{ user.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3"><BaseBadge :tone="ROLE_TONE[user.role]" size="xs">{{ ROLE_LABEL[user.role] }}</BaseBadge></td>
              <td class="px-3 py-3">
                <BaseBadge :tone="user.status === 'active' ? 'emerald' : 'rose'" size="xs">
                  <template #icon>
                    <CheckCircle2 v-if="user.status === 'active'" class="h-2.5 w-2.5" />
                    <Ban v-else class="h-2.5 w-2.5" />
                  </template>
                  {{ user.status }}
                </BaseBadge>
              </td>
              <td class="px-3 py-3 text-xs text-fog-400">{{ fromNow(user.last_seen_at) }}</td>
              <td class="px-3 py-3 text-xs text-fog-400">{{ formatDate(user.created_at) }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-1">
                  <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white" title="Edit" @click="openEdit(user)">
                    <Pencil class="h-3.5 w-3.5" />
                  </button>
                  <button
                    class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-amber-400/12 hover:text-amber-300"
                    :title="user.status === 'active' ? 'Suspend' : 'Reinstate'"
                    @click="target = user; confirmSuspend = true"
                  >
                    <component :is="user.status === 'active' ? Ban : RotateCcw" class="h-3.5 w-3.5" />
                  </button>
                  <button
                    class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-rose-400/12 hover:text-rose-300 disabled:opacity-30"
                    title="Delete"
                    :disabled="user.id === auth.user?.id"
                    @click="target = user; confirmDelete = true"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-white/7 px-5 py-3">
        <p class="text-[0.7rem] text-fog-500">
          Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} people
        </p>
        <div class="flex items-center gap-1">
          <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white disabled:opacity-30" :disabled="page <= 1" @click="changePage(-1)">
            <ChevronLeft class="h-4 w-4" />
          </button>
          <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 transition hover:bg-white/8 hover:text-white disabled:opacity-30" :disabled="page >= meta.last_page" @click="changePage(1)">
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <BaseModal :open="editorOpen" :title="title" size="md" @close="editorOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <BaseInput v-model="form.name" label="Full name" :error="errors.name" />
        <BaseInput v-model="form.email" label="Email" type="email" :error="errors.email" />
        <BaseInput
          v-model="form.password"
          :label="editing ? 'New password (optional)' : 'Temporary password'"
          type="password"
          :hint="editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.'"
          :error="errors.password"
        />
        <div class="grid gap-4 sm:grid-cols-2">
          <BaseSelect v-model="form.role" label="Role" :options="roleOptions" />
          <BaseSelect v-if="editing" v-model="form.status" label="Status" :options="statusOptions" />
        </div>
        <BaseInput v-model="form.headline" label="Headline" placeholder="Studio Lead, Visual Communication" />
        <BaseInput v-model="form.phone" label="Phone" placeholder="Optional" />
      </form>

      <template #footer>
        <BaseButton variant="ghost" @click="editorOpen = false">Cancel</BaseButton>
        <BaseButton :loading="busy" :disabled="!form.name || !form.email || (!editing && form.password.length < 8)" @click="save">
          <template #icon>
            <UserRound v-if="editing" class="h-4 w-4" />
            <Plus v-else class="h-4 w-4" />
          </template>
          {{ editing ? 'Save changes' : 'Create account' }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :open="confirmSuspend"
      :title="target?.status === 'active' ? `Suspend ${target?.name}?` : `Reinstate ${target?.name}?`"
      :description="
        target?.status === 'active'
          ? 'They will be signed out and blocked from signing in until reinstated.'
          : 'They will be able to sign in again immediately.'
      "
      :confirm-label="target?.status === 'active' ? 'Suspend' : 'Reinstate'"
      :tone="target?.status === 'active' ? 'danger' : 'primary'"
      @confirm="target && toggleStatus(target)"
      @close="confirmSuspend = false"
    />

    <ConfirmDialog
      :open="confirmDelete"
      :title="`Delete ${target?.name}?`"
      description="This removes the account permanently. Consider suspending instead so the history is preserved."
      confirm-label="Delete permanently"
      tone="danger"
      @confirm="remove"
      @close="confirmDelete = false"
    />
  </div>
</template>
