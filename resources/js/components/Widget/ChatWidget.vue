<template>
  <!-- Widget root: isolated from host page styles via a wrapper -->
  <div class="tg-widget-root">

    <!-- ── Floating button ── -->
    <button
      class="tg-fab"
      :style="{ background: color }"
      :title="isOpen ? t('close_chat') : title"
      @click="store.togglePanel()"
      :aria-label="t('open_chat')"
    >
      <span v-if="!isOpen" class="tg-fab-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
          <path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-2 10H6V10h12v2zm0-3H6V7h12v2z"/>
        </svg>
      </span>
      <span v-else class="tg-fab-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
          <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
      </span>
      <!-- Unread badge -->
      <span v-if="store.unreadCount > 0 && !isOpen" class="tg-badge">
        {{ store.unreadCount > 9 ? '9+' : store.unreadCount }}
      </span>
    </button>

    <!-- ── Chat panel ── -->
    <transition name="tg-slide">
      <div v-if="isOpen" class="tg-panel">

        <!-- Header -->
        <div class="tg-header" :style="{ background: color }">
          <div class="tg-header-avatar" :style="{ background: darken(color) }">💬</div>
          <div class="tg-header-info">
            <div class="tg-header-title">{{ t('support_chat') }}</div>
            <div class="tg-header-sub">{{ t('reply_minutes') }}</div>
          </div>
          <button class="tg-close" @click="store.closePanel()" aria-label="Close">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
          </button>
        </div>

        <!-- Messages -->
        <div class="tg-messages" ref="messagesEl">

          <!-- Loading / not ready -->
          <template v-if="!store.ready">
            <div class="tg-status-msg">{{ t('connecting') }}</div>
          </template>

          <template v-else>
            <!-- Welcome message -->
            <div class="tg-welcome">
              <div class="tg-bubble tg-bubble--in">
                {{ t('welcome_message') }}
              </div>
            </div>

            <!-- Conversation -->
            <template v-for="msg in store.messages" :key="msg.id">
              <div
                class="tg-msg-row"
                :class="msg.direction === 'out' ? 'tg-msg-row--out' : 'tg-msg-row--in'"
              >
                <div
                  class="tg-bubble"
                  :class="{
                    'tg-bubble--out':     msg.direction === 'out',
                    'tg-bubble--in':      msg.direction === 'in',
                    'tg-bubble--pending': msg.pending,
                    'tg-bubble--failed':  msg.failed,
                  }"
                >
                  {{ msg.text }}
                  <span class="tg-time">{{ msg.sent_at }}</span>
                </div>
              </div>
            </template>

            <div v-if="!store.messages.length" class="tg-status-msg">
              {{ t('no_messages_say_hello') }}
            </div>
          </template>
        </div>

        <!-- Error toast -->
        <div v-if="store.error" class="tg-error">{{ store.error }}</div>

        <!-- Input -->
        <div class="tg-input-bar">
          <textarea
            ref="inputEl"
            v-model="draft"
            :placeholder="t('write_message')"
            rows="1"
            class="tg-textarea"
            :disabled="!store.ready || store.sending"
            @keydown.enter.exact.prevent="submit"
            @input="autoResize"
          ></textarea>
          <button
            class="tg-send"
            :style="{ background: color }"
            :disabled="!draft.trim() || store.sending || !store.ready"
            @click="submit"
            aria-label="Send"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
          </button>
        </div>

      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted, computed } from 'vue';
import { useWidgetStore } from '@/stores/widget.js';
import { useTranslations } from '@/i18n/index.js';

const props = defineProps({
  title:       { type: String, default: 'Support Chat' },
  color:       { type: String, default: '#1e88e5' },
  visitorName: { type: String, default: '' },
});

const store      = useWidgetStore();
const { t }      = useTranslations();
const messagesEl = ref(null);
const inputEl    = ref(null);
const draft      = ref('');
const isOpen     = computed(() => store.isOpen);

// Darken color slightly for avatar bg
function darken(hex) {
  try {
    const n = parseInt(hex.replace('#', ''), 16);
    const r = Math.max(0, ((n >> 16) & 0xff) - 30);
    const g = Math.max(0, ((n >> 8)  & 0xff) - 30);
    const b = Math.max(0, ((n)       & 0xff) - 30);
    return `rgb(${r},${g},${b})`;
  } catch { return hex; }
}

onMounted(async () => {
  await store.initSession(props.visitorName || 'Visitor');
  store.startPolling();
});

onUnmounted(() => store.stopPolling());

// Scroll to bottom when messages change
watch(() => store.messages.length, async () => {
  await nextTick();
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
  }
});

// Also scroll when panel opens
watch(isOpen, async (val) => {
  if (val) {
    await nextTick();
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
    await nextTick();
    inputEl.value?.focus();
  }
});

async function submit() {
  const text = draft.value.trim();
  if (!text || store.sending) return;
  draft.value = '';
  await nextTick();
  if (inputEl.value) {
    inputEl.value.style.height = 'auto';
  }
  await store.sendMessage(text);
}

function autoResize(e) {
  const el = e.target;
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}
</script>

<style scoped>
/* All styles are scoped — won't leak to host page */

.tg-widget-root {
  position: fixed;
  bottom: 24px;
  left: 24px;
  z-index: 9999;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* ── FAB ── */
.tg-fab {
  width: 58px;
  height: 58px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  box-shadow: 0 4px 20px rgba(0,0,0,.25);
  transition: transform .2s, box-shadow .2s;
  position: relative;
}
.tg-fab:hover {
  transform: scale(1.08);
  box-shadow: 0 6px 24px rgba(0,0,0,.35);
}
.tg-fab-icon { display: flex; align-items: center; justify-content: center; }

.tg-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #e53935;
  color: #fff;
  font-size: .65rem;
  font-weight: 700;
  border-radius: 10px;
  padding: .15rem .4rem;
  min-width: 18px;
  text-align: center;
  line-height: 1.4;
  border: 2px solid #fff;
}

/* ── Panel ── */
.tg-panel {
  position: absolute;
  bottom: 72px;
  left: 0;
  width: 340px;
  max-height: 520px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 40px rgba(0,0,0,.18);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── Slide transition ── */
.tg-slide-enter-active,
.tg-slide-leave-active {
  transition: opacity .22s ease, transform .22s ease;
}
.tg-slide-enter-from,
.tg-slide-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(.97);
}

/* ── Header ── */
.tg-header {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .9rem 1rem;
  color: #fff;
  flex-shrink: 0;
}
.tg-header-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.tg-header-info  { flex: 1; }
.tg-header-title { font-weight: 700; font-size: .95rem; }
.tg-header-sub   { font-size: .72rem; opacity: .85; }
.tg-close {
  background: transparent;
  border: none;
  color: rgba(255,255,255,.85);
  cursor: pointer;
  padding: .25rem;
  border-radius: 6px;
  display: flex;
  align-items: center;
  transition: color .15s;
}
.tg-close:hover { color: #fff; }

/* ── Messages ── */
.tg-messages {
  flex: 1;
  overflow-y: auto;
  padding: .8rem 1rem;
  background: #f5f7fa;
  display: flex;
  flex-direction: column;
  gap: .4rem;
  min-height: 200px;
}
.tg-welcome { margin-bottom: .4rem; }

.tg-msg-row {
  display: flex;
}
.tg-msg-row--out { justify-content: flex-end; }
.tg-msg-row--in  { justify-content: flex-start; }

.tg-bubble {
  max-width: 78%;
  padding: .55rem .85rem;
  border-radius: 14px;
  font-size: .88rem;
  line-height: 1.45;
  word-break: break-word;
  position: relative;
}
.tg-bubble--in {
  background: #fff;
  color: #222;
  border-bottom-left-radius: 4px;
  box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
.tg-bubble--out {
  background: #1e88e5;
  color: #fff;
  border-bottom-right-radius: 4px;
}
.tg-bubble--pending { opacity: .65; }
.tg-bubble--failed  { border: 1px solid #e53935; }

.tg-time {
  font-size: .65rem;
  opacity: .65;
  margin-left: .5rem;
  white-space: nowrap;
}

.tg-status-msg {
  text-align: center;
  font-size: .82rem;
  color: #888;
  padding: 1rem;
  margin: auto;
}

/* ── Error toast ── */
.tg-error {
  background: rgba(229,57,53,.9);
  color: #fff;
  font-size: .78rem;
  text-align: center;
  padding: .4rem .8rem;
  flex-shrink: 0;
}

/* ── Input bar ── */
.tg-input-bar {
  display: flex;
  align-items: flex-end;
  gap: .6rem;
  padding: .7rem .9rem;
  background: #fff;
  border-top: 1px solid #e8ecf0;
  flex-shrink: 0;
}
.tg-textarea {
  flex: 1;
  background: #f0f4f8;
  border: 1px solid #dde2e8;
  border-radius: 20px;
  padding: .55rem 1rem;
  font-size: .88rem;
  color: #333;
  outline: none;
  resize: none;
  min-height: 40px;
  max-height: 100px;
  font-family: inherit;
  line-height: 1.4;
  overflow-y: auto;
  transition: border-color .2s;
}
.tg-textarea:focus      { border-color: #1e88e5; }
.tg-textarea::placeholder { color: #aaa; }
.tg-textarea:disabled   { opacity: .6; cursor: not-allowed; }

.tg-send {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: transform .15s, opacity .15s;
}
.tg-send:hover:not(:disabled) { transform: scale(1.08); }
.tg-send:disabled { opacity: .45; cursor: default; }
</style>
