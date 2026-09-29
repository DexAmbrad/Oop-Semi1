<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowRight,
  CheckCircle2,
  Clock,
  ClipboardList,
  Filter,
  Plus,
  Sparkles,
  Trash2,
} from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { accentOf } from '@/lib/accents'
import { formatDue, fromNow, relativeDue } from '@/lib/format'
import type { Assignment, AssignmentType, Course } from '@/types'

const props = defineProps<{ course: Course; canTeach: boolean }>()
const emit = defineEmits<{ changed: [] }>()

const router = useRouter()
const toast = useToastStore()

const items = ref<Assignment[]>([])
const loading = ref(true)
const typeFilter = ref<'all' | AssignmentType>('all')
const sort = ref<'due' | 'created' | 'points'>('due')
const composerOpen = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})

const typeTone: Record<string, 'violet' | 'sky' | 'emerald' | 'amber'> = {
  assignment: 'violet',
  quiz: 'sky',
  project: 'emerald',
  reading: 'amber',
}

const typeFilters: { key: 'all' | AssignmentType; label: string }[] = [
  { key: 'all', label: 'Everything' },
  { key: 'assignment', label: 'Assignments' },
  { key: 'quiz', label: 'Quizzes' },
  { key: 'project', label: 'Projects' },
  { key: 'reading', label: 'Readings' },
]

const form = reactive({
  title: '',
  summary: '',
  instructions: '',
  type: 'assignment' as AssignmentType,
  max_points: 100,
  due_at: '',
  allow_late: true,
  status: 'published' as 'published' | 'draft',
})

const filtered = computed(() => {
  const list = items.value.filter((item) => props.canTeach || item.status === 'published')
  return typeFilter.value === 'all' ? list : list.filter((item) => item.type === typeFilter.value)
})

const stats = computed(() => ({
  total: items.value.length,
  dueSoon: items.value.filter((item) => item.is_due_soon).length,
  submitted: items.value.filter((item) => item.my_submission?.submitted_at).length,
  toGrade: items.value.reduce((sum, item) => sum + (item.pending_count ?? 0), 0),
}))

async function load() {
  loading.value = true
  try {
    const payload = await request<{ data: Assignment[] }>({
      url: `/courses/${props.course.id}/assignments`,
      params: { sort: sort.value },
    })
    items.value = payload.data
  } catch (error) {
    toast.error('Could not load coursework', errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function create() {
  busy.value = true
  errors.value = {}

  try {
    const payload = await request<{ message: string; assignment: Assignment }>({
      url: `/courses/${props.course.id}/assignments`,
      method: 'POST',
      data: {
        ...form,
        due_at: form.due_at ? new Date(form.due_at).toISOString() : null,
      },
    })

    items.value.unshift(payload.assignment)
    toast.success(payload.message)
    composerOpen.value = false
    Object.assign(form, {
      title: '',
      summary: '',
      instructions: '',
      type: 'assignment',
      max_points: 100,
      due_at: '',
      allow_late: true,
      status: 'published',
    })
    emit('changed')
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not publish', errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function remove(assignment: Assignment) {
  try {
    await request({ url: `/courses/${props.course.id}/assignments/${assignment.id}`, method: 'DELETE' })
    items.value = items.value.filter((item) => item.id !== assignment.id)
    toast.success('Assignment removed')
    emit('changed')
  } catch (error) {
    toast.error('Could not remove', errorMessage(error))
  }
}

function open(assignment: Assignment) {
  router.push({ name: 'work', params: { courseId: props.course.id, workId: assignment.id } })
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-4">
      <div class="panel p-3.5">
        <p class="text-[0.65rem] tracking-wide text-fog-500 uppercase">Total tasks</p>
        <p class="mt-1 font-display text-xl font-semibold text-white">{{ stats.total }}</p>
      </div>
      <div class="panel p-3.5">
        <p class="text-[0.65rem] tracking-wide text-fog-500 uppercase">Due within 72h</p>
        <p class="mt-1 font-display text-xl font-semibold text-amber-300">{{ stats.dueSoon }}</p>
      </div>
      <div class="panel p-3.5">
        <p class="text-[0.65rem] tracking-wide text-fog-500 uppercase">{{ canTeach ? 'Submissions' : 'You submitted' }}</p>
        <p class="mt-1 font-display text-xl font-semibold text-emerald-300">{{ stats.submitted }}</p>
      </div>
      <div class="panel p-3.5">
        <p class="text-[0.65rem] tracking-wide text-fog-500 uppercase">Awaiting grade</p>
        <p class="mt-1 font-display text-xl font-semibold" :class="stats.toGrade ? 'text-rose-300' : 'text-fog-300'">
          {{ stats.toGrade }}
        </p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center gap-1 rounded-full border border-white/8 bg-white/3 p-1">
        <button
          v-for="option in typeFilters"
          :key="option.key"
          class="rounded-full px-3 py-1.5 text-xs font-medium capitalize transition"
          :class="typeFilter === option.key ? 'bg-white/12 text-white' : 'text-fog-400 hover:text-white'"
          @click="typeFilter = option.key"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <Filter class="h-3.5 w-3.5 text-fog-500" />
        <select v-model="sort" class="field h-9 w-auto py-0 text-xs" @change="load">
          <option value="due">Sort by due date</option>
          <option value="created">Newest first</option>
          <option value="points">Highest points</option>
        </select>
        <BaseButton v-if="canTeach" size="sm" @click="composerOpen = true">
          <template #icon><Plus class="h-3.5 w-3.5" /></template>
          Create
        </BaseButton>
      </div>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="n in 4" :key="n" class="panel p-5"><SkeletonBlock :rows="2" /></div>
    </div>

    <EmptyState
      v-else-if="!filtered.length"
      :icon="ClipboardList"
      title="No coursework here yet"
      :description="canTeach ? 'Publish your first task. Students see it the moment it goes live.' : 'Your teacher has not published anything for this filter.'"
    >
      <template v-if="canTeach" #action>
        <BaseButton @click="composerOpen = true">
          <template #icon><Plus class="h-4 w-4" /></template>
          Create a task
        </BaseButton>
      </template>
    </EmptyState>

    <template v-else>
      <article
        v-for="item in filtered"
        :key="item.id"
        class="panel group anim-rise cursor-pointer p-5 transition hover:border-white/18"
        @click="open(item)"
      >
        <div class="flex items-start gap-4">
          <span
            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl2"
            :style="{ background: accentOf(typeTone[item.type]).soft, color: accentOf(typeTone[item.type]).text }"
          >
            <ClipboardList class="h-5 w-5" />
          </span>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-display text-base font-semibold text-white">{{ item.title }}</h3>
              <BaseBadge :tone="typeTone[item.type]" size="xs">{{ item.type }}</BaseBadge>
              <BaseBadge v-if="item.status === 'draft'" tone="amber" size="xs">Draft</BaseBadge>
              <BaseBadge v-if="item.my_submission?.is_late" tone="rose" size="xs">Late</BaseBadge>
              <BaseBadge v-else-if="item.my_submission?.submitted_at" tone="emerald" size="xs">
                <template #icon><CheckCircle2 class="h-2.5 w-2.5" /></template>Submitted
              </BaseBadge>
            </div>

            <p v-if="item.summary" class="mt-1.5 line-clamp-1 text-xs text-fog-400">{{ item.summary }}</p>

            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[0.72rem]">
              <span class="inline-flex items-center gap-1.5" :class="item.is_overdue ? 'text-rose-300' : 'text-fog-400'">
                <Clock class="h-3.5 w-3.5" />
                {{ formatDue(item.due_at) }}
                <span v-if="item.due_at && !item.is_overdue" class="text-fog-500">· {{ relativeDue(item.due_at) }}</span>
              </span>
              <span class="text-fog-400"><Sparkles class="mr-1 inline h-3.5 w-3.5" />{{ item.max_points }} pts</span>

              <span v-if="canTeach" class="inline-flex items-center gap-3 text-fog-400">
                <span>{{ item.submissions_count }} submitted</span>
                <span v-if="item.pending_count" class="font-semibold text-amber-300">{{ item.pending_count }} to grade</span>
              </span>
            </div>

            <div v-if="item.my_submission?.score !== null && item.my_submission?.score !== undefined" class="mt-3 inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/10 px-3 py-1">
              <span class="text-[0.7rem] font-semibold text-emerald-300">
                {{ item.my_submission.score }} / {{ item.max_points }}
              </span>
              <span class="text-[0.65rem] text-emerald-300/70">graded {{ fromNow(item.my_submission.graded_at) }}</span>
            </div>
          </div>

          <div class="flex shrink-0 flex-col items-end gap-2">
            <ArrowRight class="h-4 w-4 text-fog-500 transition group-hover:translate-x-0.5 group-hover:text-violet-300" />
            <button
              v-if="canTeach"
              class="grid h-8 w-8 place-items-center rounded-full text-fog-500 transition hover:bg-rose-400/12 hover:text-rose-300"
              title="Delete assignment"
              @click.stop="remove(item)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </article>
    </template>

    <BaseModal :open="composerOpen" title="Create coursework" size="lg" @close="composerOpen = false">
      <form class="space-y-4" @submit.prevent="create">
        <BaseInput v-model="form.title" label="Title" placeholder="Problem Set 5" :error="errors.title" />

        <BaseInput
          v-model="form.summary"
          label="One-line summary"
          placeholder="Short description shown in the list"
          :error="errors.summary"
        />

        <BaseTextarea
          v-model="form.instructions"
          label="Instructions"
          :rows="6"
          placeholder="Deliverables, constraints, where to submit…"
          :error="errors.instructions"
        />

        <div class="grid gap-4 sm:grid-cols-3">
          <BaseSelect
            v-model="form.type"
            label="Type"
            :options="[
              { value: 'assignment', label: 'Assignment' },
              { value: 'quiz', label: 'Quiz' },
              { value: 'project', label: 'Project' },
              { value: 'reading', label: 'Reading' },
            ]"
          />
          <BaseInput v-model.number="form.max_points" label="Points" type="number" />
          <BaseSelect
            v-model="form.status"
            label="Visibility"
            :options="[
              { value: 'published', label: 'Publish now' },
              { value: 'draft', label: 'Save as draft' },
            ]"
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.due_at" label="Due date & time" type="datetime-local" />
          <label class="flex items-end gap-2 pb-2.5 text-sm text-fog-300">
            <input v-model="form.allow_late" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-white/5" />
            Accept late submissions
          </label>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="ghost" @click="composerOpen = false">Cancel</BaseButton>
        <BaseButton :loading="busy" :disabled="!form.title" @click="create">
          <template #icon><Plus class="h-4 w-4" /></template>
          Publish task
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
