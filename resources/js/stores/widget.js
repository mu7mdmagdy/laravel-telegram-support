import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

// Configure axios CSRF (safe to call multiple times — idempotent)
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const _token = document.querySelector('meta[name="csrf-token"]')?.content;
if (_token) axios.defaults.headers.common['X-CSRF-TOKEN'] = _token;

const SESSION_KEY = 'tg_widget_session_id';

export const useWidgetStore = defineStore('widget', () => {
    // ── State ─────────────────────────────────────────────────────────────────
    const sessionId   = ref(localStorage.getItem(SESSION_KEY) ?? null);
    const messages    = ref([]);          // array of message objects
    const isOpen      = ref(false);       // chat panel visible?
    const unreadCount = ref(0);           // badge count when panel is closed
    const sending     = ref(false);
    const error       = ref(null);
    const ready       = ref(false);       // session confirmed with server

    let _pollTimer       = null;
    let _emptyCount      = 0;
    let _visitorName     = 'Visitor';
    const BACKOFF        = [3000, 6000, 12000, 20000];
    const _interval      = () => BACKOFF[Math.min(_emptyCount, BACKOFF.length - 1)];

    // ── Session ───────────────────────────────────────────────────────────────

    async function initSession(name = 'Visitor') {
        _visitorName = name;
        try {
            const { data } = await axios.post('/api/widget/session', {
                session_id: sessionId.value,
                name,
            });
            sessionId.value = data.session_id;
            localStorage.setItem(SESSION_KEY, data.session_id);
            ready.value = true;
            // Load initial messages
            await fetchMessages();
        } catch (e) {
            error.value = 'Could not connect to support. Please try again.';
        }
    }

    // ── Messages ──────────────────────────────────────────────────────────────

    async function fetchMessages() {
        if (!sessionId.value) return;
        const lastId = messages.value.length
            ? messages.value[messages.value.length - 1].id
            : 0;
        try {
            const { data } = await axios.get(
                `/api/widget/${sessionId.value}/messages?after=${lastId}`
            );
            if (data.messages?.length) {
                _emptyCount = 0;
                // Invert direction for widget display:
                //   DB 'in'  = visitor sent   → widget shows on RIGHT ('out')
                //   DB 'out' = admin replied   → widget shows on LEFT  ('in')
                const normalized = data.messages.map(m => ({
                    ...m,
                    direction: m.direction === 'in' ? 'out' : 'in',
                }));
                const agentReplies = normalized.filter(m => m.direction === 'in');
                // Count new agent replies as unread when panel is closed
                if (!isOpen.value) unreadCount.value += agentReplies.length;
                messages.value = [...messages.value, ...normalized];
            } else {
                _emptyCount++;
            }
        } catch { /* silent */ }
    }

    async function sendMessage(text) {
        if (!sessionId.value || !text.trim()) return;
        sending.value = true;

        // Optimistic bubble
        const tmp = {
            id:        'tmp-' + Date.now(),
            direction: 'out',
            from_name: 'You',
            text,
            sent_at:   new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
            pending:   true,
        };
        messages.value = [...messages.value, tmp];

        try {
            const { data } = await axios.post(
                `/api/widget/${sessionId.value}/send`,
                { message: text, name: _visitorName }
            );
            // Replace optimistic with confirmed (direction 'in' from server = visitor msg)
            // We treat visitor sends as 'out' bubbles in the widget
            const confirmed = { ...data.message, direction: 'out', pending: false };
            messages.value = messages.value.map(m =>
                m.id === tmp.id ? confirmed : m
            );
            _emptyCount = 0; // reset backoff — activity is happening
        } catch (e) {
            messages.value = messages.value.map(m =>
                m.id === tmp.id ? { ...m, failed: true, pending: false } : m
            );
            error.value = 'Failed to send. Please retry.';
            setTimeout(() => { error.value = null; }, 4000);
        } finally {
            sending.value = false;
        }
    }

    // ── Panel ─────────────────────────────────────────────────────────────────

    function openPanel() {
        isOpen.value      = true;
        unreadCount.value = 0;
    }

    function closePanel() {
        isOpen.value = false;
    }

    function togglePanel() {
        if (isOpen.value) closePanel(); else openPanel();
    }

    // ── Polling ───────────────────────────────────────────────────────────────

    function startPolling() {
        stopPolling();
        _emptyCount = 0;
        _scheduleNextPoll();
        document.addEventListener('visibilitychange', _onVisibilityChange);
    }

    function _scheduleNextPoll() {
        if (_pollTimer) clearTimeout(_pollTimer);
        _pollTimer = setTimeout(async () => {
            await fetchMessages();
            if (!document.hidden) _scheduleNextPoll();
        }, _interval());
    }

    function _onVisibilityChange() {
        if (document.hidden) {
            if (_pollTimer) { clearTimeout(_pollTimer); _pollTimer = null; }
        } else {
            _emptyCount = 0;
            _scheduleNextPoll();
        }
    }

    function stopPolling() {
        if (_pollTimer) { clearTimeout(_pollTimer); _pollTimer = null; }
        document.removeEventListener('visibilitychange', _onVisibilityChange);
    }

    return {
        sessionId, messages, isOpen, unreadCount, sending, error, ready,
        initSession, fetchMessages, sendMessage,
        openPanel, closePanel, togglePanel,
        startPolling, stopPolling,
    };
});
