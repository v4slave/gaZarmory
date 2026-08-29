<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'
import { apiErrorMessage } from '../stores/notifications.js'
import { formatDateTime } from '../utils/format.js'
import AdminNav from '../components/AdminNav.vue'
import SkeletonRows from '../components/SkeletonRows.vue'
import CompactPagination from '../components/CompactPagination.vue'

const route = useRoute(), router = useRouter()
const logs = ref([]), actions = ref([]), meta = ref({}), loading = ref(true), error = ref(''), expanded = ref(null)
const filters = ref({ search:String(route.query.search??''), action:String(route.query.action??''), date_from:String(route.query.date_from??''), date_to:String(route.query.date_to??'') })
const hasFilters = computed(() => Object.values(filters.value).some(Boolean))
let timer
async function load(){loading.value=true;error.value='';try{const{data}=await api.get('/api/admin/audit-logs',{params:route.query});logs.value=data.logs.data;meta.value={current_page:data.logs.current_page,last_page:data.logs.last_page,total:data.logs.total};actions.value=data.actions}catch(e){error.value=apiErrorMessage(e,'Не удалось загрузить аудит.')}finally{loading.value=false}}
function apply(page=1){router.replace({query:Object.fromEntries(Object.entries({...filters.value,...(page>1?{page}: {})}).filter(([,value])=>value))})}
function reset(){filters.value={search:'',action:'',date_from:'',date_to:''};apply()}
function remove(key){filters.value[key]=''}
watch(filters,()=>{clearTimeout(timer);timer=setTimeout(()=>apply(),350)},{deep:true})
watch(()=>route.query,()=>{filters.value={search:String(route.query.search??''),action:String(route.query.action??''),date_from:String(route.query.date_from??''),date_to:String(route.query.date_to??'')};load()},{deep:true})
onMounted(load)
function details(log){return JSON.stringify({до:log.old_values,после:log.new_values,ip:log.ip_address},null,2)}
</script>

<template><section><div class="page-heading"><div><p class="eyebrow">АДМИНКА</p><h1>Аудит</h1><p class="muted">Неизменяемый журнал действий пользователей</p></div><span class="freshness">{{ loading?'Обновляем…':`${meta.total??0} записей` }}</span></div><AdminNav/><div class="panel audit-panel"><div class="audit-filters filter-toolbar"><input v-model.trim="filters.search" type="search" placeholder="Действие, сущность или пользователь"><select v-model="filters.action"><option value="">Все действия</option><option v-for="action in actions" :key="action">{{ action }}</option></select><input v-model="filters.date_from" type="date" title="Дата с"><input v-model="filters.date_to" type="date" title="Дата по"><button v-if="hasFilters" class="filter-reset" type="button" @click="reset">Сбросить</button></div><div v-if="hasFilters" class="active-filters" aria-label="Активные фильтры"><span v-if="filters.search">Поиск: <b>{{ filters.search }}</b><button aria-label="Убрать поиск" @click="remove('search')">×</button></span><span v-if="filters.action">Действие: <b>{{ filters.action }}</b><button aria-label="Убрать действие" @click="remove('action')">×</button></span><span v-if="filters.date_from">С: <b>{{ filters.date_from }}</b><button aria-label="Убрать начальную дату" @click="remove('date_from')">×</button></span><span v-if="filters.date_to">По: <b>{{ filters.date_to }}</b><button aria-label="Убрать конечную дату" @click="remove('date_to')">×</button></span></div><p v-if="error" class="notice error">{{ error }} <button @click="load">Повторить</button></p><SkeletonRows v-if="loading&&!logs.length" :rows="8" :columns="5"/><div v-else :class="['table-wrap','mobile-cards',{updating:loading}]" :aria-busy="loading"><table class="audit-table"><thead><tr><th>Дата</th><th>Пользователь</th><th>Действие</th><th>Сущность</th><th></th></tr></thead><tbody><template v-for="log in logs" :key="log.id"><tr><td data-label="Дата">{{ formatDateTime(log.created_at) }}</td><td data-label="Пользователь">{{ log.user?.discord_display_name||log.user?.discord_username||'Система' }}</td><td data-label="Действие"><strong>{{ log.action }}</strong></td><td data-label="Сущность">{{ log.entity_type?.split('\\').pop()??'—' }} #{{ log.entity_id??'—' }}</td><td><button class="audit-toggle" @click="expanded=expanded===log.id?null:log.id">{{ expanded===log.id?'Скрыть':'Детали' }}</button></td></tr><tr v-if="expanded===log.id" class="audit-detail-row"><td colspan="5"><pre>{{ details(log) }}</pre></td></tr></template><tr v-if="!logs.length"><td colspan="5"><div class="empty-state"><strong>{{ hasFilters?'По фильтрам ничего не найдено':'Журнал пока пуст' }}</strong><p>{{ hasFilters?'Сбросьте или измените активные фильтры.':'Действия пользователей появятся здесь.' }}</p><button v-if="hasFilters" @click="reset">Сбросить фильтры</button></div></td></tr></tbody></table></div><CompactPagination :page="meta.current_page??1" :pages="meta.last_page??1" :disabled="loading" label="Страницы аудита" @change="apply"/></div></section></template>
