<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage } from '../stores/notifications.js'

const router = useRouter()
const auth = useAuthStore()
const error = ref('')

function decodePayload(value) {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4)
  const bytes = Uint8Array.from(atob(base64), character => character.charCodeAt(0))
  return JSON.parse(new TextDecoder().decode(bytes))
}

onMounted(async () => {
  try {
    if (!auth.authenticated) throw new Error('Сначала войдите в Armory, затем повторите импорт с archa.ge.')
    const encoded = window.location.hash.slice(1)
    if (!encoded) throw new Error('Данные экипировки не переданы.')
    const payload = decodePayload(encoded)
    history.replaceState(null, '', '/gear-import')
    await api.post('/api/me/player/archa-gear-snapshot', payload)
    await router.replace(`/players/${auth.user.player.id}`)
  } catch (exception) {
    error.value = exception.response ? apiErrorMessage(exception, 'Не удалось импортировать экипировку.') : exception.message
  }
})
</script>

<template><section class="panel gear-import-state"><h1>Импорт экипировки</h1><p v-if="!error">Получаем предметы из archa.ge…</p><template v-else><p class="notice error">{{ error }}</p><RouterLink class="secondary" :to="auth.user?.player ? `/players/${auth.user.player.id}` : '/dashboard'">Вернуться в Armory</RouterLink></template></section></template>
