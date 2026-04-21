<template>
  <div
    class="chat-item"
    :class="{ active }"
    :data-name="chat.display_name?.toLowerCase()"
    role="button"
    tabindex="0"
    @click="$emit('select', chat.chat_id)"
    @keydown.enter.space.prevent="$emit('select', chat.chat_id)"
  >
    <div class="chat-avatar">{{ chat.avatar_letter }}</div>
    <div class="chat-item-body">
      <div class="chat-item-top">
        <span class="chat-item-name">{{ chat.display_name }}</span>
        <span class="chat-item-time">{{ chat.last_message_at }}</span>
      </div>
      <div class="chat-item-bottom">
        <span class="chat-item-preview">{{ truncate(chat.last_message_text) }}</span>
        <span v-if="chat.unread_count > 0" class="unread-badge">{{ chat.unread_count }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTranslations } from '@/i18n/index.js';

defineProps({
  chat:   { type: Object,  required: true },
  active: { type: Boolean, default: false },
});
defineEmits(['select']);

const { t } = useTranslations();

function truncate(str, len = 45) {
  if (!str) return t('no_messages_yet');
  return str.length > len ? str.slice(0, len) + '…' : str;
}
</script>

<style scoped>
.chat-item {
  display: flex;
  align-items: center;
  gap: .85rem;
  padding: .75rem 1rem;
  cursor: pointer;
  text-decoration: none;
  border-bottom: 1px solid rgba(255,255,255,.03);
  transition: background .15s;
}
.chat-item:hover  { background: var(--sidebar-hover); }
.chat-item.active { background: var(--sidebar-active); }

.chat-avatar {
  width: 46px; height: 46px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), #1565c0);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.15rem; font-weight: 700; color: #fff;
  flex-shrink: 0; text-transform: uppercase;
}

.chat-item-body  { flex: 1; min-width: 0; }

.chat-item-top {
  display: flex; justify-content: space-between; align-items: baseline;
  gap: .5rem; margin-bottom: .2rem;
}

.chat-item-name {
  font-size: .93rem; font-weight: 600; color: var(--sidebar-text);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;
}

.chat-item-time { font-size: .72rem; color: var(--sidebar-sub); white-space: nowrap; }

.chat-item-bottom { display: flex; justify-content: space-between; align-items: center; }

.chat-item-preview {
  font-size: .82rem; color: var(--sidebar-sub);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.unread-badge {
  background: var(--accent); color: #fff;
  font-size: .7rem; font-weight: 700;
  border-radius: 10px; padding: .15rem .45rem;
  min-width: 18px; text-align: center; margin-left: .3rem; flex-shrink: 0;
}
</style>
