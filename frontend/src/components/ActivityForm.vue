<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useActivitiesStore } from '../stores/activities.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const emit = defineEmits(['cancel', 'created'])
const router = useRouter(); const activities = useActivitiesStore(); const error = ref(''); const saving = ref(false)
const notifications = useNotificationsStore()
const now = new Date(); now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
const form = reactive({ activity_definition_id: '', occurred_at: now.toISOString().slice(0, 16) })
async function submit() {
  saving.value = true; error.value = ''
  try {
    const payload = { ...form, activity_definition_id: Number(form.activity_definition_id), occurred_at: new Date(form.occurred_at).toISOString() }
    const activity = await activities.createActivity(payload)
    notifications.success('Черновик активности создан.')
    emit('created', activity)
    emit('cancel')
    router.push(`/activities/${activity.id}`).catch(() => {
      error.value = 'Событие создано, но страницу не удалось открыть. Обновите журнал активностей.'
    })
  } catch (e) { error.value = apiErrorMessage(e, 'Не удалось создать событие.'); notifications.error(error.value) }
  finally { saving.value = false }
}
</script>

<template><form class="form-card" @submit.prevent="submit"><h2>Новое событие</h2>
  <label>Событие<select v-model="form.activity_definition_id" required><option disabled value="">Выберите из справочника</option><option v-for="item in activities.definitions.filter(x => x.is_active)" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
  <label>Дата и время<input v-model="form.occurred_at" required type="datetime-local"></label>
  <p v-if="error" class="error notice">{{ error }}</p><div class="form-actions"><button type="button" @click="$emit('cancel')">Отмена</button><button class="primary" :disabled="saving">Создать</button></div>
</form></template>
