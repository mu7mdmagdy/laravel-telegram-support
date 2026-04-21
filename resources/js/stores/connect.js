import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

// Configure axios CSRF (safe to run multiple times — idempotent)
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const _token = document.querySelector('meta[name="csrf-token"]')?.content;
if (_token) axios.defaults.headers.common['X-CSRF-TOKEN'] = _token;

const LS_KEY = 'telegram_connect_chat_id';

export const useConnectStore = defineStore('telegram-connect', () => {
    // ── State ─────────────────────────────────────────────────────────────────
    /**
     * status values:
     *   'idle'              — not started yet
     *   'waiting'           — token generated, waiting for user to send /connect
     *   'connected'         — user has sent /connect and chat_id is confirmed (this session)
     *   'already_connected' — previously connected (server-side or localStorage)
     *   'expired'           — token expired before user connected
     *   'error'             — network / server error
     */
    const status      = ref('idle');
    const token       = ref(null);
    const chatId      = ref(null);
    const botUsername = ref('');
    const errorMsg    = ref('');
    const expiresIn   = ref(600); // seconds remaining (countdown)

    let _pollTimer      = null;
    let _countdownTimer = null;

    // ── localStorage helpers ──────────────────────────────────────────────────

    function _saveToStorage(id) {
        try { localStorage.setItem(LS_KEY, id); } catch { /* private browsing */ }
    }

    function _clearStorage() {
        try { localStorage.removeItem(LS_KEY); } catch {}
    }

    function getStoredChatId() {
        try { return localStorage.getItem(LS_KEY) || null; } catch { return null; }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    async function generate() {
        status.value   = 'idle';
        token.value    = null;
        chatId.value   = null;
        errorMsg.value = '';
        stopPolling();

        try {
            const { data } = await axios.post('/api/telegram/connect/generate');
            token.value       = data.token;
            botUsername.value = data.bot_username ?? '';
            expiresIn.value   = data.expires_in ?? 600;
            status.value      = 'waiting';
            _startCountdown();
            startPolling();
        } catch (e) {
            status.value  = 'error';
            errorMsg.value = e?.response?.data?.message ?? 'Could not generate code. Please try again.';
        }
    }

    async function pollStatus() {
        if (!token.value || status.value !== 'waiting') return;
        try {
            const { data } = await axios.get(`/api/telegram/connect/status?token=${token.value}`);
            if (data.expired) {
                status.value = 'expired';
                stopPolling();
                return;
            }
            if (data.connected) {
                chatId.value = data.chat_id;
                _saveToStorage(data.chat_id); // persist for future page loads
                status.value = 'connected';
                stopPolling();
            }
        } catch { /* silent — keep polling */ }
    }

    function startPolling() {
        stopPolling();
        _schedulePoll();
    }

    function _schedulePoll() {
        _pollTimer = setTimeout(async () => {
            await pollStatus();
            if (status.value === 'waiting') _schedulePoll();
        }, 3000);
    }

    function stopPolling() {
        if (_pollTimer) { clearTimeout(_pollTimer); _pollTimer = null; }
        if (_countdownTimer) { clearInterval(_countdownTimer); _countdownTimer = null; }
    }

    function _startCountdown() {
        if (_countdownTimer) clearInterval(_countdownTimer);
        _countdownTimer = setInterval(() => {
            if (expiresIn.value > 0) {
                expiresIn.value--;
            } else {
                if (status.value === 'waiting') status.value = 'expired';
                stopPolling();
            }
        }, 1000);
    }

    function reset() {
        stopPolling();
        _clearStorage(); // allow reconnect with a different account
        status.value    = 'idle';
        token.value     = null;
        chatId.value    = null;
        errorMsg.value  = '';
        expiresIn.value = 600;
    }

    function setAlreadyConnected(id = null) {
        stopPolling();
        if (id) {
            chatId.value = id;
            _saveToStorage(id);
        }
        status.value = 'already_connected';
    }

    return {
        status, token, chatId, botUsername, errorMsg, expiresIn,
        generate, pollStatus, startPolling, stopPolling, reset,
        setAlreadyConnected, getStoredChatId,
    };
});

