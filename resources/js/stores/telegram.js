import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

// Configure axios with CSRF
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.querySelector('meta[name="csrf-token"]')?.content;
if (token) axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

export const useTelegramStore = defineStore('telegram', () => {
    // ── State ─────────────────────────────────────────────────────────────────
    const chats       = ref([]);       // array of chat objects
    const messages    = ref({});       // { [chatId]: Message[] }
    const botInfo     = ref(null);
    const syncing     = ref(false);
    const loadingMsgs = ref(false);
    const error       = ref(null);

    let _pollTimer         = null;
    let _emptyCount        = 0;        // consecutive syncs with 0 new messages
    let _activeChatIdRef   = null;     // reactive ref passed from App.vue

    // Backoff ladder (ms): 3s → 6s → 12s → 20s cap
    const BACKOFF = [3000, 6000, 12000, 20000];
    const _interval = () => BACKOFF[Math.min(_emptyCount, BACKOFF.length - 1)];

    // ── Getters ───────────────────────────────────────────────────────────────
    const sortedChats = computed(() =>
        [...chats.value].sort((a, b) => {
            if (!a.last_message_at) return 1;
            if (!b.last_message_at) return -1;
            return b.last_message_at_raw - a.last_message_at_raw;
        })
    );

    const totalUnread = computed(() =>
        chats.value.reduce((sum, c) => sum + (c.unread_count || 0), 0)
    );

    // ── Actions ───────────────────────────────────────────────────────────────

    async function fetchBotInfo() {
        try {
            const { data } = await axios.get('/api/telegram/me');
            botInfo.value = data.result ?? null;
        } catch { /* non-critical */ }
    }

    async function fetchChats() {
        try {
            const { data } = await axios.get('/api/telegram/chats');
            chats.value = data.chats ?? [];
        } catch (e) {
            error.value = e.message;
        }
    }

    async function fetchMessages(chatId) {
        loadingMsgs.value = true;
        try {
            const { data } = await axios.get(`/api/telegram/chats/${chatId}/messages`);
            messages.value = { ...messages.value, [chatId]: data.messages ?? [] };
            await markRead(chatId);
        } catch (e) {
            error.value = e.message;
        } finally {
            loadingMsgs.value = false;
        }
    }

    async function pollNewMessages(chatId) {
        const current  = messages.value[chatId] ?? [];
        const lastId   = current.length ? current[current.length - 1].id : 0;

        try {
            const { data } = await axios.get(
                `/api/telegram/chats/${chatId}/messages?after=${lastId}`
            );
            if (data.messages?.length) {
                messages.value = {
                    ...messages.value,
                    [chatId]: [...current, ...data.messages],
                };
                await markRead(chatId);
            }
        } catch { /* silent */ }
    }

    async function syncUpdates(activeChatId = null) {
        if (syncing.value) return;
        syncing.value = true;
        try {
            const { data } = await axios.post('/api/telegram/sync');
            const hasNew = data.new_messages > 0;

            if (hasNew || chats.value.length === 0) {
                _emptyCount = 0; // reset backoff on activity
                await fetchChats();
            } else {
                _emptyCount++; // back off gradually when idle
            }

            // Always poll the open chat — widget chats never appear in Telegram sync,
            // and new messages for the active chat arrive regardless of global new_messages
            if (activeChatId) {
                await pollNewMessages(activeChatId);
                // fetchChats may have restored the unread count for the active chat;
                // re-zero it locally since the user is currently viewing it
                const idx = chats.value.findIndex(c => c.chat_id === activeChatId);
                if (idx !== -1) {
                    const updated = [...chats.value];
                    updated[idx] = { ...updated[idx], unread_count: 0 };
                    chats.value = updated;
                }
            }
        } catch { /* silent */ } finally {
            syncing.value = false;
        }
    }

    async function sendMessage(chatId, text) {
        // Optimistic bubble
        const tmp = {
            id:        'tmp-' + Date.now(),
            direction: 'out',
            from_name: 'Support',
            text,
            sent_at:   new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
            sent_date: new Date().toISOString().slice(0, 10),
            pending:   true,
        };
        const list = messages.value[chatId] ?? [];
        messages.value = { ...messages.value, [chatId]: [...list, tmp] };

        try {
            const { data } = await axios.post(
                `/api/telegram/chats/${chatId}/send`,
                { message: text }
            );
            // Replace optimistic with confirmed
            messages.value = {
                ...messages.value,
                [chatId]: (messages.value[chatId] ?? []).map(m =>
                    m.id === tmp.id ? { ...data.message, pending: false } : m
                ),
            };
            // Refresh chat list preview
            await fetchChats();
        } catch (e) {
            // Mark optimistic as failed
            messages.value = {
                ...messages.value,
                [chatId]: (messages.value[chatId] ?? []).map(m =>
                    m.id === tmp.id ? { ...m, failed: true, pending: false } : m
                ),
            };
            throw e;
        }
    }

    async function markRead(chatId) {
        // Update local state immediately
        const idx = chats.value.findIndex(c => c.chat_id === chatId);
        if (idx !== -1) {
            const updated = [...chats.value];
            updated[idx] = { ...updated[idx], unread_count: 0 };
            chats.value = updated;
        }
        // Persist to backend so polling doesn't resurrect the unread count
        try {
            await axios.post(`/api/telegram/chats/${chatId}/read`);
        } catch { /* silent */ }
    }

    function startPolling(activeChatIdRef) {
        stopPolling();
        _activeChatIdRef = activeChatIdRef;
        _emptyCount = 0;
        _scheduleNextPoll();
        document.addEventListener('visibilitychange', _onVisibilityChange);
    }

    // Use setTimeout (not setInterval) so the delay can change between calls
    function _scheduleNextPoll() {
        if (_pollTimer) clearTimeout(_pollTimer);
        _pollTimer = setTimeout(async () => {
            await syncUpdates(_activeChatIdRef?.value ?? null);
            // Only reschedule if tab is visible; _onVisibilityChange handles resume
            if (!document.hidden) _scheduleNextPoll();
        }, _interval());
    }

    // Pause when tab is hidden; resume (with backoff reset) when visible again
    function _onVisibilityChange() {
        if (document.hidden) {
            if (_pollTimer) { clearTimeout(_pollTimer); _pollTimer = null; }
        } else {
            _emptyCount = 0; // treat return to tab as fresh activity
            _scheduleNextPoll();
        }
    }

    function stopPolling() {
        if (_pollTimer) { clearTimeout(_pollTimer); _pollTimer = null; }
        document.removeEventListener('visibilitychange', _onVisibilityChange);
        _activeChatIdRef = null;
    }

    return {
        // state
        chats, messages, botInfo, syncing, loadingMsgs, error,
        // getters
        sortedChats, totalUnread,
        // actions
        fetchBotInfo, fetchChats, fetchMessages, pollNewMessages,
        syncUpdates, sendMessage, markRead, startPolling, stopPolling,
    };
});
