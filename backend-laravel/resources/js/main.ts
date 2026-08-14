import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { initializeAuth } from './composables/useAuth';
import '../css/app.css';

initializeAuth();

createApp(App).use(router).mount('#app');

