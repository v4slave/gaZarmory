<script setup>
import { onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useActivitiesStore } from '../stores/activities.js'
import ActivityForm from '../components/ActivityForm.vue'
const auth=useAuthStore(); const activities=useActivitiesStore(); const showForm=ref(false)
const typeLabels={prime:'Основной прайм'}
function status(item){if(item.earnings_count>0)return 'Рассчитана';return 'Черновик'}
watch(()=>[activities.filters.definition_id,activities.filters.date_from,activities.filters.date_to],activities.fetchActivities)
onMounted(()=>Promise.all([activities.fetchDefinitions(),activities.fetchActivities()]))
</script>
<template><section><div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ЖУРНАЛ</p><h1>Активности</h1><p class="muted">Основные праймы гильдии</p></div><button v-if="auth.canManage" class="primary" @click="showForm=true">Создать событие</button></div>
  <div class="toolbar activity-toolbar"><label><span>Событие</span><select v-model="activities.filters.definition_id"><option value="">Все события</option><option v-for="definition in activities.definitions.filter(item => item.is_active)" :key="definition.id" :value="definition.id">{{ definition.name }}</option></select></label><label><span>С даты</span><input v-model="activities.filters.date_from" type="date"></label><label><span>По дату</span><input v-model="activities.filters.date_to" type="date"></label></div>
  <p v-if="activities.error" class="notice error">{{ activities.error }}</p>
  <div class="table-wrap"><table><thead><tr><th>Дата</th><th>Название</th><th>Тип</th><th>Игроков</th><th>Статус</th></tr></thead><tbody><tr v-if="activities.loading"><td colspan="5" class="empty">Загрузка…</td></tr><tr v-else-if="!activities.items.length"><td colspan="5" class="empty">Событий пока нет</td></tr><tr v-for="item in activities.items" :key="item.id"><td>{{ new Date(item.occurred_at).toLocaleString('ru-RU') }}</td><td><RouterLink class="activity-table-link" :to="`/activities/${item.id}`"><img v-if="item.definition.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else>{{ item.definition.name.slice(0,1) }}</span><strong>{{ item.definition.name }}</strong></RouterLink></td><td><span :class="['event-type',item.definition.type]">{{ typeLabels[item.definition.type] }}</span></td><td>{{ item.players_count }}</td><td><span :class="['import-status',status(item)==='Черновик'?'draft':'confirmed']">{{ status(item) }}</span></td></tr></tbody></table></div>
  <div v-if="showForm" class="modal"><ActivityForm @created="activities.fetchActivities()" @cancel="showForm=false" /></div>
</section></template>
