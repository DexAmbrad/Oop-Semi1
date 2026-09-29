<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Archive, BookOpen, FolderPlus, LayoutGrid, Plus, Search, Sparkles, Ticket } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import CourseCard from '@/components/course/CourseCard.vue'
import { useAuthStore } from '@/stores/auth'
import { useCourseStore } from '@/stores/courses'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { ACCENT_KEYS, COURSE_ICON_KEYS, accentOf, courseIcon } from '@/lib/accents'
import type { Accent, Course, CourseIcon, User } from '@/types'

type CourseFilter = 'all' | 'active' | 'archived'

const auth = useAuthStore()
const courses = useCourseStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()

const { items, loading } = storeToRefs(courses)

const search = ref('')
const filter = ref<CourseFilter>('all')

const filters: { key: CourseFilter; label: string; icon: typeof LayoutGrid }[] = [
  { key: 'all', label: 'All', icon: LayoutGrid },
  { key: 'active', label: 'Active', icon: BookOpen },
  { key: 'archived', label: 'Archived', icon: Archive },
]

const joinOpen = ref(false)
const createOpen = ref(false)
const joinCode = ref('')
const joining = ref(false)
const joinError = ref('')

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

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()

  return items.value.filter((course) => {
    const matchesStatus =
      filter.value === 'all' ||
      (filter.value === 'active' && course.status === 'active') ||
      (filter.value === 'archived' && course.status === 'archived')

    const matchesTerm =
      !term ||
      course.title.toLowerCase().includes(term) ||
      course.code.toLowerCase().includes(term) ||
      (course.subject ?? '').toLowerCase().includes(term)

    return matchesStatus && matchesTerm
  })
})

const counts = computed(() => ({
  all: items.value.length,
  active: items.value.filter((course) => course.status === 'active').length,
  archived: items.value.filter((course) => course.status === 'archived').length,
}))

async function load() {
  await courses.fetchAll({}, true)
}

async function join() {
  joining.value = true
  joinError.value = ''

  try {
    const payload = await request<{ message: string; course: Course }>({
      url: '/courses/join',
      method: 'POST',
      data: { room_code: joinCode.value.trim().toUpperCase() },
    })

    courses.upsert(payload.course)
    toast.success(payload.message)
    joinOpen.value = false
    joinCode.value = ''
    router.push({ name: 'course', params: { id: payload.course.id } })
  } catch (error) {
    joinError.value = fieldErrors(error).room_code ?? errorMessage(error)
  } finally {
    joining.value = false
  }
}

async function create() {
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
    toast.success(payload.message)
    createOpen.value = false
    router.push({ name: 'course', params: { id: payload.course.id } })
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not create classroom', errorMessage(error))
  } finally {
    busy.value = false
  }
}

function resetForm() {
  Object.assign(form, {
    title: '',
    code: '',
    subject: '',
    description: '',
    accent: 'violet',
    icon: 'book',
    term: 'Fall 2026',
    capacity: 40,
    teacher_id: '' as number | '',
  })
  errors.value = {}
}

watch(createOpen, (open) => {
  if (open) {
    resetForm()
    loadTeachers()
  }
})

onMounted(() => {
  load()
  if (route.query.join === '1') {
    joinOpen.value = true
    router.replace({ name: 'courses' })
  }
})
</script>

<template>
  <div>
    <PageHeader
      eyebrow="Classrooms"
      title="Where the work happens"
      description="Every classroom you teach or attend, sorted by what needs attention first."
    >
      <template #actions>
        <BaseButton variant="outline" size="sm" @click="joinOpen = true">
          <template #icon><Ticket class="h-3.5 w-3.5" /></template>
          Join with code
        </BaseButton>
        <BaseButton v-if="canCreate" size="sm" @click="createOpen = true">
          <template #icon><Plus class="h-3.5 w-3.5" /></template>
          New classroom
        </BaseButton>
      </template>
    </PageHeader>

    <div class="mb-5 flex flex-wrap items-center gap-3">
      <div class="relative min-w-[14rem] flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-fog-500" />
        <input v-model="search" type="search" placeholder="Search by name, code or subject" class="field h-10 pl-9" />
      </div>

      <div class="flex items-center gap-1 rounded-full border border-white/8 bg-white/3 p-1">
        <button
          v-for="option in filters"
          :key="option.key"
          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition"
          :class="filter === option.key ? 'bg-white/12 text-white' : 'text-fog-400 hover:text-white'"
          @click="filter = option.key"
        >
          <component :is="option.icon" class="h-3.5 w-3.5" />
          {{ option.label }}
          <span class="text-[0.65rem] opacity-60">{{ counts[option.key] }}</span>
        </button>
      </div>
    </div>

    <div v-if="loading && !items.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="n in 6" :key="n" class="panel overflow-hidden">
        <div class="h-28 anim-shimmer bg-white/5" />
        <div class="space-y-3 p-4"><SkeletonBlock :rows="3" /></div>
      </div>
    </div>

    <div v-else-if="!filtered.length">
      <EmptyState
        :icon="Sparkles"
        title="No classrooms match"
        description="Try a different filter, or join a classroom with the room code your teacher shared."
      >
        <template #action>
          <BaseButton @click="joinOpen = true">
            <template #icon><Ticket class="h-4 w-4" /></template>
            Join a classroom
          </BaseButton>
        </template>
      </EmptyState>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <CourseCard
        v-for="(course, index) in filtered"
        :key="course.id"
        :course="course"
        class="anim-rise"
        :style="{ animationDelay: `${Math.min(index, 8) * 40}ms` }"
      />
    </div>

    <!-- Join modal -->
    <BaseModal
      :open="joinOpen"
      title="Join a classroom"
      description="Ask your teacher for the six-character room code."
      size="sm"
      @close="joinOpen = false"
    >
      <form class="space-y-4" @submit.prevent="join">
        <BaseInput
          v-model="joinCode"
          label="Room code"
          placeholder="K7QT2M"
          :error="joinError"
          hint="Codes are not case-sensitive."
        />
        <p class="text-xs leading-relaxed text-fog-500">
          Teachers joining with a code are added as an assistant teacher rather than a student.
        </p>
        <BaseButton type="submit" block :loading="joining" :disabled="joinCode.trim().length < 4">
          Join classroom
        </BaseButton>
      </form>
    </BaseModal>

    <!-- Create modal -->
    <BaseModal :open="createOpen" title="Create a classroom" size="lg" @close="createOpen = false">
      <form class="space-y-4" @submit.prevent="create">
        <BaseInput v-model="form.title" label="Classroom name" placeholder="Algorithm Design Studio" :error="errors.title" required />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.code" label="Course code" placeholder="CS 210" :error="errors.code" hint="Blank generates one." />
          <BaseInput v-model="form.subject" label="Subject" placeholder="Computer Science" :error="errors.subject" />
        </div>

        <BaseTextarea v-model="form.description" label="Description" :rows="3" placeholder="What will students walk away knowing?" />

        <div class="grid gap-4 sm:grid-cols-3">
          <div class="sm:col-span-2">
            <p class="field-label">Accent</p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="accent in ACCENT_KEYS"
                :key="accent"
                type="button"
                class="h-9 w-9 rounded-xl border transition"
                :style="{
                  background: accentOf(accent).gradient,
                  borderColor: form.accent === accent ? '#fff' : 'transparent',
                  boxShadow: form.accent === accent ? `0 0 0 3px ${accentOf(accent).glow}` : 'none',
                }"
                @click="form.accent = accent"
              />
            </div>
          </div>

          <BaseSelect
            v-model="form.capacity"
            label="Capacity"
            :options="[
              { value: 20, label: '20' },
              { value: 30, label: '30' },
              { value: 40, label: '40' },
              { value: 60, label: '60' },
            ]"
          />
        </div>

        <div>
          <p class="field-label">Icon</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="icon in COURSE_ICON_KEYS"
              :key="icon"
              type="button"
              class="grid h-10 w-10 place-items-center rounded-xl border transition"
              :class="form.icon === icon ? 'border-violet-300/70 bg-violet-500/15 text-violet-200' : 'border-white/8 text-fog-400 hover:border-white/20 hover:text-white'"
              @click="form.icon = icon"
            >
              <component :is="courseIcon(icon)" class="h-4 w-4" />
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
        </div>
      </form>

      <template #footer>
        <BaseButton variant="ghost" @click="createOpen = false">Cancel</BaseButton>
        <BaseButton :loading="busy" :disabled="!form.title" @click="create">
          <template #icon><FolderPlus class="h-4 w-4" /></template>
          Create classroom
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
