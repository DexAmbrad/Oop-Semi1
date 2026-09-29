<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ArchiveRestore, Copy, LogOut, Settings2, Trash2, TriangleAlert } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useToastStore } from '@/stores/toast'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { ACCENT_KEYS, COURSE_ICON_KEYS, accentOf, courseIcon } from '@/lib/accents'
import { formatDate } from '@/lib/format'
import type { Accent, Course, CourseIcon } from '@/types'

const props = defineProps<{ course: Course; canTeach: boolean; isEnrolled: boolean }>()
const emit = defineEmits<{ changed: [] }>()

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()

const busy = ref(false)
const leaving = ref(false)
const confirmLeave = ref(false)
const confirmArchive = ref(false)
const confirmDelete = ref(false)
const errors = ref<Record<string, string>>({})
const copied = ref(false)

const form = reactive({
  title: '',
  code: '',
  subject: '',
  description: '',
  accent: 'violet' as Accent,
  icon: 'book' as CourseIcon,
  term: '',
  capacity: 30,
  status: 'active' as 'active' | 'archived',
  starts_at: '',
  ends_at: '',
})

function syncForm() {
  Object.assign(form, {
    title: props.course.title,
    code: props.course.code,
    subject: props.course.subject ?? '',
    description: props.course.description ?? '',
    accent: props.course.accent,
    icon: props.course.icon,
    term: props.course.term ?? '',
    capacity: props.course.capacity,
    status: props.course.status,
    starts_at: props.course.starts_at ? props.course.starts_at.slice(0, 10) : '',
    ends_at: props.course.ends_at ? props.course.ends_at.slice(0, 10) : '',
  })
}

watch(() => props.course, syncForm, { immediate: true, deep: false })

async function save() {
  busy.value = true
  errors.value = {}

  try {
    const payload = await request<{ message: string; course: Course }>({
      url: `/courses/${props.course.id}`,
      method: 'PATCH',
      data: {
        ...form,
        capacity: Number(form.capacity),
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
      },
    })

    toast.success(payload.message)
    emit('changed')
    syncForm()
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not save changes', errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function toggleArchive() {
  const next = props.course.status === 'active' ? 'archived' : 'active'
  confirmArchive.value = false

  try {
    const payload = await request<{ message: string; course: Course }>({
      url: `/courses/${props.course.id}`,
      method: 'PATCH',
      data: { status: next },
    })

    toast.success(payload.message)
    emit('changed')
    syncForm()
  } catch (error) {
    toast.error('Could not update status', errorMessage(error))
  }
}

async function remove() {
  leaving.value = true
  confirmDelete.value = false

  try {
    await request({ url: `/courses/${props.course.id}`, method: 'DELETE' })
    toast.success('Classroom archived')
    router.push({ name: 'courses' })
  } catch (error) {
    toast.error('Could not archive classroom', errorMessage(error))
  } finally {
    leaving.value = false
  }
}

async function leave() {
  leaving.value = true
  confirmLeave.value = false

  try {
    await request({ url: `/courses/${props.course.id}/leave`, method: 'POST' })
    toast.success('You left the classroom')
    router.push({ name: 'courses' })
  } catch (error) {
    toast.error('Could not leave the classroom', errorMessage(error))
  } finally {
    leaving.value = false
  }
}

async function copyCode() {
  await navigator.clipboard.writeText(props.course.room_code)
  copied.value = true
  window.setTimeout(() => (copied.value = false), 1800)
}
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
    <div v-if="canTeach" class="space-y-5">
      <form class="panel space-y-5 p-6" @submit.prevent="save">
        <header class="flex items-center gap-3 border-b border-white/7 pb-4">
          <span class="grid h-9 w-9 place-items-center rounded-xl2 bg-violet-400/12 text-violet-300">
            <Settings2 class="h-4 w-4" />
          </span>
          <div>
            <h2 class="font-display text-base font-semibold text-white">Classroom settings</h2>
            <p class="text-xs text-fog-500">Changes are visible to everyone in this class immediately.</p>
          </div>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.title" label="Class name" :error="errors.title" />
          <BaseInput v-model="form.code" label="Course code" placeholder="PHY-101" :error="errors.code" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.subject" label="Subject" placeholder="Physics" :error="errors.subject" />
          <BaseInput v-model="form.term" label="Term" placeholder="Autumn 2026" :error="errors.term" />
        </div>

        <BaseTextarea v-model="form.description" label="Description" :rows="4" placeholder="What will this class cover?" />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model.number="form.capacity" label="Capacity" type="number" :error="errors.capacity" />
          <BaseSelect
            v-model="form.status"
            label="Status"
            :options="[
              { value: 'active', label: 'Active' },
              { value: 'archived', label: 'Archived' },
            ]"
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.starts_at" label="Starts on" type="date" />
          <BaseInput v-model="form.ends_at" label="Ends on" type="date" :error="errors.ends_at" />
        </div>

        <div>
          <p class="field-label">Accent colour</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button
              v-for="accent in ACCENT_KEYS"
              :key="accent"
              type="button"
              class="grid h-9 w-9 place-items-center rounded-full border-2 transition"
              :class="form.accent === accent ? 'scale-110 border-white' : 'border-transparent hover:border-white/30'"
              :style="{ background: accentOf(accent).soft }"
              :title="accent"
              @click="form.accent = accent"
            >
              <span class="h-4 w-4 rounded-full" :style="{ background: accentOf(accent).base }" />
            </button>
          </div>
        </div>

        <div>
          <p class="field-label">Icon</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button
              v-for="icon in COURSE_ICON_KEYS"
              :key="icon"
              type="button"
              class="grid h-9 w-9 place-items-center rounded-xl2 border transition"
              :class="
                form.icon === icon
                  ? 'border-violet-400/60 bg-violet-400/12 text-violet-200'
                  : 'border-white/8 text-fog-400 hover:border-white/20 hover:text-white'
              "
              :title="icon"
              @click="form.icon = icon"
            >
              <component :is="courseIcon(icon)" class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-white/7 pt-4">
          <BaseButton variant="ghost" @click="syncForm">Reset</BaseButton>
          <BaseButton type="submit" :loading="busy">Save changes</BaseButton>
        </div>
      </form>

      <section class="panel p-6">
        <h2 class="font-display text-base font-semibold text-white">Lifecycle</h2>
        <p class="mt-1 text-xs text-fog-500">
          Archived classrooms stay readable but stop accepting new learners.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
          <BaseButton variant="outline" @click="confirmArchive = true">
            <template #icon>
              <ArchiveRestore v-if="course.status === 'archived'" class="h-4 w-4" />
              <ArchiveRestore v-else class="h-4 w-4 rotate-180" />
            </template>
            {{ course.status === 'archived' ? 'Restore classroom' : 'Archive classroom' }}
          </BaseButton>
          <BaseButton variant="danger" @click="confirmDelete = true">
            <template #icon><Trash2 class="h-4 w-4" /></template>
            Delete permanently
          </BaseButton>
        </div>
      </section>
    </div>

    <div v-else class="space-y-5">
      <div class="panel p-6">
        <h2 class="font-display text-base font-semibold text-white">About this class</h2>
        <p class="mt-3 text-sm leading-relaxed text-fog-400">
          {{ course.description ?? 'Your teacher has not added a description yet.' }}
        </p>

        <dl class="mt-5 space-y-3 text-xs">
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Subject</dt>
            <dd class="font-medium text-white">{{ course.subject ?? '—' }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Term</dt>
            <dd class="font-medium text-white">{{ course.term ?? '—' }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Meets</dt>
            <dd class="font-medium text-white">
              {{ course.starts_at ? formatDate(course.starts_at) : 'Flexible' }}
            </dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Status</dt>
            <dd><BaseBadge :tone="course.status === 'active' ? 'emerald' : 'amber'" size="xs">{{ course.status }}</BaseBadge></dd>
          </div>
        </dl>
      </div>

      <div v-if="isEnrolled" class="panel border-rose-400/20 p-6">
        <h2 class="font-display text-base font-semibold text-white">Leave classroom</h2>
        <p class="mt-1 text-xs text-fog-500">
          You can rejoin later with the room code, but you will lose access to drafts.
        </p>
        <BaseButton class="mt-4" variant="danger" :loading="leaving" @click="confirmLeave = true">
          <template #icon><LogOut class="h-4 w-4" /></template>
          Leave {{ course.code }}
        </BaseButton>
      </div>
    </div>

    <aside class="space-y-4">
      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">Join code</h3>
        <button
          class="mt-3 flex w-full items-center justify-between rounded-xl2 border border-dashed border-violet-400/35 bg-violet-400/8 px-4 py-3 transition hover:bg-violet-400/12"
          @click="copyCode"
        >
          <span class="font-display text-xl font-semibold tracking-[0.3em] text-violet-200">{{ course.room_code }}</span>
          <span class="text-[0.68rem] font-medium text-violet-300">{{ copied ? 'Copied!' : 'Copy' }}</span>
        </button>
        <p class="mt-2 text-[0.7rem] leading-relaxed text-fog-500">
          Learners use this code to enrol in <span class="text-fog-300">{{ course.code }}</span>.
        </p>
      </div>

      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">At a glance</h3>
        <dl class="mt-4 space-y-3 text-xs">
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Learners</dt>
            <dd class="font-semibold text-white">{{ course.students_count }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Tasks</dt>
            <dd class="font-semibold text-white">{{ course.assignments_count }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Posts</dt>
            <dd class="font-semibold text-white">{{ course.announcements_count }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Resources</dt>
            <dd class="font-semibold text-white">{{ course.materials_count }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="canTeach" class="panel border-amber-400/20 p-5">
        <div class="flex items-center gap-2 text-amber-300">
          <TriangleAlert class="h-4 w-4" />
          <h3 class="font-display text-sm font-semibold">Careful</h3>
        </div>
        <p class="mt-2 text-[0.72rem] leading-relaxed text-fog-400">
          Deleting a classroom removes its coursework, grades, and messages for everyone.
        </p>
      </div>

      <div v-else-if="!isEnrolled" class="panel p-5">
        <div class="flex items-center gap-2 text-violet-300">
          <Copy class="h-4 w-4" />
          <h3 class="font-display text-sm font-semibold">Not enrolled?</h3>
        </div>
        <p class="mt-2 text-[0.72rem] leading-relaxed text-fog-400">
          Join with code <span class="font-mono text-violet-200">{{ course.room_code }}</span> from the classrooms page.
        </p>
      </div>

      <p class="px-1 text-[0.68rem] text-fog-600">
        Signed in as {{ auth.user?.name }} · {{ auth.user?.role }}
      </p>
    </aside>

    <ConfirmDialog
      :open="confirmLeave"
      title="Leave this classroom?"
      description="You will lose access to coursework and the stream until you rejoin with the room code."
      confirm-label="Leave classroom"
      tone="danger"
      @confirm="leave"
      @close="confirmLeave = false"
    />

    <ConfirmDialog
      :open="confirmArchive"
      :title="course.status === 'archived' ? 'Restore this classroom?' : 'Archive this classroom?'"
      :description="
        course.status === 'archived'
          ? 'Learners will be able to enrol and post again.'
          : 'The classroom becomes read-only for learners and stops accepting new ones. You can restore it at any time.'
      "
      :confirm-label="course.status === 'archived' ? 'Restore' : 'Archive'"
      @confirm="toggleArchive"
      @close="confirmArchive = false"
    />

    <ConfirmDialog
      :open="confirmDelete"
      title="Delete this classroom?"
      description="This permanently removes the classroom, its coursework, submissions, grades, and materials. This cannot be undone."
      confirm-label="Delete permanently"
      tone="danger"
      @confirm="remove"
      @close="confirmDelete = false"
    />
  </div>
</template>
