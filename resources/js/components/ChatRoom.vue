<template>
  <div class="chat-room">

    <!-- Top bar -->
    <div class="chat-topbar">
      <div class="topbar-avatar">{{ chat?.avatar_letter ?? '?' }}</div>
      <div class="topbar-info">
        <div class="topbar-name">{{ chat?.display_name ?? chatId }}</div>
        <div class="topbar-sub">
          <span v-if="chat?.username">@{{ chat.username }} &nbsp;·&nbsp;</span>
          {{ chat?.type ?? 'private' }}
        </div>
      </div>
      <button class="action-btn" :class="{ spinning: store.syncing }" @click="manualSync" title="Sync">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
      </button>
    </div>

    <!-- Messages -->
    <div class="messages-area" ref="messagesEl">
      <template v-if="store.loadingMsgs && !currentMessages.length">
        <div class="loading">{{ t('loading_messages') }}</div>
      </template>

      <template v-else>
        <template v-for="(msg, i) in currentMessages" :key="msg.id">
          <!-- Date separator -->
          <div
            v-if="showDateSep(msg, currentMessages[i - 1])"
            class="date-separator"
          >
            <span>{{ formatDate(msg.sent_date) }}</span>
          </div>

          <MessageBubble :msg="msg" />
        </template>

        <div v-if="!currentMessages.length" class="no-msgs">
          {{ t('no_messages_start') }}
        </div>
      </template>
    </div>

    <!-- Error toast -->
    <div v-if="sendError" class="send-error">{{ sendError }}</div>

    <!-- Input bar -->
    <div class="input-bar">
      <textarea
        ref="inputEl"
        v-model="draft"
        :placeholder="t('write_message')"
        rows="1"
        @keydown.enter.exact.prevent="submit"
        @input="autoResize"
      ></textarea>
      <button class="send-btn" :disabled="!draft.trim() || sending" @click="submit">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
          <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
        </svg>
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useTelegramStore } from '@/stores/telegram.js';
import { useTranslations } from '@/i18n/index.js';
import MessageBubble from './MessageBubble.vue';

const props = defineProps({ chatId: { type: String, required: true } });

const store      = useTelegramStore();
const { t }      = useTranslations();
const messagesEl = ref(null);
const inputEl    = ref(null);
const draft      = ref('');
const sending    = ref(false);
const sendError  = ref('');
let   errorTimer = null;

// Derive the chat meta from the store's chat list
const chat = computed(() =>
  store.chats.find(c => c.chat_id === props.chatId) ?? null
);

const currentMessages = computed(() =>
  store.messages[props.chatId] ?? []
);

// Load messages when chatId changes
watch(() => props.chatId, async (id) => {
  if (id) {
    await store.fetchMessages(id);
    scrollBottom();
  }
}, { immediate: true });

// Scroll down whenever messages grow
watch(currentMessages, async () => {
  await nextTick();
  scrollBottom();
}, { deep: true });

function scrollBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
  }
}

async function submit() {
  const text = draft.value.trim();
  if (!text || sending.value) return;

  sending.value  = true;
  sendError.value = '';
  draft.value    = '';
  await nextTick();
  if (inputEl.value) {
    inputEl.value.style.height = 'auto';
    inputEl.value.focus();
  }

  try {
    await store.sendMessage(props.chatId, text);
  } catch (e) {
    sendError.value = e?.response?.data?.error ?? e.message ?? 'Failed to send';
    clearTimeout(errorTimer);
    errorTimer = setTimeout(() => { sendError.value = ''; }, 4000);
    // Restore draft so user can retry
    draft.value = text;
  } finally {
    sending.value = false;
  }
}

async function manualSync() {
  await store.syncUpdates(props.chatId);
}

function autoResize(e) {
  const el = e.target;
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function showDateSep(msg, prev) {
  if (!msg.sent_date) return false;
  return !prev || prev.sent_date !== msg.sent_date;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const today     = new Date(); today.setHours(0,0,0,0);
  const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
  if (d >= today)     return t('today');
  if (d >= yesterday) return t('yesterday');
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

onUnmounted(() => clearTimeout(errorTimer));
</script>

<style scoped>
.chat-room {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

/* ── Topbar ── */
.chat-topbar {
  background: var(--topbar-bg);
  padding: .75rem 1.4rem;
  display: flex; align-items: center; gap: .9rem;
  border-bottom: 1px solid var(--sidebar-border);
  min-height: 56px; flex-shrink: 0;
}

.topbar-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), #1565c0);
  display: flex; align-items: center; justify-content: center;
  font-size: .9rem; font-weight: 700; color: #fff; flex-shrink: 0;
  text-transform: uppercase;
}

.topbar-info { flex: 1; }
.topbar-name { font-size: .95rem; font-weight: 700; color: var(--sidebar-text); }
.topbar-sub  { font-size: .75rem; color: var(--accent); }

.action-btn {
  background: transparent; border: 1px solid var(--input-border);
  color: var(--sidebar-sub); width: 34px; height: 34px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all .2s; flex-shrink: 0;
}
.action-btn:hover { border-color: var(--accent); color: var(--accent); }
.action-btn.spinning svg { animation: spin .8s linear infinite; }

/* ── Messages ── */
.messages-area {
  flex: 1; overflow-y: auto;
  padding: 1rem 1.5rem;
  display: flex; flex-direction: column; gap: .2rem;
}

.loading, .no-msgs {
  text-align: center; color: var(--bubble-sub);
  font-size: .85rem; padding: 2rem;
  margin: auto;
}

.date-separator {
  text-align: center; margin: .7rem 0 .3rem;
}
.date-separator span {
  background: rgba(0,0,0,.3); color: var(--bubble-sub);
  font-size: .72rem; padding: .25rem .8rem; border-radius: 12px;
}

/* ── Error toast ── */
.send-error {
  background: rgba(229,57,53,.9); color: #fff;
  font-size: .82rem; text-align: center;
  padding: .5rem 1rem; flex-shrink: 0;
}

/* ── Input bar ── */
.input-bar {
  background: var(--input-bg);
  border-top: 1px solid var(--sidebar-border);
  padding: .85rem 1.2rem;
  display: flex; align-items: flex-end; gap: .8rem;
  flex-shrink: 0;
}

textarea {
  flex: 1;
  background: var(--chat-bg);
  border: 1px solid var(--input-border);
  border-radius: 20px;
  padding: .65rem 1.1rem;
  color: var(--sidebar-text);
  font-size: .92rem; outline: none;
  resize: none; min-height: 44px; max-height: 120px;
  line-height: 1.4; overflow-y: auto;
  transition: border-color .2s;
  font-family: inherit;
}
textarea:focus { border-color: var(--accent); }
textarea::placeholder { color: var(--sidebar-sub); }

.send-btn {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--accent); border: none;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: background .2s, transform .15s; color: #fff;
}
.send-btn:hover:not(:disabled) { background: var(--accent-hover); transform: scale(1.05); }
.send-btn:disabled { opacity: .5; cursor: default; }
</style>
