<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  CheckCircle2,
  Clock,
  CloudUpload,
  FileText,
  Inbox,
  Link2,
  MessageSquareQuote,
  Save,
  Send,
  Sparkles,
  Trash2,
  Users,
  Wand2,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { accentOf, courseIcon } from '@/lib/accents'
import { formatDue, fromNow, percentageTone } from '@/lib/format'
import type { Assignment, AssignmentType, Course, Submission } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const course = ref<Course | null>(null)
const assignment = ref<Assignment | null>(null)
const submissions = ref<Submission[]>([])
const mine = ref<Submission | null>(null)
const loading = ref(true)
const submitting = ref(false)
const editing = ref(false)
const errors = ref<Record<string, string>>({})
const deleting = ref(false)
const confirmDelete = ref(false)

const draft = reactive({ body: '', link_url: '' })
const file = ref<File | null>(null)
const bulk = reactive({ feedback: '' })
const confirmBulk = ref(false)
const grading = reactive<Record<number, { score: number | null; feedback: string; busy: boolean }>>({})

const courseId = computed(() => Number(route.params.courseId))
const workId = computed(() => Number(route.params.workId))

const canTeach = computed(() => !!course.value && (course.value.is_teacher || auth.isAdmin))

const typeTone: Record<AssignmentType, 'violet' | 'sky' | 'emerald' | 'amber'> = {
  assignment: 'violet',
  quiz: 'sky',
  project: 'emerald',
  reading: 'amber',
}

const graded = computed(() => submissions.value.filter((item) => item.is_graded))
const awaiting = computed(() => submissions.value.filter((item) => !item.is_graded))
const classAverage = computed(() => {
  const scored = submissions.value.filter((item) => item.score !== null)
  if (!scored.length) return null
  return Math.round((scored.reduce((sum, item) => sum + (item.score ?? 0), 0) / scored.length) * 10) / 10
})

const locked = computed(() => !!assignment.value?.is_overdue && !assignment.value.allow_late)

const canSubmit = computed(
  () => !locked.value && (draft.body.trim() || draft.link_url.trim() || file.value !== null),
)

async function load() {
  loading.value = true

  try {
    const [coursePayload, assignmentPayload] = await Promise.all([
      request<{ data: Course }>({ url: `/courses/${courseId.value}` }),
      request<{ data: Assignment }>({ url: `/courses/${courseId.value}/assignments/${workId.value}` }),
    ])

    course.value = coursePayload.data
    assignment.value = assignmentPayload.data

    if (canTeach.value) {
      const payload = await request<{ data: Submission[] }>({
        url: `/courses/${courseId.value}/assignments/${workId.value}/submissions`,
      })
      submissions.value = payload.data
      syncGradingDrafts()
    } else {
      const payload = await request<{ submission: Submission | null }>({
        url: `/courses/${courseId.value}/assignments/${workId.value}/my-submission`,
      })
      mine.value = payload.submission
      hydrateDraft()
    }
  } catch (error) {
    toast.error('Could not open this task', errorMessage(error))
    router.replace({ name: 'courses' })
  } finally {
    loading.value = false
  }
}

function syncGradingDrafts() {
  for (const submission of submissions.value) {
    grading[submission.id] = {
      score: submission.score,
      feedback: submission.feedback ?? '',
      busy: false,
    }
  }
}

function hydrateDraft() {
  if (!mine.value) return
  draft.body = mine.value.body ?? ''
  draft.link_url = mine.value.link_url ?? ''
  editing.value = false
}

function pickFile(event: Event) {
  const input = event.target as HTMLInputElement
  file.value = input.files?.[0] ?? null
}

async function submit() {
  submitting.value = true
  errors.value = {}

  try {
    const body = new FormData()
    if (draft.body.trim()) body.append('body', draft.body.trim())
    if (draft.link_url.trim()) body.append('link_url', draft.link_url.trim())
    if (file.value) body.append('file', file.value)

    const payload = await request<{ message: string; submission: Submission }>({
      url: `/courses/${courseId.value}/assignments/${workId.value}/submissions`,
      method: 'POST',
      data: body,
    })

    mine.value = payload.submission
    editing.value = false
    file.value = null
    toast.success(payload.message)
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not submit your work', errorMessage(error))
  } finally {
    submitting.value = false
  }
}

async function saveGrade(submission: Submission) {
  const row = grading[submission.id]
  if (!row || row.score === null) return

  row.busy = true

  try {
    const payload = await request<{ message: string; submission: Submission }>({
      url: `/courses/${courseId.value}/assignments/${workId.value}/submissions/${submission.id}/grade`,
      method: 'PATCH',
      data: { score: Number(row.score), feedback: row.feedback || null },
    })

    Object.assign(submission, payload.submission)
    toast.success(`${submission.user?.name ?? 'Learner'} graded`)
  } catch (error) {
    toast.error('Could not save that grade', errorMessage(error))
  } finally {
    row.busy = false
  }
}

async function runBulk() {
  confirmBulk.value = false

  const targets = submissions.value.filter((item) => grading[item.id]?.score !== null && grading[item.id]?.score !== undefined)

  if (!targets.length) {
    toast.info('Nothing to grade yet', 'Enter a score for at least one learner first.')
    return
  }

  try {
    const payload = await request<{ message: string }>({
      url: `/courses/${courseId.value}/assignments/${workId.value}/submissions/bulk-grade`,
      method: 'POST',
      data: {
        scores: targets.map((item) => ({ submission_id: item.id, score: Number(grading[item.id].score) })),
        feedback: bulk.feedback || null,
      },
    })

    toast.success(payload.message)
    await load()
  } catch (error) {
    toast.error('Bulk grading failed', errorMessage(error))
  }
}

async function removeAssignment() {
  confirmDelete.value = false
  deleting.value = true

  try {
    await request({ url: `/courses/${courseId.value}/assignments/${workId.value}`, method: 'DELETE' })
    toast.success('Task deleted')
    router.push({ name: 'course', params: { id: courseId.value }, query: { tab: 'work' } })
  } catch (error) {
    toast.error('Could not delete this task', errorMessage(error))
  } finally {
    deleting.value = false
  }
}

onMounted(load)
watch([courseId, workId], load)
</script>

<template>
  <div v-if="loading && !assignment" class="space-y-5">
    <div class="panel p-6"><SkeletonBlock :rows="3" /></div>
    <div class="panel p-6"><SkeletonBlock :rows="6" /></div>
  </div>

  <div v-else-if="assignment && course">
    <RouterLink
      :to="{ name: 'course', params: { id: course.id }, query: { tab: 'work' } }"
      class="mb-4 inline-flex items-center gap-1.5 text-xs font-medium text-fog-400 transition hover:text-white"
    >
      <ArrowLeft class="h-3.5 w-3.5" />
      {{ course.code }} · back to coursework
    </RouterLink>

    <PageHeader
      :eyebrow="course.title"
      :title="assignment.title"
      :description="assignment.summary ?? undefined"
      compact
    >
      <template #actions>
        <BaseBadge :tone="typeTone[assignment.type]" size="sm">{{ assignment.type }}</BaseBadge>
        <BaseBadge tone="amber" size="sm">{{ assignment.max_points }} pts</BaseBadge>
        <BaseBadge v-if="assignment.status === 'draft'" tone="rose" size="sm">Draft</BaseBadge>
        <BaseButton v-if="canTeach" size="sm" variant="danger" :loading="deleting" @click="confirmDelete = true">
          <template #icon><Trash2 class="h-3.5 w-3.5" /></template>
          Delete
        </BaseButton>
      </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
      <!-- Left column -->
      <div class="space-y-5">
        <section class="panel p-6">
          <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-fog-400">
            <span class="inline-flex items-center gap-1.5" :class="assignment.is_overdue ? 'text-rose-300' : ''">
              <Clock class="h-3.5 w-3.5" />
              {{ formatDue(assignment.due_at) }}
            </span>
            <span class="inline-flex items-center gap-1.5">
              <Users class="h-3.5 w-3.5" />
              {{ assignment.submissions_count }} submitted
            </span>
            <span v-if="assignment.author" class="inline-flex items-center gap-1.5">
              <Sparkles class="h-3.5 w-3.5" />
              by {{ assignment.author.name }}
            </span>
            <BaseBadge v-if="!assignment.allow_late" tone="rose" size="xs">No late work</BaseBadge>
          </div>

          <h2 class="mt-5 font-display text-sm font-semibold tracking-wide text-fog-100 uppercase">Instructions</h2>
          <p v-if="assignment.instructions" class="mt-2 text-sm leading-relaxed whitespace-pre-line text-fog-300">
            {{ assignment.instructions }}
          </p>
          <p v-else class="mt-2 text-sm text-fog-500">Your teacher did not add instructions for this task.</p>
        </section>

        <!-- Teacher: grading queue -->
        <section v-if="canTeach" class="panel overflow-hidden">
          <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/7 px-5 py-4">
            <div class="flex items-center gap-2">
              <Inbox class="h-4 w-4 text-violet-300" />
              <h2 class="font-display text-sm font-semibold text-white">Submissions</h2>
              <span v-if="awaiting.length" class="chip border-amber-400/25 text-amber-300">{{ awaiting.length }} to grade</span>
            </div>

            <div class="flex items-center gap-2">
              <span v-if="classAverage !== null" class="text-xs text-fog-400">
                Class average
                <strong class="font-display text-sm" :style="{ color: percentageTone(classAverage) }">{{ classAverage }}</strong>
              </span>
            </div>
          </header>

          <div v-if="!submissions.length" class="px-5 py-10 text-center">
            <p class="font-display text-sm font-semibold text-fog-100">No submissions yet</p>
            <p class="mt-1.5 text-sm text-fog-500">Learners who turn this in will appear here instantly.</p>
          </div>

          <div v-else class="flex flex-wrap items-end gap-3 border-b border-white/5 bg-white/2 px-5 py-3">
            <div class="min-w-[14rem] flex-1">
              <BaseInput
                v-model="bulk.feedback"
                label="Shared feedback for everyone"
                placeholder="Applied to every graded submission in one pass"
              />
            </div>
            <BaseButton variant="outline" @click="confirmBulk = true">
              <template #icon><Wand2 class="h-3.5 w-3.5" /></template>
              Grade {{ graded.length + awaiting.length }} in bulk
            </BaseButton>
          </div>

          <ul class="divide-y divide-white/5">
            <li v-for="submission in submissions" :key="submission.id" class="px-5 py-4 transition hover:bg-white/2">
              <div class="flex flex-wrap items-start gap-3">
                <BaseAvatar
                  :name="submission.user?.name ?? '?'"
                  :initials="submission.user?.initials ?? '?'"
                  role="student"
                  size="sm"
                />

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium text-white">{{ submission.user?.name }}</p>
                    <BaseBadge v-if="submission.is_late" tone="rose" size="xs">Late</BaseBadge>
                    <BaseBadge v-if="submission.is_graded" tone="emerald" size="xs">Graded</BaseBadge>
                    <span class="text-[0.68rem] text-fog-500">{{ fromNow(submission.submitted_at) }}</span>
                  </div>

                  <p v-if="submission.body" class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-fog-400">
                    {{ submission.body }}
                  </p>

                  <div class="mt-2 flex flex-wrap items-center gap-3 text-[0.7rem]">
                    <a
                      v-if="submission.link_url"
                      :href="submission.link_url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center gap-1 text-sky-300 transition hover:text-sky-200"
                    >
                      <Link2 class="h-3 w-3" />{{ submission.link_url }}
                    </a>
                    <a
                      v-if="submission.file_url"
                      :href="submission.file_url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center gap-1 text-violet-300 transition hover:text-violet-200"
                    >
                      <FileText class="h-3 w-3" />{{ submission.file_name }}
                    </a>
                  </div>
                </div>

                <div class="flex w-full items-center gap-2 sm:w-auto">
                  <input
                    v-model.number="grading[submission.id].score"
                    type="number"
                    min="0"
                    :max="assignment.max_points"
                    placeholder="—"
                    class="w-16 rounded-lg border border-white/8 bg-white/3 px-2 py-1.5 text-center text-xs text-white transition focus:border-violet-400/60 focus:outline-none"
                  />
                  <span class="text-xs text-fog-500">/ {{ assignment.max_points }}</span>
                  <BaseButton
                    size="sm"
                    variant="ghost"
                    :loading="grading[submission.id].busy"
                    :disabled="grading[submission.id].score === null"
                    @click="saveGrade(submission)"
                  >
                    <template #icon><Save class="h-3.5 w-3.5" /></template>
                    Save
                  </BaseButton>
                </div>
              </div>

              <details class="mt-3">
                <summary
                  class="inline-flex cursor-pointer list-none items-center gap-1.5 text-[0.7rem] font-medium text-fog-400 transition hover:text-violet-300"
                >
                  <MessageSquareQuote class="h-3.5 w-3.5" />
                  {{ submission.feedback ? 'Edit feedback' : 'Add feedback' }}
                </summary>
                <textarea
                  v-model="grading[submission.id].feedback"
                  rows="2"
                  class="field mt-2 resize-y text-sm"
                  placeholder="Private feedback for this learner…"
                />
              </details>
            </li>
          </ul>
        </section>

        <!-- Student: submission -->
        <section v-else class="panel p-6">
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-sm font-semibold text-white">Your work</h2>
            <BaseBadge
              v-if="mine"
              :tone="mine.is_graded ? 'emerald' : mine.is_late ? 'rose' : 'sky'"
              size="sm"
            >
              {{ mine.is_graded ? 'Graded' : mine.is_late ? 'Submitted late' : 'Submitted' }}
            </BaseBadge>
          </div>

          <div v-if="locked" class="mt-4 rounded-xl2 border border-rose-400/25 bg-rose-400/8 p-4">
            <p class="text-sm font-medium text-rose-200">The deadline has passed</p>
            <p class="mt-1 text-xs leading-relaxed text-rose-200/70">
              This task closed {{ formatDue(assignment.due_at) }} and does not accept late work. Contact your teacher if you
              need an extension.
            </p>
          </div>

          <template v-else-if="mine && !editing">
            <div class="mt-4 space-y-3">
              <div v-if="mine.body" class="rounded-xl2 border border-white/7 bg-white/2 p-4">
                <p class="text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Response</p>
                <p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-fog-200">{{ mine.body }}</p>
              </div>

              <div class="flex flex-wrap gap-2">
                <a
                  v-if="mine.link_url"
                  :href="mine.link_url"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center gap-1.5 rounded-full border border-sky-400/25 bg-sky-400/10 px-3 py-1.5 text-xs text-sky-200 transition hover:bg-sky-400/16"
                >
                  <Link2 class="h-3.5 w-3.5" />{{ mine.link_url }}
                </a>
                <a
                  v-if="mine.file_url"
                  :href="mine.file_url"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center gap-1.5 rounded-full border border-violet-400/25 bg-violet-400/10 px-3 py-1.5 text-xs text-violet-200 transition hover:bg-violet-400/16"
                >
                  <FileText class="h-3.5 w-3.5" />{{ mine.file_name }}
                </a>
              </div>

              <p class="text-[0.7rem] text-fog-500">Submitted {{ fromNow(mine.submitted_at) }}</p>
            </div>

            <div v-if="mine.is_graded" class="mt-5 rounded-xl2 border border-emerald-400/25 bg-emerald-400/8 p-4">
              <div class="flex flex-wrap items-center gap-3">
                <span class="font-display text-2xl font-semibold text-emerald-300">
                  {{ mine.score }}<span class="text-sm text-emerald-300/60">/{{ assignment.max_points }}</span>
                </span>
                <BaseBadge tone="emerald" size="xs">
                  <template #icon><CheckCircle2 class="h-2.5 w-2.5" /></template>
                  {{ Math.round(((mine.score ?? 0) / assignment.max_points) * 100) }}%
                </BaseBadge>
                <span v-if="mine.grader" class="text-[0.7rem] text-emerald-200/60">by {{ mine.grader.name }}</span>
              </div>
              <p v-if="mine.feedback" class="mt-3 text-sm leading-relaxed text-emerald-100/80">{{ mine.feedback }}</p>
            </div>

            <BaseButton class="mt-5" variant="outline" @click="editing = true">Edit submission</BaseButton>
          </template>

          <form v-else class="mt-4 space-y-4" @submit.prevent="submit">
            <BaseTextarea
              v-model="draft.body"
              label="Written response"
              :rows="7"
              placeholder="Explain your reasoning, paste your solution, or leave a note for your teacher…"
              :error="errors.body"
            />

            <BaseInput v-model="draft.link_url" label="Or share a link" placeholder="https://…" :error="errors.link_url" />

            <label
              class="flex cursor-pointer items-center gap-3 rounded-xl2 border border-dashed border-white/12 bg-white/2 px-4 py-3.5 transition hover:border-violet-400/40 hover:bg-white/4"
            >
              <CloudUpload class="h-4 w-4 shrink-0 text-violet-300" />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-xs font-medium text-white">
                  {{ file ? file.name : 'Attach a file' }}
                </span>
                <span class="block text-[0.68rem] text-fog-500">Optional · up to 10 MB</span>
              </span>
              <input type="file" class="hidden" @change="pickFile" />
            </label>

            <div class="flex flex-wrap justify-end gap-2 border-t border-white/7 pt-4">
              <BaseButton v-if="mine" variant="ghost" @click="editing = false">Cancel</BaseButton>
              <BaseButton type="submit" :loading="submitting" :disabled="!canSubmit">
                <template #icon><Send class="h-4 w-4" /></template>
                {{ mine ? 'Resubmit' : 'Submit work' }}
              </BaseButton>
            </div>
          </form>
        </section>
      </div>

      <!-- Right column -->
      <aside class="space-y-4">
        <div class="panel p-5">
          <div class="flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-xl2" :style="{ background: accentOf(course.accent).soft, color: accentOf(course.accent).text }">
              <component :is="courseIcon(course.icon)" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
              <p class="truncate text-xs font-semibold text-white">{{ course.title }}</p>
              <p class="truncate text-[0.66rem] text-fog-500">{{ course.code }}</p>
            </div>
          </div>
          <p v-if="assignment.course?.description" class="mt-3 line-clamp-3 text-[0.7rem] leading-relaxed text-fog-500">
            {{ assignment.course.description }}
          </p>
        </div>

        <div v-if="canTeach" class="panel p-5">
          <h3 class="font-display text-sm font-semibold text-white">Grading progress</h3>
          <div class="mt-4 space-y-3 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-fog-400">Submitted</span>
              <span class="font-semibold text-white">{{ submissions.length }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-fog-400">Graded</span>
              <span class="font-semibold text-emerald-300">{{ graded.length }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-fog-400">Waiting</span>
              <span class="font-semibold text-amber-300">{{ awaiting.length }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-fog-400">Class average</span>
              <span class="font-semibold" :style="{ color: percentageTone(classAverage) }">
                {{ classAverage === null ? '—' : classAverage }}
              </span>
            </div>
          </div>

          <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/6">
            <div
              class="h-full rounded-full bg-gradient-to-r from-violet-500 to-emerald-400 transition-all duration-500"
              :style="{ width: `${submissions.length ? (graded.length / submissions.length) * 100 : 0}%` }"
            />
          </div>
        </div>

        <div v-else class="panel p-5">
          <h3 class="font-display text-sm font-semibold text-white">At a glance</h3>
          <dl class="mt-4 space-y-3 text-xs">
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Points</dt>
              <dd class="font-semibold text-white">{{ assignment.max_points }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Due</dt>
              <dd class="font-semibold text-white">{{ formatDue(assignment.due_at) }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Late work</dt>
              <dd class="font-semibold text-white">{{ assignment.allow_late ? 'Accepted' : 'Closed' }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-fog-500">Status</dt>
              <dd class="font-semibold" :class="assignment.is_overdue ? 'text-rose-300' : 'text-emerald-300'">
                {{ assignment.is_overdue ? 'Closed' : 'Open' }}
              </dd>
            </div>
          </dl>
        </div>
      </aside>
    </div>

    <ConfirmDialog
      :open="confirmDelete"
      title="Delete this task?"
      description="Submissions, grades, and feedback attached to this task will be removed for everyone."
      confirm-label="Delete task"
      tone="danger"
      @confirm="removeAssignment"
      @close="confirmDelete = false"
    />

    <ConfirmDialog
      :open="confirmBulk"
      title="Grade every scored learner"
      description="This applies the scores you entered above in one go. Feedback stays individual."
      confirm-label="Grade all"
      @confirm="runBulk"
      @close="confirmBulk = false"
    />
  </div>
</template>
