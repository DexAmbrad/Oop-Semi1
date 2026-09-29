<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Award, Plus, RefreshCw, Save, TrendingUp, Trash2, X } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ProgressRing from '@/components/ui/ProgressRing.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useToastStore } from '@/stores/toast'
import { errorMessage, request } from '@/lib/api'
import { percentageTone } from '@/lib/format'
import type { Course, Grade, Gradebook } from '@/types'

const props = defineProps<{ course: Course; canTeach: boolean }>()

const toast = useToastStore()

const data = ref<Gradebook | null>(null)
const loading = ref(true)
const manualOpen = ref(false)
const busy = ref(false)
const pendingCells = ref<string[]>([])
const drafts = reactive<Record<string, string>>({})

const form = reactive({ user_id: '', item_title: '', score: 0, max_score: 100, feedback: '' })

const distributions = computed(() => {
  if (!data.value) return []

  const buckets = [
    { label: 'A', min: 90, tone: '#34d399' },
    { label: 'B', min: 80, tone: '#38bdf8' },
    { label: 'C', min: 70, tone: '#fbbf24' },
    { label: 'D', min: 60, tone: '#fb923c' },
    { label: 'F', min: 0, tone: '#fb7185' },
  ]

  const rows = data.value.rows.filter((row) => row.percentage !== null)
  const total = rows.length || 1

  return buckets.map((bucket, index) => {
    const upper = index === 0 ? 101 : buckets[index - 1].min
    const count = rows.filter((row) => (row.percentage ?? 0) >= bucket.min && (row.percentage ?? 0) < upper).length
    return { ...bucket, count, share: (count / total) * 100 }
  })
})

const gradeRows = computed(() => {
  if (!data.value) return []
  return [...data.value.rows].sort((a, b) => (b.percentage ?? -1) - (a.percentage ?? -1))
})

const manualGrades = ref<Grade[]>([])

async function load(silent = false) {
  if (!silent) loading.value = true
  try {
    data.value = await request<Gradebook>({ url: `/courses/${props.course.id}/gradebook` })

    if (props.canTeach) {
      const payload = await request<{ data: Grade[] }>({ url: `/courses/${props.course.id}/grades` })
      manualGrades.value = payload.data.filter((grade) => grade.item_type === 'manual')
    }
  } catch (error) {
    toast.error('Could not load the gradebook', errorMessage(error))
  } finally {
    if (!silent) loading.value = false
  }
}

function cellKey(userId: number, itemId: number) {
  return `${userId}:${itemId}`
}

function valueFor(userId: number, cell: { item_id: number; score: number | null }) {
  const key = cellKey(userId, cell.item_id)
  if (drafts[key] === undefined) {
    return cell.score === null ? '' : String(cell.score)
  }
  return drafts[key]
}

function isSaving(key: string) {
  return pendingCells.value.includes(key)
}

async function saveCell(rowUserId: number, cell: { item_id: number; score: number | null }) {
  const key = cellKey(rowUserId, cell.item_id)
  const raw = drafts[key]
  if (raw === undefined) return

  const score = raw === '' ? null : Number(raw)

  if (score === null && cell.score === null) {
    delete drafts[key]
    return
  }

  pendingCells.value = [...pendingCells.value, key]

  try {
    await request({
      url: `/courses/${props.course.id}/gradebook/cell`,
      method: 'POST',
      data: { user_id: rowUserId, item_id: cell.item_id, score },
    })

    delete drafts[key]
    await load(true)
  } catch (error) {
    toast.error('Could not save grade', errorMessage(error))
  } finally {
    pendingCells.value = pendingCells.value.filter((item) => item !== key)
  }
}

async function saveManual() {
  busy.value = true
  try {
    await request({
      url: `/courses/${props.course.id}/grades`,
      method: 'POST',
      data: {
        user_id: Number(form.user_id),
        item_title: form.item_title,
        score: form.score,
        max_score: form.max_score,
        feedback: form.feedback || null,
      },
    })

    toast.success('Manual grade recorded')
    manualOpen.value = false
    Object.assign(form, { user_id: '', item_title: '', score: 0, max_score: 100, feedback: '' })
    await load()
  } catch (error) {
    toast.error('Could not record grade', errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function removeManual(grade: Grade) {
  try {
    await request({ url: `/courses/${props.course.id}/grades/${grade.id}`, method: 'DELETE' })
    manualGrades.value = manualGrades.value.filter((item) => item.id !== grade.id)
    toast.success('Grade removed')
    await load()
  } catch (error) {
    toast.error('Could not remove grade', errorMessage(error))
  }
}

function dismissDraft(key: string) {
  delete drafts[key]
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="grid gap-4 lg:grid-cols-[17rem_1fr]">
      <div class="panel flex flex-col items-center p-5">
        <h3 class="mb-4 self-start font-display text-sm font-semibold text-white">Class average</h3>
        <ProgressRing :value="data?.class_average ?? null" :size="150" :thickness="11" label="Gradebook" />
        <p class="mt-4 text-center text-xs leading-relaxed text-fog-500">
          Weighted across {{ data?.items.length ?? 0 }} graded items and {{ data?.rows.length ?? 0 }} learners.
        </p>
      </div>

      <div class="panel p-5">
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <TrendingUp class="h-4 w-4 text-emerald-300" />
            <h3 class="font-display text-sm font-semibold text-white">Score distribution</h3>
          </div>
          <BaseButton size="sm" variant="outline" :loading="loading" @click="load">
            <template #icon><RefreshCw class="h-3.5 w-3.5" /></template>
            Refresh
          </BaseButton>
        </div>

        <div v-if="!data || !data.rows.some((row) => row.percentage !== null)" class="py-8 text-center text-sm text-fog-500">
          No grades recorded yet.
        </div>

        <div v-else class="space-y-3">
          <div v-for="bucket in distributions" :key="bucket.label" class="flex items-center gap-3">
            <span class="w-6 font-display text-sm font-semibold" :style="{ color: bucket.tone }">{{ bucket.label }}</span>
            <div class="h-3 flex-1 overflow-hidden rounded-full bg-white/5">
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: `${bucket.share}%`, background: bucket.tone, opacity: bucket.count ? 1 : 0.25 }"
              />
            </div>
            <span class="w-10 text-right text-xs font-medium text-fog-400">{{ bucket.count }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="panel overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/7 px-5 py-4">
        <div class="flex items-center gap-2">
          <Award class="h-4 w-4 text-amber-300" />
          <h3 class="font-display text-sm font-semibold text-white">
            {{ canTeach ? 'Gradebook matrix' : 'Your grades' }}
          </h3>
          <span v-if="pendingCells.length" class="chip border-violet-400/30 text-violet-200">
            Saving {{ pendingCells.length }}…
          </span>
          <span v-else-if="Object.keys(drafts).length" class="chip border-amber-400/30 text-amber-200">
            {{ Object.keys(drafts).length }} unsaved
          </span>
        </div>

        <BaseButton v-if="canTeach" size="sm" @click="manualOpen = true">
          <template #icon><Plus class="h-3.5 w-3.5" /></template>
          Manual grade
        </BaseButton>
      </div>

      <div v-if="loading" class="p-5"><SkeletonBlock :rows="5" /></div>

      <EmptyState
        v-else-if="!data || !gradeRows.length"
        :icon="Award"
        compact
        title="The gradebook is empty"
        description="Grades appear here automatically as you grade submissions."
      />

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[46rem] border-collapse text-sm">
          <thead>
            <tr class="border-b border-white/7 text-left">
              <th class="sticky left-0 z-10 bg-ink-880 px-5 py-3 text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">
                Learner
              </th>
              <th
                v-for="item in data.items"
                :key="item.item_id"
                class="px-3 py-3 text-center text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase"
                :title="item.title"
              >
                {{ item.label }}
              </th>
              <th class="px-4 py-3 text-right text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">Total</th>
              <th class="px-5 py-3 text-right text-[0.66rem] font-semibold tracking-wide text-fog-500 uppercase">%</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in gradeRows" :key="row.user.id" class="border-b border-white/4 transition hover:bg-white/3">
              <td class="sticky left-0 z-10 bg-ink-880 px-5 py-2.5">
                <div class="flex items-center gap-2.5">
                  <BaseAvatar :name="row.user.name" :initials="row.user.initials" role="student" size="xs" />
                  <span class="min-w-0">
                    <span class="block truncate text-xs font-medium text-white">{{ row.user.name }}</span>
                    <span class="block truncate text-[0.64rem] text-fog-500">{{ row.user.email }}</span>
                  </span>
                </div>
              </td>

              <td v-for="cell in row.cells" :key="cell.item_id" class="px-2 py-2 text-center">
                <template v-if="canTeach">
                  <span class="relative inline-flex items-center">
                    <input
                      :value="valueFor(row.user.id, cell)"
                      type="number"
                      min="0"
                      :class="[
                        'w-16 rounded-lg border bg-white/3 px-2 py-1 text-center text-xs text-white transition focus:outline-none',
                        isSaving(cellKey(row.user.id, cell.item_id))
                          ? 'border-violet-400/60 opacity-60'
                          : 'border-white/8 focus:border-violet-400/60 focus:bg-white/6',
                      ]"
                      @input="drafts[cellKey(row.user.id, cell.item_id)] = ($event.target as HTMLInputElement).value"
                      @blur="saveCell(row.user.id, cell)"
                    />
                    <button
                      v-if="drafts[cellKey(row.user.id, cell.item_id)] !== undefined && !isSaving(cellKey(row.user.id, cell.item_id))"
                      class="absolute -right-1 -top-1 grid h-4 w-4 place-items-center rounded-full bg-rose-500 text-white"
                      @click="dismissDraft(cellKey(row.user.id, cell.item_id))"
                    >
                      <X class="h-2.5 w-2.5" />
                    </button>
                  </span>
                </template>
                <span v-else class="text-xs font-medium" :style="{ color: cell.score === null ? '#6d6d86' : '#fff' }">
                  {{ cell.score === null ? '—' : cell.score }}
                </span>
              </td>

              <td class="px-4 py-2.5 text-right text-xs font-medium text-fog-300">
                {{ row.total }}<span class="text-fog-500">/{{ row.possible }}</span>
              </td>
              <td class="px-5 py-2.5 text-right text-xs font-semibold" :style="{ color: percentageTone(row.percentage) }">
                {{ row.percentage === null ? '—' : `${row.percentage}%` }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Manual grades -->
    <div v-if="canTeach && manualGrades.length" class="panel p-5">
      <h3 class="mb-4 font-display text-sm font-semibold text-white">Manual entries</h3>
      <div class="space-y-2">
        <div
          v-for="grade in manualGrades"
          :key="grade.id"
          class="flex items-center gap-3 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-2.5"
        >
          <BaseAvatar v-if="grade.user" :name="grade.user.name" :initials="grade.user.initials" role="student" size="xs" />
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-medium text-white">{{ grade.item_title }}</p>
            <p class="truncate text-[0.66rem] text-fog-500">{{ grade.user?.name }}</p>
          </div>
          <span class="text-xs font-semibold" :style="{ color: percentageTone(grade.percentage) }">
            {{ grade.score }}/{{ grade.max_score }}
          </span>
          <button
            class="grid h-7 w-7 place-items-center rounded-full text-fog-500 transition hover:bg-rose-400/12 hover:text-rose-300"
            @click="removeManual(grade)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>

    <BaseModal :open="manualOpen" title="Record a manual grade" size="md" @close="manualOpen = false">
      <form class="space-y-4" @submit.prevent="saveManual">
        <label class="block">
          <span class="field-label">Learner</span>
          <select v-model="form.user_id" class="field">
            <option value="" disabled>Choose a learner</option>
            <option v-for="row in data?.rows ?? []" :key="row.user.id" :value="String(row.user.id)">
              {{ row.user.name }}
            </option>
          </select>
        </label>

        <BaseInput v-model="form.item_title" label="Item title" placeholder="Participation — week 4" />
        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model.number="form.score" label="Score" type="number" />
          <BaseInput v-model.number="form.max_score" label="Out of" type="number" />
        </div>
        <BaseTextarea v-model="form.feedback" label="Feedback" :rows="3" placeholder="Optional note for the learner." />
      </form>

      <template #footer>
        <BaseButton variant="ghost" @click="manualOpen = false">Cancel</BaseButton>
        <BaseButton :loading="busy" :disabled="!form.user_id || !form.item_title" @click="saveManual">
          <template #icon><Save class="h-4 w-4" /></template>
          Record grade
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
