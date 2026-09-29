<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { BookOpen, Megaphone, Plus, ClipboardList, FolderPlus } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/courses'
import { useToastStore } from '@/stores/toast'
import { request, errorMessage, fieldErrors } from '@/lib/api'
import { ACCENT_KEYS, COURSE_ICON_KEYS, accentOf, courseIcon } from '@/lib/accents'
import type { Accent, Course, CourseIcon, User } from '@/types'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()

const auth = useAuthStore()
const courses = useCourseStore()
const toast = useToastStore()
const router = useRouter()

const { user } = storeToRefs(auth)

const mode = ref<'menu' | 'create'>('menu')
const busy = ref(false)
const errors = ref<Record<string, string>>({})

const form = reactive({
  title: '',
  code: '',
  subject: '',
  description: '',
  accent: 'violet' as Accent,
  icon: 'book' as CourseIcon,
  term: 'Fall 2026',
  capacity: 40,
  teacher_id: '' as number | '',
})

const canCreate = computed(() => auth.isTeacher || auth.isAdmin)

const teachers = ref<User[]>([])

const teacherOptions = computed(() => [
  { value: '' as number | '', label: 'Choose a teacher' },
  ...teachers.value.map((teacher) => ({ value: teacher.id, label: `${teacher.name} · ${teacher.email}` })),
])

async function loadTeachers() {
  if (!auth.isAdmin || teachers.value.length) return

  try {
    const payload = await request<{ data: User[] }>({ url: '/admin/users', params: { role: 'teacher', per_page: 50 } })
    teachers.value = payload.data
    form.teacher_id = teachers.value[0]?.id ?? ''
  } catch {
    teachers.value = []
  }
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      mode.value = 'menu'
      errors.value = {}
      Object.assign(form, {
        title: '',
        code: '',
        subject: '',
        description: '',
        accent: 'violet',
        icon: 'book',
        term: 'Fall 2026',
        capacity: 40,
        teacher_id: form.teacher_id,
      })
    }
  },
)

const accentOptions = ACCENT_KEYS.map((key) => ({ value: key, label: key.charAt(0).toUpperCase() + key.slice(1) }))
const iconOptions = COURSE_ICON_KEYS.map((key) => ({ value: key, label: key.charAt(0).toUpperCase() + key.slice(1) }))

async function createCourse() {
  busy.value = true
  errors.value = {}

  try {
    const payload = await request<{ message: string; course: Course }>({
      url: '/courses',
      method: 'POST',
      data: {
        ...form,
        code: form.code || null,
        teacher_id: auth.isAdmin ? form.teacher_id || null : null,
      },
    })

    courses.upsert(payload.course)
    toast.success(payload.message, `${payload.course.code} is live.`)
    emit('close')
    router.push({ name: 'course', params: { id: payload.course.id } })
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not create classroom', errorMessage(error))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <BaseModal
    :open="open"
    :title="mode === 'menu' ? 'Start something' : 'Create a classroom'"
    :description="
      mode === 'menu'
        ? `What would you like to set up, ${user?.name?.split(' ')[0] ?? 'there'}?`
        : 'Give it a name and a look — you can refine everything later.'
    "
    @close="emit('close')"
  >
    <div v-if="mode === 'menu'" class="grid gap-3 sm:grid-cols-2">
      <button
        v-if="canCreate"
        class="panel-quiet group flex flex-col items-start gap-3 p-5 text-left transition hover:border-violet-400/40 hover:bg-white/6"
        @click="mode = 'create'; loadTeachers()"
      >
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-500/15 text-violet-300">
          <Plus class="h-5 w-5" />
        </span>
        <span>
          <span class="block font-display text-sm font-semibold text-white">Create classroom</span>
          <span class="mt-1 block text-xs leading-relaxed text-fog-500">
            Set up a new space with a join code, roster and stream.
          </span>
        </span>
      </button>

      <button
        class="panel-quiet group flex flex-col items-start gap-3 p-5 text-left transition hover:border-sky-400/40 hover:bg-white/6"
        @click="emit('close'); router.push({ name: 'courses', query: { join: '1' } })"
      >
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-500/15 text-sky-300">
          <BookOpen class="h-5 w-5" />
        </span>
        <span>
          <span class="block font-display text-sm font-semibold text-white">Join with a code</span>
          <span class="mt-1 block text-xs leading-relaxed text-fog-500">
            Enter the six-character room code your teacher shared.
          </span>
        </span>
      </button>

      <button
        class="panel-quiet group flex flex-col items-start gap-3 p-5 text-left transition hover:border-amber-400/40 hover:bg-white/6"
        @click="emit('close'); router.push({ name: 'calendar' })"
      >
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-500/15 text-amber-300">
          <ClipboardList class="h-5 w-5" />
        </span>
        <span>
          <span class="block font-display text-sm font-semibold text-white">Review the timeline</span>
          <span class="mt-1 block text-xs leading-relaxed text-fog-500">
            Everything due across your classrooms in one place.
          </span>
        </span>
      </button>

      <button
        class="panel-quiet group flex flex-col items-start gap-3 p-5 text-left transition hover:border-emerald-400/40 hover:bg-white/6"
        @click="emit('close'); router.push({ name: 'messages' })"
      >
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-500/15 text-emerald-300">
          <Megaphone class="h-5 w-5" />
        </span>
        <span>
          <span class="block font-display text-sm font-semibold text-white">Message someone</span>
          <span class="mt-1 block text-xs leading-relaxed text-fog-500">
            Reach a teacher or classmate directly, with context.
          </span>
        </span>
      </button>
    </div>

    <form v-else class="space-y-4" @submit.prevent="createCourse">
      <BaseInput v-model="form.title" label="Classroom name" placeholder="e.g. Algorithms & Data Structures" :error="errors.title" required />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput v-model="form.code" label="Course code" placeholder="CS 210" :error="errors.code" hint="Leave blank to auto-generate." />
        <BaseInput v-model="form.subject" label="Subject" placeholder="Computer Science" :error="errors.subject" />
      </div>

      <BaseTextarea v-model="form.description" label="Description" :rows="3" placeholder="What will students walk away knowing?" />

      <div>
        <p class="field-label">Accent</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="accent in accentOptions"
            :key="accent.value"
            type="button"
            class="h-9 w-9 rounded-xl border transition"
            :style="{
              background: accentOf(accent.value).gradient,
              borderColor: form.accent === accent.value ? '#fff' : 'transparent',
              boxShadow: form.accent === accent.value ? `0 0 0 3px ${accentOf(accent.value).glow}` : 'none',
            }"
            :title="accent.label"
            @click="form.accent = accent.value as Accent"
          />
        </div>
      </div>

      <div>
        <p class="field-label">Icon</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="icon in iconOptions"
            :key="icon.value"
            type="button"
            class="grid h-10 w-10 place-items-center rounded-xl border transition"
            :class="form.icon === icon.value ? 'border-violet-300/70 bg-violet-500/15 text-violet-200' : 'border-white/8 text-fog-400 hover:border-white/20 hover:text-white'"
            :title="icon.label"
            @click="form.icon = icon.value as CourseIcon"
          >
            <component :is="courseIcon(icon.value)" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput v-model="form.term" label="Term" placeholder="Fall 2026" />
        <BaseSelect
          v-if="auth.isAdmin"
          v-model="form.teacher_id"
          label="Instructor"
          :options="teacherOptions"
          :error="errors.teacher_id"
        />
        <BaseSelect
          v-else
          v-model="form.capacity"
          label="Capacity"
          :options="[
            { value: 20, label: '20 students' },
            { value: 30, label: '30 students' },
            { value: 40, label: '40 students' },
            { value: 50, label: '50 students' },
            { value: 80, label: '80 students' },
          ]"
        />
      </div>
      <BaseSelect
        v-if="auth.isAdmin"
        v-model="form.capacity"
        label="Capacity"
        :options="[
          { value: 20, label: '20 students' },
          { value: 30, label: '30 students' },
          { value: 40, label: '40 students' },
          { value: 50, label: '50 students' },
          { value: 80, label: '80 students' },
        ]"
      />
    </form>

    <template v-if="mode === 'create'" #footer>
      <BaseButton variant="ghost" type="button" @click="mode = 'menu'">Back</BaseButton>
      <BaseButton :loading="busy" @click="createCourse">
        <template #icon><FolderPlus class="h-4 w-4" /></template>
        Create classroom
      </BaseButton>
    </template>
  </BaseModal>
</template>
