<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useActivitiesStore } from '../stores/activities.js'
import ActivityForm from '../components/ActivityForm.vue'
import AsyncState from '../components/AsyncState.vue'
import AppModal from '../components/AppModal.vue'
import { formatDateTime } from '../utils/format.js'
const auth=useAuthStore(); const activities=useActivitiesStore(); const showForm=ref(false)
const route=useRoute(),router=useRouter();let filterTimer
const typeLabels={prime:'Основной прайм'}
function status(item){if(item.earnings_count>0)return 'Рассчитана';return 'Черновик'}
const hasFilters=computed(()=>Object.values(activities.filters).some(Boolean))
function apply(){router.replace({query:Object.fromEntries(Object.entries(activities.filters).filter(([,value])=>value))})}
function reset(){Object.keys(activities.filters).forEach(key=>activities.filters[key]='');apply()}
watch(()=>[activities.filters.definition_id,activities.filters.date_from,activities.filters.date_to],()=>{clearTimeout(filterTimer);filterTimer=setTimeout(apply,250)})
watch(()=>route.query,()=>{Object.keys(activities.filters).forEach(key=>activities.filters[key]=String(route.query[key]??''));activities.fetchActivities()},{deep:true})
onMounted(()=>{Object.keys(activities.filters).forEach(key=>activities.filters[key]=String(route.query[key]??''));return Promise.all([activities.fetchDefinitions(),activities.fetchActivities()])})
</script>
<template><section><div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ЖУРНАЛ</p><h1>Активности</h1></div><button v-if="auth.canManage" class="primary" @click="showForm=true">Создать событие</button></div>
  <div class="toolbar activity-toolbar filter-toolbar"><label><span>Событие</span><select v-model="activities.filters.definition_id"><option value="">Все события</option><option v-for="definition in activities.definitions.filter(item => item.is_active)" :key="definition.id" :value="definition.id">{{ definition.name }}</option></select></label><label><span>С даты</span><input v-model="activities.filters.date_from" type="date"></label><label><span>По дату</span><input v-model="activities.filters.date_to" type="date"></label><button v-if="hasFilters" class="filter-reset" @click="reset">Сбросить</button><span class="filter-result">{{ activities.loading?'Обновляем…':`${activities.items.length} событий` }}</span></div>
  <div v-if="hasFilters" class="active-filters"><span v-if="activities.filters.definition_id">Событие выбрано<button aria-label="Убрать событие" @click="activities.filters.definition_id=''">×</button></span><span v-if="activities.filters.date_from">С: <b>{{ activities.filters.date_from }}</b><button aria-label="Убрать начальную дату" @click="activities.filters.date_from=''">×</button></span><span v-if="activities.filters.date_to">По: <b>{{ activities.filters.date_to }}</b><button aria-label="Убрать конечную дату" @click="activities.filters.date_to=''">×</button></span></div>
  <AsyncState :loading="activities.loading&&!activities.items.length" :error="activities.error" :empty="!activities.items.length" loading-text="Загружаем журнал активностей…" :empty-title="hasFilters?'По фильтрам ничего не найдено':'Событий пока нет'" :empty-text="hasFilters?'Сбросьте или измените активные фильтры.':'Созданные праймы появятся в этом журнале.'" @retry="activities.fetchActivities"><template #action><button v-if="auth.canManage&&!hasFilters" class="primary" type="button" @click="showForm=true">Создать первое событие</button><button v-else-if="hasFilters" type="button" @click="reset">Сбросить фильтры</button></template></AsyncState>
  <div v-if="!activities.error&&activities.items.length" :class="['table-wrap',{updating:activities.loading}]" :aria-busy="activities.loading"><table><thead><tr><th>Дата</th><th>Название</th><th>Тип</th><th>Игроков</th><th>Статус</th></tr></thead><tbody><tr v-for="item in activities.items" :key="item.id"><td>{{ formatDateTime(item.occurred_at) }}</td><td><RouterLink class="activity-table-link" :to="`/activities/${item.id}`"><img v-if="item.definition.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else>{{ item.definition.name.slice(0,1) }}</span><strong>{{ item.definition.name }}</strong></RouterLink></td><td><span :class="['event-type',item.definition.type]">{{ typeLabels[item.definition.type] }}</span></td><td>{{ item.players_count }}</td><td><span :class="['import-status',status(item)==='Черновик'?'draft':'confirmed']">{{ status(item) }}</span></td></tr></tbody></table></div>
  <AppModal :open="showForm" title="Новое событие" @close="showForm=false"><ActivityForm @created="activities.fetchActivities();showForm=false" @cancel="showForm=false" /></AppModal>
</section></template>
