import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ChatWidget from './components/Widget/ChatWidget.vue';

const el = document.getElementById('telegram-widget-app');
if (el) {
    const title = el.dataset.title ?? 'Support Chat';
    const color = el.dataset.color ?? '#1e88e5';
    const name  = el.dataset.name  ?? '';

    const app = createApp(ChatWidget, { title, color, visitorName: name });
    app.use(createPinia());
    app.mount(el);
}
