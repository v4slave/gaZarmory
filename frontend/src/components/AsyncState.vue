<script setup>
import AppIcon from './AppIcon.vue'
import { useLocale } from '../i18n.js'

const { t } = useLocale()

defineProps({
  loading: Boolean,
  error: { type: String, default: '' },
  empty: Boolean,
  loadingText: { type: String, default: 'Загружаем данные…' },
  emptyTitle: { type: String, default: 'Здесь пока ничего нет' },
  emptyText: { type: String, default: '' },
})

defineEmits(['retry'])
</script>

<template>
  <div v-if="loading" class="panel async-state" role="status" aria-live="polite">
    <span class="async-spinner" aria-hidden="true"></span><strong>{{ t(loadingText) }}</strong>
  </div>
  <div v-else-if="error" class="panel async-state async-error" role="alert">
    <AppIcon name="warning" :size="28" /><strong>{{ t('Не удалось загрузить данные') }}</strong><p>{{ t(error) }}</p>
    <button type="button" class="secondary" @click="$emit('retry')">{{ t('Повторить') }}</button>
  </div>
  <div v-else-if="empty" class="panel async-state">
    <AppIcon name="info" :size="28" /><strong>{{ t(emptyTitle) }}</strong><p v-if="emptyText">{{ t(emptyText) }}</p>
  </div>
</template>
