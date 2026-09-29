<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Megaphone, MessageSquare, Pin, Plus, Send, Trash2 } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, fieldErrors, request } from '@/lib/api'
import { fromNow } from '@/lib/format'
import type { Accent, Announcement, AnnouncementComment, Course, Paginated } from '@/types'

const props = defineProps<{ course: Course; canTeach: boolean }>()
const emit = defineEmits<{ changed: [] }>()

const auth = useAuthStore()
const toast = useToastStore()

const items = ref<Announcement[]>([])
const loading = ref(true)
const composerOpen = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const commentDrafts = ref<Record<number, string>>({})
const sendingComment = ref<number | null>(null)

const form = ref({ title: '', body: '', category: 'general', pinned: false })

const categoryTone: Record<string, Accent> = {
  general: 'violet',
  schedule: 'sky',
  resource: 'emerald',
  event: 'indigo',
  urgent: 'rose',
}

const canPost = computed(() => props.canTeach && props.course.status === 'active')

async function load() {
  loading.value = true
  try {
    const payload = await request<Paginated<Announcement>>({
      url: `/courses/${props.course.id}/announcements`,
      params: { per_page: 30 },
    })
    items.value = payload.data
  } catch (error) {
    toast.error('Could not load the stream', errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function publish() {
  busy.value = true
  errors.value = {}

  try {
    const payload = await request<{ message: string; announcement: Announcement }>({
      url: `/courses/${props.course.id}/announcements`,
      method: 'POST',
      data: form.value,
    })

    items.value.unshift({ ...payload.announcement, comments: [] })
    toast.success(payload.message)
    composerOpen.value = false
    form.value = { title: '', body: '', category: 'general', pinned: false }
    emit('changed')
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error('Could not publish', errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function comment(announcement: Announcement) {
  const body = (commentDrafts.value[announcement.id] ?? '').trim()
  if (!body) return

  sendingComment.value = announcement.id

  try {
    const payload = await request<{ message: string; comment: AnnouncementComment }>({
      url: `/courses/${props.course.id}/announcements/${announcement.id}/comments`,
      method: 'POST',
      data: { body },
    })

    announcement.comments.push(payload.comment)
    announcement.comments_count += 1
    commentDrafts.value[announcement.id] = ''
  } catch (error) {
    toast.error('Could not post reply', errorMessage(error))
  } finally {
    sendingComment.value = null
  }
}

async function remove(announcement: Announcement) {
  try {
    await request({ url: `/courses/${props.course.id}/announcements/${announcement.id}`, method: 'DELETE' })
    items.value = items.value.filter((item) => item.id !== announcement.id)
    toast.success('Announcement deleted')
    emit('changed')
  } catch (error) {
    toast.error('Could not delete', errorMessage(error))
  }
}

async function togglePin(announcement: Announcement) {
  try {
    await request({
      url: `/courses/${props.course.id}/announcements/${announcement.id}`,
      method: 'PATCH',
      data: { pinned: !announcement.pinned },
    })
    announcement.pinned = !announcement.pinned
    items.value = [...items.value].sort((a, b) => Number(b.pinned) - Number(a.pinned))
  } catch (error) {
    toast.error('Could not update', errorMessage(error))
  }
}

onMounted(load)
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
    <div class="space-y-4">
      <div v-if="canPost" class="panel p-4">
        <button
          class="flex w-full items-center gap-3 rounded-xl2 border border-white/8 bg-white/3 px-4 py-3 text-left transition hover:border-white/20 hover:bg-white/6"
          @click="composerOpen = true"
        >
          <BaseAvatar v-if="auth.user" :name="auth.user.name" :role="auth.user.role" size="sm" />
          <span class="flex-1 text-sm text-fog-400">Share something with the class…</span>
          <span class="grid h-8 w-8 place-items-center rounded-full bg-violet-500 text-white"><Plus class="h-4 w-4" /></span>
        </button>
      </div>

      <div v-if="loading" class="space-y-4">
        <div v-for="n in 3" :key="n" class="panel p-5"><SkeletonBlock :rows="4" /></div>
      </div>

      <EmptyState
        v-else-if="!items.length"
        :icon="Megaphone"
        title="The stream is quiet"
        description="Announcements, reminders and class-wide updates will show up here."
      />

      <template v-else>
        <article v-for="item in items" :key="item.id" class="panel anim-rise p-5">
        <header class="flex items-start gap-3">
          <BaseAvatar
            v-if="item.author"
            :name="item.author.name"
            :initials="item.author.initials"
            :role="item.author.role"
            size="md"
          />
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-semibold text-white">{{ item.author?.name ?? 'Instructor' }}</p>
              <BaseBadge :tone="categoryTone[item.category] ?? 'violet'" size="xs">{{ item.category }}</BaseBadge>
              <BaseBadge v-if="item.pinned" tone="amber" size="xs"><template #icon><Pin class="h-2.5 w-2.5" /></template>Pinned</BaseBadge>
            </div>
            <p class="mt-0.5 text-[0.7rem] text-fog-500">{{ fromNow(item.published_at) }}</p>
          </div>

          <div v-if="canTeach" class="flex shrink-0 items-center gap-1">
            <button
              class="grid h-8 w-8 place-items-center rounded-full text-fog-500 transition hover:bg-white/8 hover:text-amber-300"
              :title="item.pinned ? 'Unpin' : 'Pin'"
              @click="togglePin(item)"
            >
              <Pin class="h-3.5 w-3.5" />
            </button>
            <button
              class="grid h-8 w-8 place-items-center rounded-full text-fog-500 transition hover:bg-rose-400/12 hover:text-rose-300"
              title="Delete"
              @click="remove(item)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </header>

        <h3 class="mt-4 font-display text-base font-semibold text-white">{{ item.title }}</h3>
        <p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-fog-300">{{ item.body }}</p>

        <div class="mt-5 border-t border-white/6 pt-4">
          <div v-if="item.comments?.length" class="mb-3 space-y-3">
            <div v-for="reply in item.comments" :key="reply.id" class="flex items-start gap-3">
              <BaseAvatar v-if="reply.user" :name="reply.user.name" :initials="reply.user.initials" :role="reply.user.role" size="xs" />
              <div class="min-w-0 flex-1 rounded-xl2 bg-white/3 px-3 py-2">
                <div class="flex items-center gap-2">
                  <p class="text-xs font-semibold text-white">{{ reply.user?.name }}</p>
                  <p class="text-[0.66rem] text-fog-500">{{ fromNow(reply.created_at) }}</p>
                </div>
                <p class="mt-1 text-xs leading-relaxed text-fog-300">{{ reply.body }}</p>
              </div>
            </div>
          </div>

          <p v-if="!item.comments?.length" class="mb-3 text-[0.7rem] text-fog-500">
            <MessageSquare class="mr-1 inline h-3 w-3" />No replies yet. Start the conversation.
          </p>

          <div class="flex items-center gap-2">
            <input
              v-model="commentDrafts[item.id]"
              type="text"
              placeholder="Write a reply…"
              class="field h-9 py-0 text-sm"
              @keydown.enter.prevent="comment(item)"
            />
            <BaseButton
              size="sm"
              :loading="sendingComment === item.id"
              :disabled="!(commentDrafts[item.id] ?? '').trim()"
              @click="comment(item)"
            >
              <template #icon><Send class="h-3.5 w-3.5" /></template>
            </BaseButton>
          </div>
        </div>
        </article>
      </template>
    </div>

    <aside class="space-y-4">
      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">Class essentials</h3>
        <dl class="mt-4 space-y-3 text-xs">
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Room code</dt>
            <dd class="font-mono font-semibold text-white">{{ course.room_code }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Learners</dt>
            <dd class="font-semibold text-white">{{ course.students_count }} / {{ course.capacity }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Open work</dt>
            <dd class="font-semibold text-white">{{ course.assignments_count }}</dd>
          </div>
          <div class="flex items-center justify-between">
            <dt class="text-fog-500">Resources</dt>
            <dd class="font-semibold text-white">{{ course.materials_count }}</dd>
          </div>
        </dl>
      </div>

      <div class="panel p-5">
        <h3 class="font-display text-sm font-semibold text-white">Instructor</h3>
        <div v-if="course.teacher" class="mt-3 flex items-center gap-3">
          <BaseAvatar :name="course.teacher.name" :initials="course.teacher.initials" :accent="course.accent" size="md" />
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">{{ course.teacher.name }}</p>
            <p class="truncate text-[0.7rem] text-fog-500">{{ course.teacher.headline ?? course.teacher.email }}</p>
          </div>
        </div>
      </div>
    </aside>

    <BaseModal :open="composerOpen" title="New announcement" size="md" @close="composerOpen = false">
      <form class="space-y-4" @submit.prevent="publish">
        <BaseInput v-model="form.title" label="Headline" placeholder="Week 4 plan is live" :error="errors.title" />
        <BaseTextarea v-model="form.body" label="Message" :rows="6" placeholder="Keep it short and specific." :error="errors.body" />
        <div class="grid gap-4 sm:grid-cols-2">
          <BaseSelect
            v-model="form.category"
            label="Category"
            :options="[
              { value: 'general', label: 'General' },
              { value: 'schedule', label: 'Schedule' },
              { value: 'resource', label: 'Resource' },
              { value: 'event', label: 'Event' },
              { value: 'urgent', label: 'Urgent' },
            ]"
          />
          <label class="flex items-end gap-2 pb-2.5 text-sm text-fog-300">
            <input v-model="form.pinned" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-white/5" />
            Pin to the top of the stream
          </label>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="ghost" @click="composerOpen = false">Cancel</BaseButton>
        <BaseButton :loading="busy" :disabled="!form.title || !form.body" @click="publish">
          <template #icon><Megaphone class="h-4 w-4" /></template>
          Publish
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
