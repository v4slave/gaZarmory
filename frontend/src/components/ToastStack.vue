<script setup>
import { useNotificationsStore } from '../stores/notifications.js'

const notifications = useNotificationsStore()
</script>

<template>
  <div class="toast-stack" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast">
      <article v-for="item in notifications.items" :key="item.id" :class="['toast-card', item.type]" role="status">
        <span class="toast-symbol" aria-hidden="true">{{ item.type === 'success' ? '✓' : item.type === 'error' ? '!' : item.type === 'warning' ? '⚠' : 'i' }}</span>
        <p>{{ item.message }}</p>
        <button type="button" aria-label="Закрыть уведомление" @click="notifications.dismiss(item.id)">×</button>
      </article>
    </TransitionGroup>
  </div>
</template>
