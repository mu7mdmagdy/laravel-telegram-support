import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import TelegramConnect from './components/TelegramConnect.vue';

// Mount on every element with data-telegram-connect attribute.
// This allows multiple connect buttons on the same page (each gets its own Pinia instance).
document.querySelectorAll('[data-telegram-connect]').forEach((el) => {
    const label       = el.dataset.label       ?? 'Connect to Telegram';
    const color       = el.dataset.color       ?? '#1e88e5';
    const botUsername = el.dataset.botUsername ?? '';
    const inputName   = el.dataset.inputName   ?? 'telegram_chat_id';
    const required    = el.dataset.required    === '1';

    const app = createApp({
        render: () => h(TelegramConnect, {
            label,
            color,
            botUsername,
            inputName,
            required,
            onConnected: (payload) => {
                el.dispatchEvent(new CustomEvent('telegram:connected', {
                    bubbles: true,
                    detail:  payload,
                }));
            },
        }),
    });

    app.use(createPinia());
    app.mount(el);
});
