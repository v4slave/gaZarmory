<script setup>
const links = [
  ['/admin/users', 'Пользователи и роли'], ['/admin/requests', 'Заявки'],
  ['/admin/activities', 'Активности'], ['/admin/loot', 'Справочник лута'],
  ['/admin/audit', 'Аудит'], ['/admin/economy', 'Настройки экономики'],
  ['/admin/integrations', 'Discord и уведомления'],
]
</script>
<template><nav class="admin-tabs" aria-label="Разделы админки"><RouterLink class="admin-home-link" to="/admin">Обзор</RouterLink><RouterLink v-for="link in links" :key="link[0]" :to="link[0]">{{ link[1] }}</RouterLink></nav></template>
