<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'
import { formatDateTime, formatGold } from '../utils/format.js'
import AdminNav from '../components/AdminNav.vue'
import SkeletonRows from '../components/SkeletonRows.vue'

const notifications = useNotificationsStore()
const data = ref(null)
const tokenUnitValue = ref(1)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = (await api.get('/api/admin/settings')).data
    tokenUnitValue.value = Number(data.value.economy.token_unit_value) || 1
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Не удалось загрузить настройки.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const economy = (await api.patch('/api/admin/settings/economy', {
      token_unit_value: Number(tokenUnitValue.value),
      updated_at: data.value.economy.token_updated_at,
    })).data
    data.value.economy = economy
    tokenUnitValue.value = economy.token_unit_value
    notifications.success('Стоимость жетона обновлена.')
  } catch (requestError) {
    error.value = apiErrorMessage(requestError, 'Не удалось обновить стоимость жетона.')
    notifications.error(error.value)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section>
    <div class="page-heading">
      <div><p class="eyebrow">АДМИНКА</p><h1>Настройки экономики</h1></div>
      <button :disabled="loading || saving" @click="load">Обновить</button>
    </div>
    <AdminNav/>
    <p v-if="error" class="notice error">{{ error }}</p>
    <SkeletonRows v-if="loading" :rows="3"/>
    <template v-else-if="data">
      <div class="settings-grid">
        <article class="panel"><span>Стоимость жетона</span><strong>{{ formatGold(data.economy.token_unit_value) }}</strong><small>Изменено: {{ formatDateTime(data.economy.token_updated_at) }}</small></article>
        <article class="panel"><span>Формула отображения</span><strong>⌊ золото ÷ стоимость жетона ⌋</strong><small>Количество жетонов вычисляется автоматически</small></article>
      </div>
      <form class="panel settings-instruction economy-settings-form" @submit.prevent="save">
        <div><h2>Изменение стоимости</h2><p>Укажите, сколько золота соответствует одному жетону.</p></div>
        <label>Стоимость одного жетона, золото<input v-model.number="tokenUnitValue" type="number" min="1" max="1000000000" step="1" required></label>
        <button class="primary" :disabled="saving || tokenUnitValue < 1">{{ saving ? 'Сохранение…' : 'Сохранить стоимость' }}</button>
        <div class="notice warning">Изменение цены не двигает золото и не создаёт финансовую транзакцию — меняется только эквивалент в жетонах. Уже запущенные аукционы сохраняют зафиксированный при старте курс.</div>
      </form>
    </template>
  </section>
</template>
