<template>
  <div class="tgc-root">

    <!--
      Hidden input submitted with the parent form.
      Name = chat_id_column from config (default: telegram_chat_id).
      Value is empty until the user connects — then filled with their chat_id.
    -->
    <input
      type="hidden"
      :name="inputName"
      :value="connectedValue"
    />

    <!-- Visually-hidden input for native form validation when :required is true -->
    <input
      v-if="isRequired"
      ref="validationInput"
      type="text"
      :value="connectedValue"
      required
      tabindex="-1"
      aria-hidden="true"
      class="tgc-validation-input"
      @invalid.prevent="onValidationFail"
    />

    <!-- ── Already connected badge ── -->
    <div
      v-if="store.status === 'already_connected'"
      class="tgc-already"
      :style="{ borderColor: color }"
    >
      <span class="tgc-already-icon" :style="{ color }">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
        </svg>
      </span>
      <span class="tgc-already-text">{{ t('telegram_connected') }}</span>
      <button
        class="tgc-reconnect-btn"
        type="button"
        @click="store.reset()"
        :title="t('reconnect')"
      >{{ t('reconnect') }}</button>
    </div>

    <!-- ── Trigger button ── -->
    <button
      v-else-if="store.status === 'idle' || store.status === 'error'"
      class="tgc-btn"
      :class="{ 'tgc-btn--required-invalid': isRequired && !connectedValue }"
      :style="{ background: color }"
      :disabled="loading"
      @click="start"
      type="button"
    >
      <span class="tgc-btn-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
        </svg>
      </span>
      {{ store.status === 'error' ? t('retry') : label }}
    </button>

    <!-- ── Modal overlay ── -->
    <transition name="tgc-fade">
      <div
        v-if="store.status !== 'idle' && store.status !== 'already_connected'"
        class="tgc-backdrop"
        @click.self="maybeClose"
      >
        <div class="tgc-modal" role="dialog" aria-modal="true" :aria-label="t('connect_modal_title')">

          <!-- Header -->
          <div class="tgc-modal-header" :style="{ background: color }">
            <div class="tgc-modal-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
              </svg>
            </div>
            <span class="tgc-modal-title">{{ t('connect_modal_title') }}</span>
            <button class="tgc-close" @click="closeModal" :aria-label="t('done')" type="button">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="tgc-modal-body">

            <!-- Error state -->
            <div v-if="store.status === 'error'" class="tgc-state tgc-state--error">
              <div class="tgc-state-icon">⚠️</div>
              <p>{{ store.errorMsg }}</p>
              <button class="tgc-action-btn" :style="{ background: color }" @click="start" type="button">{{ t('try_again') }}</button>
            </div>

            <!-- Expired state -->
            <div v-else-if="store.status === 'expired'" class="tgc-state tgc-state--expired">
              <div class="tgc-state-icon">⏰</div>
              <p>{{ t('code_expired') }}</p>
              <button class="tgc-action-btn" :style="{ background: color }" @click="start" type="button">{{ t('get_new_code') }}</button>
            </div>

            <!-- Connected state -->
            <div v-else-if="store.status === 'connected'" class="tgc-state tgc-state--connected">
              <div class="tgc-state-icon tgc-bounce">✅</div>
              <p class="tgc-success-text">{{ t('connected_success') }}</p>
              <p class="tgc-success-sub">{{ t('connected_sub') }}</p>
              <button class="tgc-action-btn" :style="{ background: color }" @click="closeModal" type="button">{{ t('done') }}</button>
            </div>

            <!-- Waiting state -->
            <div v-else class="tgc-waiting">
              <ol class="tgc-steps">
                <li class="tgc-step">
                  <span class="tgc-step-num" :style="{ background: color }">1</span>
                  <div class="tgc-step-body">
                    <strong>{{ t('open_the_bot') }}</strong>
                    <a
                      v-if="store.botUsername"
                      :href="`https://t.me/${store.botUsername}?text=/connect ${store.token}`"
                      target="popup"
                      onclick="window.open(this.href,'popup','width=600,height=400,scrollbars=yes,resizable=yes'); return false;"
                      rel="noopener noreferrer"
                      class="tgc-bot-link"
                      :style="{ color }"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle; margin-right:3px;">
                        <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
                      </svg>
                      @{{ store.botUsername }}
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle; margin-left:2px; opacity:.7;">
                        <path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                      </svg>
                    </a>
                    <span v-else class="tgc-bot-loading">
                      <span class="tgc-spinner" style="width:11px;height:11px;"></span> {{ t('bot_loading') }}
                    </span>
                  </div>
                </li>

                <li class="tgc-step">
                  <span class="tgc-step-num" :style="{ background: color }">2</span>
                  <div class="tgc-step-body">
                    <strong>{{ t('send_this_command') }}</strong>
                    <div class="tgc-code-block">
                      <code>/connect {{ store.token }}</code>
                      <button
                        class="tgc-copy-btn"
                        :class="{ copied }"
                        :title="copied ? t('copied') : t('copy')"
                        @click="copyCode"
                        type="button"
                      >
                        <svg v-if="!copied" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/>
                        </svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </li>

                <li class="tgc-step">
                  <span class="tgc-step-num" :style="{ background: color }">3</span>
                  <div class="tgc-step-body">
                    <strong>{{ t('wait_for_confirmation') }}</strong>
                    <span class="tgc-waiting-label">
                      <span class="tgc-spinner"></span> {{ t('waiting') }}
                      <span class="tgc-countdown">({{ formatExpiry }})</span>
                    </span>
                    <button
                      class="tgc-check-btn"
                      :style="{ color, borderColor: color }"
                      :disabled="checking"
                      @click="checkNow"
                      type="button"
                    >
                      <span v-if="checking"><span class="tgc-spinner" style="width:10px;height:10px;border-top-color:currentColor;border-color:rgba(0,0,0,.15)"></span></span>
                      <span v-else>↻</span>
                      {{ checking ? t('checking') : t('check_now') }}
                    </button>
                  </div>
                </li>
              </ol>
            </div>

          </div>

        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useConnectStore } from '@/stores/connect.js';
import { useTranslations } from '@/i18n/index.js';

const props = defineProps({
  label:       { type: String,  default: 'Connect to Telegram' },
  color:       { type: String,  default: '#1e88e5' },
  botUsername: { type: String,  default: '' },
  inputName:   { type: String,  default: 'telegram_chat_id' },
  required:    { type: Boolean, default: false },
});

const emit = defineEmits(['connected']);

const store           = useConnectStore();
const { t }           = useTranslations();
const loading         = ref(false);
const copied          = ref(false);
const checking        = ref(false);
const validationInput = ref(null);
let   copyTimer       = null;

const isRequired = computed(() => props.required);

// Non-empty only when connected — used for both the hidden input and validation
const connectedValue = computed(() => {
  const s = store.status;
  if (s === 'already_connected' || s === 'connected') {
    return store.chatId || store.getStoredChatId() || 'connected';
  }
  return '';
});

// Keep native validity in sync with connection state
watch(connectedValue, (val) => {
  nextTick(() => {
    if (!validationInput.value) return;
    validationInput.value.setCustomValidity(
      val ? '' : t('please_connect_validation')
    );
  });
}, { immediate: true });

function onValidationFail() {
  if (store.status === 'idle' || store.status === 'error') {
    start();
  }
}

// Pre-fill botUsername from prop so the link shows immediately (before API call)
if (props.botUsername && !store.botUsername) {
  store.botUsername = props.botUsername;
}

// Restore connected state from localStorage on page load
onMounted(() => {
  if (store.status !== 'idle') return;

  const stored = store.getStoredChatId();
  if (stored) {
    store.setAlreadyConnected(stored);
  }
});

async function start() {
  loading.value = true;
  await store.generate();
  loading.value = false;
}

function closeModal() {
  if (store.status === 'connected') {
    emit('connected', { chatId: store.chatId });
    store.setAlreadyConnected(store.chatId);
  } else {
    store.reset();
  }
}

function maybeClose() {
  if (['connected', 'expired', 'error'].includes(store.status)) {
    closeModal();
  }
}

async function checkNow() {
  if (checking.value) return;
  checking.value = true;
  await store.pollStatus();
  checking.value = false;
}

async function copyCode() {
  if (!store.token) return;
  try {
    await navigator.clipboard.writeText(`/connect ${store.token}`);
  } catch {
    const el = document.createElement('textarea');
    el.value = `/connect ${store.token}`;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
  }
  copied.value = true;
  clearTimeout(copyTimer);
  copyTimer = setTimeout(() => { copied.value = false; }, 2000);
}

const formatExpiry = computed(() => {
  const s = store.expiresIn;
  const m = Math.floor(s / 60);
  const sec = s % 60;
  return `${m}:${String(sec).padStart(2, '0')}`;
});

onUnmounted(() => {
  store.stopPolling();
  clearTimeout(copyTimer);
});
</script>

<style scoped>
/* Visually hidden but participates in native form validation */
.tgc-validation-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
  margin: 0;
  padding: 0;
  border: none;
}

.tgc-root { display: inline-block; }

/* ── Already connected badge ── */
.tgc-already {
  display: inline-flex; align-items: center; gap: .5rem;
  border: 1.5px solid;
  border-radius: 8px;
  padding: .5rem .9rem;
  font-size: .88rem;
  background: #f0fdf4;
}
.tgc-already-icon { display: flex; align-items: center; flex-shrink: 0; }
.tgc-already-text { font-weight: 600; color: #166534; }
.tgc-reconnect-btn {
  background: transparent; border: none;
  font-size: .75rem; color: #6b7280;
  cursor: pointer; padding: 0; font-family: inherit;
  text-decoration: underline; text-underline-offset: 2px;
  transition: color .15s;
}
.tgc-reconnect-btn:hover { color: #374151; }

/* ── Trigger button ── */
.tgc-btn {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .6rem 1.25rem;
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: .9rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: opacity .2s, transform .15s;
  box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
.tgc-btn:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
.tgc-btn:disabled { opacity: .6; cursor: not-allowed; }
.tgc-btn-icon { display: flex; align-items: center; }

/* ── Backdrop ── */
.tgc-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.55);
  display: flex; align-items: center; justify-content: center;
  z-index: 99999;
  padding: 1rem;
}

.tgc-fade-enter-active, .tgc-fade-leave-active { transition: opacity .2s ease; }
.tgc-fade-enter-from,  .tgc-fade-leave-to      { opacity: 0; }

/* ── Modal ── */
.tgc-modal {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 16px 48px rgba(0,0,0,.22);
  max-width: 400px;
  width: 100%;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.tgc-modal-header {
  display: flex; align-items: center; gap: .75rem;
  padding: 1rem 1.2rem;
  color: #fff;
}
.tgc-modal-icon { display: flex; align-items: center; flex-shrink: 0; }
.tgc-modal-title { flex: 1; font-weight: 700; font-size: 1rem; }
.tgc-close {
  background: transparent; border: none; color: rgba(255,255,255,.8);
  cursor: pointer; padding: .25rem; border-radius: 6px;
  display: flex; align-items: center;
  transition: color .15s;
}
.tgc-close:hover { color: #fff; }

.tgc-modal-body { padding: 1.5rem 1.4rem; }

/* ── States ── */
.tgc-state {
  text-align: center;
  display: flex; flex-direction: column; align-items: center; gap: .8rem;
}
.tgc-state-icon { font-size: 2.5rem; line-height: 1; }
.tgc-state p    { color: #444; font-size: .9rem; margin: 0; }

.tgc-success-text { font-size: 1.1rem; font-weight: 700; color: #2e7d32; }
.tgc-success-sub  { font-size: .85rem; color: #666; }

.tgc-bounce { animation: bounce .6s ease infinite alternate; }
@keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-8px); } }

/* ── Steps ── */
.tgc-steps {
  list-style: none; margin: 0; padding: 0;
  display: flex; flex-direction: column; gap: 1.2rem;
}
.tgc-step {
  display: flex; align-items: flex-start; gap: .85rem;
}
.tgc-step-num {
  width: 26px; height: 26px; border-radius: 50%;
  color: #fff; font-size: .8rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: .1rem;
}
.tgc-step-body {
  display: flex; flex-direction: column; gap: .35rem;
  font-size: .88rem; color: #333;
}
.tgc-step-body strong { font-weight: 600; color: #111; }

.tgc-bot-link {
  display: inline-flex;
  align-items: center;
  font-weight: 600;
  text-decoration: none;
  padding: .3rem .7rem;
  border-radius: 6px;
  border: 1.5px solid currentColor;
  font-size: .83rem;
  width: fit-content;
  transition: background .15s, opacity .15s;
}
.tgc-bot-link:hover { opacity: .85; text-decoration: none; background: rgba(0,0,0,.04); }

.tgc-bot-loading {
  display: inline-flex; align-items: center; gap: .4rem;
  font-size: .82rem; color: #999;
}

/* ── Code block ── */
.tgc-code-block {
  display: flex; align-items: center;
  background: #f0f4f8;
  border: 1px solid #dde3ea;
  border-radius: 8px;
  padding: .5rem .75rem;
  gap: .6rem;
  width: fit-content;
}
.tgc-code-block code {
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: .9rem; color: #1a1a1a; letter-spacing: .03em;
  user-select: all;
}
.tgc-copy-btn {
  background: transparent; border: none; cursor: pointer;
  color: #777; padding: .1rem; display: flex; align-items: center;
  border-radius: 4px; transition: color .15s;
  flex-shrink: 0;
}
.tgc-copy-btn:hover { color: #333; }
.tgc-copy-btn.copied { color: #2e7d32; }

/* ── Waiting label ── */
.tgc-waiting-label {
  display: flex; align-items: center; gap: .4rem;
  color: #666; font-size: .85rem;
}
.tgc-countdown { color: #999; font-size: .78rem; }

/* ── Spinner ── */
.tgc-spinner {
  width: 14px; height: 14px; border-radius: 50%;
  border: 2px solid #ccc; border-top-color: #555;
  display: inline-block;
  animation: spin .8s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Check-now button ── */
.tgc-check-btn {
  display: inline-flex; align-items: center; gap: .35rem;
  background: transparent;
  border: 1.5px solid currentColor;
  border-radius: 6px;
  font-size: .78rem; font-weight: 600;
  cursor: pointer; font-family: inherit;
  padding: .28rem .7rem;
  width: fit-content;
  transition: opacity .15s;
  margin-top: .2rem;
}
.tgc-check-btn:hover:not(:disabled) { opacity: .75; }
.tgc-check-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Button gets a red outline when required but not yet connected */
.tgc-btn--required-invalid {
  outline: 2px solid #ef4444;
  outline-offset: 2px;
}

/* ── Action button ── */
.tgc-action-btn {
  padding: .55rem 1.5rem; border: none; border-radius: 8px;
  color: #fff; font-size: .9rem; font-weight: 600;
  cursor: pointer; font-family: inherit;
  transition: opacity .2s;
}
.tgc-action-btn:hover { opacity: .88; }
</style>
