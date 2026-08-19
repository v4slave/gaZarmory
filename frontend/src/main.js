import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import { useAuthStore } from './stores/auth.js'
import '@fontsource/jetbrains-mono/cyrillic-400.css'
import '@fontsource/jetbrains-mono/cyrillic-500.css'
import '@fontsource/jetbrains-mono/cyrillic-600.css'
import '@fontsource/jetbrains-mono/cyrillic-700.css'
import '@fontsource/jetbrains-mono/cyrillic-800.css'
import '@fontsource/jetbrains-mono/latin-400.css'
import '@fontsource/jetbrains-mono/latin-500.css'
import '@fontsource/jetbrains-mono/latin-600.css'
import '@fontsource/jetbrains-mono/latin-700.css'
import '@fontsource/jetbrains-mono/latin-800.css'
import './style.css'
import './admin.css'

const pinia = createPinia()
const app = createApp(App)
app.use(pinia)

const auth = useAuthStore(pinia)
router.beforeEach(async to => {
  if (auth.loading) await auth.fetchMe()
  const allowedRoles = to.meta.roles
  if (allowedRoles?.length && auth.authenticated) {
    const roles = auth.user?.roles ?? [auth.user?.role]
    if (!roles.some(role => allowedRoles.includes(role))) return { name: 'forbidden' }
  }
  document.title = `${to.meta.title ?? 'GAZ ARMORY'} · GAZ ARMORY`
})

app.use(router).mount('#app')
