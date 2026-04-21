import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import './stores/telegram.js'; // ensure axios config runs early

const el = document.getElementById('telegram-support-app');
if (el) {
    const app = createApp(App);
    app.use(createPinia());
    app.mount(el);
}
