<template>
  <div
    class="bubble-row"
    :class="msg.direction"
  >
    <div class="bubble" :class="{ pending: msg.pending, failed: msg.failed }">
      <div v-if="msg.direction === 'in' && msg.from_name" class="bubble-sender">
        {{ msg.from_name }}
      </div>
      <div class="bubble-text" v-html="formattedText"></div>
      <div class="bubble-meta">
        <svg v-if="msg.direction === 'out' && !msg.failed"
          width="13" height="13" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2.5" style="opacity:.7">
          <path d="m5 12 5 5L20 7"/>
        </svg>
        <span v-if="msg.failed" style="color:#e53935;font-size:.7rem">Failed</span>
        <span v-else-if="msg.pending" style="opacity:.5">…</span>
        <span>{{ msg.sent_at }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  msg: { type: Object, required: true },
});

// Basic HTML-escape; preserve newlines as <br>
const formattedText = computed(() =>
  (props.msg.text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\n/g, '<br>')
);
</script>

<style scoped>
.bubble-row {
  display: flex;
  margin-bottom: .15rem;
}
.bubble-row.out { justify-content: flex-end; }
.bubble-row.in  { justify-content: flex-start; }

.bubble {
  max-width: 65%;
  padding: .55rem .85rem .45rem;
  border-radius: 14px;
  font-size: .9rem; line-height: 1.45;
  color: var(--bubble-text);
  word-break: break-word;
}

.bubble-row.out .bubble {
  background: var(--bubble-out);
  border-bottom-right-radius: 4px;
  opacity: 1; transition: opacity .2s;
}
.bubble-row.out .bubble.pending { opacity: .65; }
.bubble-row.out .bubble.failed  { background: #5c1a1a; }

.bubble-row.in .bubble {
  background: var(--bubble-in);
  border-bottom-left-radius: 4px;
}

.bubble-sender {
  font-size: .75rem; font-weight: 600;
  color: var(--accent); margin-bottom: .2rem;
}

.bubble-text { white-space: pre-wrap; }

.bubble-meta {
  font-size: .68rem; color: var(--bubble-sub);
  margin-top: .25rem;
  display: flex; align-items: center; gap: .3rem;
  justify-content: flex-end;
}
.bubble-row.in .bubble-meta { justify-content: flex-start; }
</style>
