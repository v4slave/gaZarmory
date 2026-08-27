<script setup>
import { useNotificationsStore } from '../stores/notifications.js'
import AppIcon from './AppIcon.vue'

const notifications = useNotificationsStore()
</script>

<template>
  <div class="toast-stack" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast">
      <article v-for="item in notifications.items" :key="item.id" :class="['toast-card', item.type]" role="status">
        <span class="toast-symbol" aria-hidden="true"><AppIcon :name="item.type === 'success' ? 'check' : item.type === 'error' ? 'error' : item.type === 'warning' ? 'warning' : 'info'" /></span>
        <p>{{ item.message }}</p>
        <button type="button" aria-label="Закрыть уведомление" @click="notifications.dismiss(item.id)"><AppIcon name="close" :size="16" /></button>
      </article>
    </TransitionGroup>
  </div>
</template>
