<template>
  <div class="tg-app" :class="{ rtl: isRtl }">
    <ChatSidebar
      :active-chat-id="activeChatId"
      @select-chat="selectChat"
    />
    <main class="tg-main">
      <ChatRoom
        v-if="activeChatId"
        :chat-id="activeChatId"
        :chat="activeChat"
      />
      <EmptyState v-else />
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTelegramStore } from '@/stores/telegram.js';
import { useTranslations } from '@/i18n/index.js';
import ChatSidebar from './components/ChatSidebar.vue';
import ChatRoom    from './components/ChatRoom.vue';
import EmptyState  from './components/EmptyState.vue';

const store       = useTelegramStore();
const { isRtl }   = useTranslations();
const activeChatId = ref(null);

const activeChat = computed(() =>
  store.chats.find(c => c.chat_id === activeChatId.value) ?? null
);

async function selectChat(chatId) {
  activeChatId.value = chatId;
  if (!store.messages[chatId]) {
    await store.fetchMessages(chatId);
  } else {
    await store.markRead(chatId);
  }
}

onMounted(async () => {
  await store.fetchBotInfo();
  await store.fetchChats();
  store.startPolling(activeChatId);
});

onUnmounted(() => {
  store.stopPolling();
});
</script>

<style>
/* ── CSS custom properties (theme) ─────────────────────────────────────── */
:root {
  --app-bg:          #17212b;
  --sidebar-bg:      #1c2733;
  --topbar-bg:       #212d3b;
  --sidebar-border:  #2a3d50;
  --sidebar-text:    #e8e8e8;
  --sidebar-sub:     #7d9aad;
  --chat-bg:         #0d1117;
  --bubble-in-bg:    #1c2733;
  --bubble-out-bg:   #2b5278;
  --bubble-text:     #e8e8e8;
  --bubble-sub:      #7d9aad;
  --input-bg:        #17212b;
  --input-border:    #2a3d50;
  --input-text:      #e8e8e8;
  --send-btn:        #2b5278;
  --send-btn-hover:  #3568a0;
  --accent:          #5eacd3;
  --header-bg:       #17212b;
}

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}
@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

* { box-sizing: border-box; margin: 0; padding: 0; }

.tg-app {
  display: flex;
  width: 100%;
  height: 100%;
  background: var(--app-bg);
  color: var(--sidebar-text);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 14px;
  overflow: hidden;
}

.tg-app.rtl { direction: rtl; }

.tg-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
</style>
