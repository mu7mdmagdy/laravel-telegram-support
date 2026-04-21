<template>
  <aside class="sidebar">
    <!-- Header -->
    <div class="sidebar-header">
      <div class="brand">{{ t('support_inbox') }}</div>
      <div class="header-meta" v-if="store.botInfo">
        <span class="bot-tag">🤖 {{ store.botInfo.first_name }}</span>
      </div>
    </div>

    <!-- Search -->
    <div class="sidebar-search">
      <input v-model="search" type="text" :placeholder="t('search_chats')" />
    </div>

    <!-- Sync bar -->
    <div class="sync-bar">
      <button class="sync-btn" :class="{ spinning: store.syncing }" @click="manualSync">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
        {{ store.syncing ? t('syncing') : t('sync') }}
      </button>
      <span v-if="store.totalUnread > 0" class="total-unread">
        {{ t('unread', { count: store.totalUnread }) }}
      </span>
    </div>

    <!-- Chat list -->
    <div class="chat-list">
      <template v-if="filteredChats.length">
      <ChatListItem
          v-for="chat in filteredChats"
          :key="chat.chat_id"
          :chat="chat"
          :active="chat.chat_id === activeChatId"
          @select="$emit('select-chat', $event)"
        />
      </template>
      <div v-else class="chat-list-empty">
        <div>📭</div>
        <div v-if="store.chats.length === 0">
          {{ t('no_conversations') }}<br>
          {{ t('no_conversations_bot_hint') }}
        </div>
        <div v-else>{{ t('no_chats_match', { query: search }) }}</div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useTelegramStore } from '@/stores/telegram.js';
import { useTranslations } from '@/i18n/index.js';
import ChatListItem from './ChatListItem.vue';

const props = defineProps({
  activeChatId: { type: String, default: null },
});
const emit = defineEmits(['select-chat']);

const store  = useTelegramStore();
const search = ref('');
const { t }  = useTranslations();

const filteredChats = computed(() => {
  const q = search.value.toLowerCase();
  return store.sortedChats.filter(c =>
    !q || (c.display_name ?? '').toLowerCase().includes(q)
  );
});

async function manualSync() {
  await store.syncUpdates();
}
</script>

<style scoped>
.sidebar {
  width: 320px; min-width: 260px;
  background: var(--sidebar-bg);
  display: flex; flex-direction: column;
  border-right: 1px solid var(--sidebar-border);
  flex-shrink: 0;
}

.sidebar-header {
  background: var(--topbar-bg);
  padding: .9rem 1.2rem;
  display: flex; align-items: center; gap: .8rem;
  border-bottom: 1px solid var(--sidebar-border);
  min-height: 56px;
}

.brand {
  font-size: 1rem; font-weight: 700; color: var(--sidebar-text);
  text-decoration: none; flex: 1;
}

.header-meta { display: flex; align-items: center; }

.bot-tag {
  font-size: .72rem; color: var(--sidebar-sub);
  background: rgba(255,255,255,.06);
  padding: .2rem .5rem; border-radius: 10px;
}

.sidebar-search {
  padding: .7rem 1rem;
  background: var(--sidebar-bg);
  border-bottom: 1px solid var(--sidebar-border);
}

.sidebar-search input {
  width: 100%;
  background: var(--topbar-bg);
  border: 1px solid var(--sidebar-border);
  border-radius: 20px;
  padding: .45rem 1rem .45rem 2.2rem;
  color: var(--sidebar-text);
  font-size: .88rem; outline: none;
  transition: border-color .2s;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: .8rem center;
}
.sidebar-search input:focus { border-color: var(--accent); }
.sidebar-search input::placeholder { color: var(--sidebar-sub); }

.sync-bar {
  display: flex; align-items: center; gap: .7rem;
  padding: .5rem 1rem;
  border-bottom: 1px solid var(--sidebar-border);
}

.sync-btn {
  display: flex; align-items: center; gap: .35rem;
  background: transparent;
  border: 1px solid var(--input-border);
  color: var(--sidebar-sub);
  padding: .35rem .75rem; border-radius: 8px;
  font-size: .78rem; cursor: pointer; transition: all .2s;
}
.sync-btn:hover { border-color: var(--accent); color: var(--accent); }
.sync-btn.spinning svg { animation: spin .8s linear infinite; }

.total-unread { font-size: .75rem; color: var(--accent); }

.chat-list { flex: 1; overflow-y: auto; }

.chat-list-empty {
  padding: 2rem 1.5rem; text-align: center;
  color: var(--sidebar-sub); font-size: .88rem; line-height: 1.6;
  display: flex; flex-direction: column; gap: .6rem; align-items: center;
}
</style>
