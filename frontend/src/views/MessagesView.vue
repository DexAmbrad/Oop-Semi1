<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  CheckCheck,
  Inbox,
  MessageSquare,
  Search,
  Send,
  Sparkles,
  UserPlus,
  X,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseAvatar from '@/components/ui/BaseAvatar.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { errorMessage, request } from '@/lib/api'
import { ROLE_LABEL } from '@/lib/accents'
import { dayjs, fromNow } from '@/lib/format'
import type { Conversation, Message, Role, User } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

interface Partner {
  id: number
  name: string
  email: string
  role: Role
  initials: string
  headline: string | null
  last_seen_at: string | null
}

const conversations = ref<Conversation[]>([])
const messages = ref<Message[]>([])
const partner = ref<Partner | null>(null)
const loading = ref(true)
const threadLoading = ref(false)
const sending = ref(false)
const search = ref('')
const draft = ref('')
const scroller = ref<HTMLElement | null>(null)

const composerOpen = ref(false)
const contacts = ref<User[]>([])
const contactSearch = ref('')
const contactsLoading = ref(false)

const activeId = computed(() => (route.params.userId ? Number(route.params.userId) : null))

const filteredConversations = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return conversations.value
  return conversations.value.filter((item) => item.partner.name.toLowerCase().includes(term))
})

const filteredContacts = computed(() => {
  const term = contactSearch.value.trim().toLowerCase()
  if (!term) return contacts.value
  return contacts.value.filter(
    (item) => item.name.toLowerCase().includes(term) || item.email.toLowerCase().includes(term),
  )
})

const totalUnread = computed(() => conversations.value.reduce((sum, item) => sum + item.unread, 0))

async function loadConversations() {
  try {
    conversations.value = await request<Conversation[]>({ url: '/messages' })
  } catch (error) {
    toast.error('Could not load conversations', errorMessage(error))
  }
}

async function openThread(id: number) {
  if (route.params.userId === String(id)) return
  router.push({ name: 'messages', params: { userId: id } })
}

async function loadThread(id: number) {
  threadLoading.value = true

  try {
    const payload = await request<{ partner: Partner; messages: Message[] }>({ url: `/messages/${id}` })
    partner.value = payload.partner
    messages.value = payload.messages

    const row = conversations.value.find((item) => item.partner.id === id)
    if (row) row.unread = 0

    await nextTick()
    scrollToEnd()
  } catch (error) {
    toast.error('Could not open that conversation', errorMessage(error))
  } finally {
    threadLoading.value = false
  }
}

async function send() {
  const body = draft.value.trim()
  if (!body || !partner.value) return

  sending.value = true

  try {
    const payload = await request<{ message: Message }>({
      url: `/messages/${partner.value.id}`,
      method: 'POST',
      data: { body },
    })

    messages.value.push(payload.message)
    draft.value = ''
    await nextTick()
    scrollToEnd()

    const row = conversations.value.find((item) => item.partner.id === partner.value?.id)
    if (row) {
      row.last_message = body
      row.last_at = payload.message.created_at
      conversations.value = [...conversations.value].sort((a, b) => (b.last_at ?? '').localeCompare(a.last_at ?? ''))
    }
  } catch (error) {
    toast.error('Message not sent', errorMessage(error))
  } finally {
    sending.value = false
  }
}

async function loadContacts() {
  contactsLoading.value = true

  try {
    const payload = await request<{ data: User[] }>({
      url: '/messages/contacts',
      params: { search: contactSearch.value || undefined },
    })
    contacts.value = payload.data
  } catch (error) {
    toast.error('Could not load people', errorMessage(error))
  } finally {
    contactsLoading.value = false
  }
}

async function startConversation(user: User) {
  composerOpen.value = false
  contactSearch.value = ''
  await openThread(user.id)

  if (!conversations.value.some((item) => item.partner.id === user.id)) {
    conversations.value = [
      {
        partner: {
          id: user.id,
          name: user.name,
          initials: user.initials,
          role: user.role,
          last_seen_at: user.last_seen_at,
        },
        last_message: '',
        last_at: null,
        unread: 0,
      },
      ...conversations.value,
    ]
  }
}

function scrollToEnd() {
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
}

const grouped = computed(() => {
  const rows: { day: string; items: Message[] }[] = []

  for (const message of messages.value) {
    const day = dayjs(message.created_at).format('YYYY-MM-DD')
    const last = rows[rows.length - 1]

    if (last && last.day === day) last.items.push(message)
    else rows.push({ day, items: [message] })
  }

  return rows
})

watch(activeId, (id) => {
  if (id) loadThread(id)
  else {
    partner.value = null
    messages.value = []
  }
})

watch(
  () => composerOpen.value,
  (open) => {
    if (open) loadContacts()
  },
)

onMounted(async () => {
  loading.value = true
  await loadConversations()
  loading.value = false

  if (activeId.value) loadThread(activeId.value)
})
</script>

<template>
  <div>
    <PageHeader eyebrow="Messages" title="Talk to your people" description="Direct threads with teachers and classmates.">
      <template #actions>
        <BaseButton size="sm" @click="composerOpen = true">
          <template #icon><UserPlus class="h-3.5 w-3.5" /></template>
          New message
        </BaseButton>
      </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-[19rem_1fr]">
      <!-- Conversation list -->
      <aside class="panel flex max-h-[calc(100vh-14rem)] flex-col overflow-hidden">
        <div class="border-b border-white/7 p-3">
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-fog-500" />
            <input v-model="search" type="search" placeholder="Search conversations" class="field h-9 py-0 pl-9 text-sm" />
          </div>
        </div>

        <div v-if="loading" class="space-y-2 p-3">
          <div v-for="n in 5" :key="n" class="panel p-3"><SkeletonBlock :rows="1" /></div>
        </div>

        <EmptyState
          v-else-if="!filteredConversations.length"
          :icon="MessageSquare"
          compact
          title="No threads yet"
          description="Start a conversation with a teacher or classmate."
        />

        <ul v-else class="flex-1 overflow-y-auto">
          <li v-for="item in filteredConversations" :key="item.partner.id">
            <button
              class="flex w-full items-start gap-3 border-l-2 px-3.5 py-3 text-left transition"
              :class="
                activeId === item.partner.id
                  ? 'border-violet-400 bg-violet-400/8'
                  : 'border-transparent hover:bg-white/3'
              "
              @click="openThread(item.partner.id)"
            >
              <BaseAvatar :name="item.partner.name" :initials="item.partner.initials" :role="item.partner.role" size="sm" />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <p class="min-w-0 flex-1 truncate text-sm font-medium text-white">{{ item.partner.name }}</p>
                  <span class="shrink-0 text-[0.62rem] text-fog-500">{{ fromNow(item.last_at) }}</span>
                </div>
                <div class="mt-0.5 flex items-center gap-2">
                  <p class="min-w-0 flex-1 truncate text-[0.7rem]" :class="item.unread ? 'text-fog-200' : 'text-fog-500'">
                    {{ item.last_message || 'Say hello 👋' }}
                  </p>
                  <span
                    v-if="item.unread"
                    class="grid h-4 min-w-4 shrink-0 place-items-center rounded-full bg-violet-500 px-1 text-[0.6rem] font-bold text-white"
                  >
                    {{ item.unread }}
                  </span>
                </div>
              </div>
            </button>
          </li>
        </ul>

        <div v-if="totalUnread" class="border-t border-white/7 px-3.5 py-2.5 text-[0.68rem] text-fog-500">
          {{ totalUnread }} unread message{{ totalUnread === 1 ? '' : 's' }}
        </div>
      </aside>

      <!-- Thread -->
      <section class="panel flex max-h-[calc(100vh-14rem)] flex-col overflow-hidden">
        <div v-if="!activeId" class="flex flex-1 flex-col items-center justify-center px-6 text-center">
          <span class="grid h-14 w-14 place-items-center rounded-2xl border border-white/10 bg-white/5 text-fog-400">
            <Inbox class="h-6 w-6" />
          </span>
          <p class="mt-4 font-display text-base font-semibold text-fog-100">Pick a conversation</p>
          <p class="mt-1.5 max-w-xs text-sm text-fog-500">
            Your threads stay private to you and the person you message.
          </p>
        </div>

        <div v-else class="flex min-h-0 flex-1 flex-col">
          <header class="flex items-center gap-3 border-b border-white/7 px-5 py-3.5">
            <button class="grid h-8 w-8 place-items-center rounded-full text-fog-400 hover:bg-white/6 lg:hidden" @click="router.push({ name: 'messages' })">
              <ArrowLeft class="h-4 w-4" />
            </button>
            <BaseAvatar
              :name="partner?.name ?? '?'"
              :initials="partner?.initials ?? '?'"
              :role="partner?.role"
              size="sm"
            />
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-white">{{ partner?.name ?? 'Loading…' }}</p>
              <p class="truncate text-[0.68rem] text-fog-500">
                {{ partner ? ROLE_LABEL[partner.role] : '' }}
                <span v-if="partner?.headline"> · {{ partner.headline }}</span>
              </p>
            </div>
          </header>

          <div ref="scroller" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
            <div v-if="threadLoading" class="space-y-3">
              <div v-for="n in 4" :key="n" class="panel p-3"><SkeletonBlock :rows="1" /></div>
            </div>

            <template v-else>
              <div v-for="group in grouped" :key="group.day" class="space-y-2.5">
                <p class="text-center text-[0.62rem] tracking-wide text-fog-600 uppercase">
                  {{ dayjs(group.day).format('ddd D MMM') }}
                </p>

                <div
                  v-for="message in group.items"
                  :key="message.id"
                  class="flex"
                  :class="message.sender_id === auth.user?.id ? 'justify-end' : 'justify-start'"
                >
                  <div
                    class="max-w-[min(32rem,80%)] rounded-2xl px-4 py-2.5"
                    :class="
                      message.sender_id === auth.user?.id
                        ? 'rounded-br-sm bg-violet-500/85 text-white'
                        : 'rounded-bl-sm border border-white/8 bg-white/5 text-fog-200'
                    "
                  >
                    <p class="text-sm leading-relaxed whitespace-pre-line">{{ message.body }}</p>
                    <p
                      class="mt-1 flex items-center justify-end gap-1.5 text-[0.6rem]"
                      :class="message.sender_id === auth.user?.id ? 'text-violet-100/70' : 'text-fog-500'"
                    >
                      <span v-if="message.course" class="inline-flex items-center gap-1">
                        <Sparkles class="h-2.5 w-2.5" />{{ message.course.title }}
                      </span>
                      {{ dayjs(message.created_at).format('HH:mm') }}
                      <CheckCheck v-if="message.sender_id === auth.user?.id" class="h-2.5 w-2.5" />
                    </p>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <form class="flex items-end gap-2 border-t border-white/7 p-3" @submit.prevent="send">
            <textarea
              v-model="draft"
              rows="1"
              class="field max-h-32 min-h-[2.75rem] flex-1 resize-none py-2.5 text-sm"
              placeholder="Write a message…"
              @keydown.enter.exact.prevent="send"
            />
            <BaseButton type="submit" :loading="sending" :disabled="!draft.trim()">
              <template #icon><Send class="h-4 w-4" /></template>
              Send
            </BaseButton>
          </form>
        </div>
      </section>
    </div>

    <BaseModal :open="composerOpen" title="Start a conversation" size="md" @close="composerOpen = false">
      <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-fog-500" />
        <input
          v-model="contactSearch"
          type="search"
          placeholder="Search by name or email"
          class="field h-10 py-0 pl-9 text-sm"
          @input="loadContacts"
        />
      </div>

      <div class="mt-4 max-h-80 space-y-1.5 overflow-y-auto">
        <div v-if="contactsLoading" class="space-y-2">
          <div v-for="n in 5" :key="n" class="panel p-3"><SkeletonBlock :rows="1" /></div>
        </div>

        <EmptyState v-else-if="!filteredContacts.length" :icon="UserPlus" compact title="Nobody found" description="Try a different search." />

        <template v-else>
          <button
            v-for="person in filteredContacts"
            :key="person.id"
            class="flex w-full items-center gap-3 rounded-xl2 border border-white/6 bg-white/2 px-3.5 py-2.5 text-left transition hover:border-violet-400/30 hover:bg-white/5"
            @click="startConversation(person)"
          >
            <BaseAvatar :name="person.name" :initials="person.initials" :role="person.role" size="sm" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ person.name }}</p>
              <p class="truncate text-[0.68rem] text-fog-500">{{ person.headline ?? person.email }}</p>
            </div>
            <span class="shrink-0 text-[0.66rem] text-fog-500">{{ ROLE_LABEL[person.role] }}</span>
          </button>
        </template>
      </div>

      <template #footer>
        <BaseButton variant="ghost" @click="composerOpen = false">
          <template #icon><X class="h-4 w-4" /></template>
          Close
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
