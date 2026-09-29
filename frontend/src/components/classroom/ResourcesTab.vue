<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  Download,
  FileText,
  FolderOpen,
  Link2,
  NotebookPen,
  Plus,
  Search,
  Trash2,
  Upload,
} from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { fromNow } from '@/lib/format'
import type { Course, Material } from '@/types'

const props = defineProps<{ course: Course; canTeach: boolean }>()
const emit = defineEmits<{ changed: [] }>()

const toast = useToastStore()

const items = ref<Material[]>([])
const loading = ref(true)
const composerOpen = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const search = ref('')
const typeFilter = ref<'all' | 'link' | 'file' | 'note'>('all')

const form = reactive({
  title: '',
  description: '',
  type: 'link' as 'link' | 'file' | 'note',
  url: '',
  topic: '',
  file: null as File | null,
})

const typeFilters: { key: 'all' | Material['type']; label: string }[] = [
  { key: 'all', label: 'Everything' },
  { key: 'link', label: 'Links' },
  { key: 'file', label: 'Files' },
  { key: 'note', label: 'Notes' },
]

const typeMeta: Record<string, { icon: typeof Link2; label: string; tone: 'sky' | 'violet' | 'amber' }> = {
  link: { icon: Link2, label: 'Link', tone: 'sky' },
  file: { icon: FileText, label: 'File', tone: 'violet' },
  note: { icon: NotebookPen, label: 'Note', tone: 'amber' },
}

const topics = computed(() =>
  [...new Set(items.value.map((item) => item.topic).filter((topic): topic is string => Boolean(topic)))].sort(),
)

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()

  return items.value.filter((item) => {
    if (typeFilter.value !== 'all' && item.type !== typeFilter.value) return false
    if (!term) return true
    return (
      item.title.toLowerCase().includes(term) ||
      (item.description ?? '').toLowerCase().includes(term) ||
      (item.topic ?? '').toLowerCase().includes(term)
    )
  })
})

const grouped = computed(() => {
  const map = new Map<string, Material[]>()

  for (const item of filtered.value) {
    const key = item.topic ?? 'General'
    map.set(key, [...(map.get(key) ?? []), item])
  }

  return [...map.entries()].sort(([a], [b]) => a.localeCompare(b))
})

async function load() {
  loading.value = true
  try {
    const payload = await request<{ data: Material[] }>({ url: `/courses/${props.course.id}/materials` })
    items.value = payload.data
  } catch (error) {
    toast.error('Could not load resources', errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function create() {
  busy.value = true
  errors.value = {}

  try {
    const body = new FormData()
    body.append('title', form.title)
    body.append('type', form.type)
    if (form.description) body.append('description', form.description)
    if (form.topic) body.append('topic', form.topic)
    if (form.type === 'link' && form.url) body.append('url', form.url)
    if (form.file) body.append('file', form.file)

    const payload = await request<{ message: string; material: Material }>({
      url: `/courses/${props.course.id}/materials`,
      method: 'POST',
      data: body,
    })

    items.value.unshift(payload.material)
    toast.success(payload.message)
    composerOpen.value = false
    Object.assign(form, { title: '', description: '', type: 'link', url: '', topic: '', file: null })
    emit('changed')
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not add resource', errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function remove(material: Material) {
  try {
    await request({ url: `/courses/${props.course.id}/materials/${material.id}`, method: 'DELETE' })
    items.value = items.value.filter((item) => item.id !== material.id)
    toast.success('Resource removed')
    emit('changed')
  } catch (error) {
    toast.error('Could not remove resource', errorMessage(error))
  }
}

function pickFile(event: Event) {
  const input = event.target as HTMLInputElement
  form.file = input.files?.[0] ?? null
  if (form.file) {
    form.type = 'file'
    if (!form.title) form.title = form.file.name.replace(/\.[^.]+$/, '')
  }
}

function target(material: Material) {
  if (material.type === 'link' && material.url) return material.url
  return material.file_url
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center gap-1 rounded-full border border-white/8 bg-white/3 p-1">
        <button
          v-for="option in typeFilters"
          :key="option.key"
          class="rounded-full px-3 py-1.5 text-xs font-medium transition"
          :class="typeFilter === option.key ? 'bg-white/12 text-white' : 'text-fog-400 hover:text-white'"
          @click="typeFilter = option.key"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="relative ml-auto min-w-[12rem] flex-1 sm:w-60 sm:flex-none">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-fog-500" />
        <input v-model="search" type="search" placeholder="Search resources" class="field h-9 py-0 pl-9 text-sm" />
      </div>

      <BaseButton v-if="canTeach" size="sm" @click="composerOpen = true">
        <template #icon><Plus class="h-3.5 w-3.5" /></template>
        Add resource
      </BaseButton>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="n in 4" :key="n" class="panel p-5"><SkeletonBlock :rows="2" /></div>
    </div>

    <EmptyState
      v-else-if="!filtered.length"
      :icon="FolderOpen"
      title="Nothing in the library yet"
      :description="canTeach ? 'Share slides, links, and notes so learners can self-serve.' : 'Your teacher has not shared any resources yet.'"
    >
      <template v-if="canTeach" #action>
        <BaseButton @click="composerOpen = true">
          <template #icon><Upload class="h-4 w-4" /></template>
          Share something
        </BaseButton>
      </template>
    </EmptyState>

    <div v-else class="space-y-6">
      <section v-for="[topic, materials] in grouped" :key="topic">
        <h3 class="mb-3 flex items-center gap-2 font-display text-sm font-semibold text-white">
          <FolderOpen class="h-3.5 w-3.5 text-fog-500" />
          {{ topic }}
          <span class="chip">{{ materials.length }}</span>
        </h3>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="material in materials"
            :key="material.id"
            class="panel group flex flex-col p-4 transition hover:border-white/18"
          >
            <div class="flex items-start gap-3">
              <span
                class="grid h-9 w-9 shrink-0 place-items-center rounded-xl2"
                :class="{
                  'bg-sky-400/12 text-sky-300': material.type === 'link',
                  'bg-violet-400/12 text-violet-300': material.type === 'file',
                  'bg-amber-400/12 text-amber-300': material.type === 'note',
                }"
              >
                <component :is="typeMeta[material.type]?.icon ?? FileText" class="h-4 w-4" />
              </span>

              <div class="min-w-0 flex-1">
                <p class="truncate font-display text-sm font-semibold text-white">{{ material.title }}</p>
                <p v-if="material.description" class="mt-1 line-clamp-2 text-[0.72rem] leading-relaxed text-fog-400">
                  {{ material.description }}
                </p>
              </div>
            </div>

            <div class="mt-3 flex items-center gap-2 text-[0.68rem] text-fog-500">
              <BaseBadge :tone="typeMeta[material.type]?.tone ?? 'violet'" size="xs">
                {{ typeMeta[material.type]?.label ?? material.type }}
              </BaseBadge>
              <span v-if="material.uploader">by {{ material.uploader.name }}</span>
              <span>·</span>
              <span>{{ fromNow(material.created_at) }}</span>
            </div>

            <div class="mt-3 flex items-center gap-2 border-t border-white/6 pt-3">
              <a
                v-if="target(material)"
                :href="target(material)!"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 text-[0.72rem] font-medium text-violet-300 transition hover:text-violet-200"
              >
                <Download class="h-3.5 w-3.5" />
                {{ material.type === 'link' ? 'Open link' : 'Download' }}
              </a>
              <span v-else class="text-[0.72rem] text-fog-600">Nothing attached</span>

              <button
                v-if="canTeach"
                class="ml-auto grid h-7 w-7 place-items-center rounded-full text-fog-500 transition hover:bg-rose-400/12 hover:text-rose-300"
                @click="remove(material)"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </article>
        </div>
      </section>
    </div>

    <p v-if="topics.length && !canTeach" class="text-center text-xs text-fog-600">
      Topics in this library: {{ topics.join(', ') }}
    </p>

    <BaseModal :open="composerOpen" title="Share a resource" size="md" @close="composerOpen = false">
      <form class="space-y-4" @submit.prevent="create">
        <BaseSelect
          v-model="form.type"
          label="Kind"
          :options="[
            { value: 'link', label: 'Link' },
            { value: 'file', label: 'File upload' },
            { value: 'note', label: 'Note' },
          ]"
        />

        <BaseInput v-model="form.title" label="Title" placeholder="Week 3 lecture slides" :error="errors.title" />

        <BaseTextarea v-model="form.description" label="Description" :rows="3" placeholder="What is this for?" />

        <BaseInput v-model="form.topic" label="Topic" placeholder="Lectures" :error="errors.topic" />

        <BaseInput
          v-if="form.type === 'link'"
          v-model="form.url"
          label="URL"
          placeholder="https://…"
          :error="errors.url"
        />

        <label
          v-else-if="form.type === 'file'"
          class="flex cursor-pointer flex-col items-center gap-2 rounded-xl2 border border-dashed border-white/14 bg-white/2 px-4 py-7 text-center transition hover:border-violet-400/40 hover:bg-white/4"
        >
          <Upload class="h-5 w-5 text-violet-300" />
          <span class="text-xs font-medium text-white">{{ form.file ? form.file.name : 'Choose a file' }}</span>
          <span class="text-[0.68rem] text-fog-500">Up to 20 MB</span>
          <input type="file" class="hidden" @change="pickFile" />
        </label>
      </form>

      <template #footer>
        <BaseButton variant="ghost" @click="composerOpen = false">Cancel</BaseButton>
        <BaseButton :loading="busy" :disabled="!form.title" @click="create">
          <template #icon><Plus class="h-4 w-4" /></template>
          Add to library
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
