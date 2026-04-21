<template>
  <div class="empty-state">
    <div class="icon">✈️</div>
    <h3>{{ t('support_inbox') }}</h3>
    <p v-if="store.chats.length === 0">
      {{ t('no_conversations') }}<br>
      {{ t('no_conversations_hint') }}
    </p>
    <p v-else>{{ t('select_conversation') }}</p>

    <button class="sync-btn" :class="{ spinning: store.syncing }" @click="store.syncUpdates()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
        <path d="M3 3v5h5"/>
      </svg>
      {{ store.syncing ? t('syncing') : t('sync_now') }}
    </button>
  </div>
</template>

<script setup>
import { useTelegramStore } from '@/stores/telegram.js';
import { useTranslations } from '@/i18n/index.js';

const store = useTelegramStore();
const { t } = useTranslations();
</script>

<style scoped>
.empty-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: rgba(255,255,255,.35);
  text-align: center;
  padding: 2rem;
}

.icon { font-size: 4.5rem; opacity: .25; }

h3 { font-size: 1.2rem; color: rgba(255,255,255,.45); font-weight: 600; }

p { font-size: .88rem; max-width: 280px; line-height: 1.6; }

.sync-btn {
  display: flex; align-items: center; gap: .4rem;
  background: transparent;
  border: 1px solid #2f3f4f;
  color: #8096a7;
  padding: .5rem 1.2rem; border-radius: 8px;
  font-size: .82rem; cursor: pointer; transition: all .2s;
  margin-top: .5rem;
}
.sync-btn:hover { border-color: var(--accent); color: var(--accent); }
.sync-btn.spinning svg { animation: spin .8s linear infinite; }
</style>
