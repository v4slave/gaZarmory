<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'

const loading = ref(true), exporting = ref(''), error = ref('')
const data = ref({ summary:{}, period:{}, players:[], events:[], groups:[], inactive:[], timeline:[], options:{groups:[],definitions:[],players:[]} })
const filters = reactive({ period:'30', group_id:'', definition_id:'', player_id:'', inactive_days:30 })
const classLabels = { melee:'Милик', archer:'Лучник', mage:'Маг', healer:'Хил', bard:'Бард', tank:'Танк' }
const maxTimeline = computed(() => Math.max(1, ...data.value.timeline.map(row => row.percentage)))
let timer
const params = () => Object.fromEntries(Object.entries(filters).filter(([,value]) => value !== ''))
async function load() {
  loading.value=true;error.value=''
  try { data.value=(await api.get('/api/attendance-analytics',{params:params()})).data; if(!filters.player_id&&data.value.selected_player_id)filters.player_id=data.value.selected_player_id }
  catch(e){error.value=e.response?.data?.message??'Не удалось загрузить аналитику посещаемости.'}
  finally{loading.value=false}
}
async function download(format){exporting.value=format;try{const response=await api.get('/api/attendance-analytics/export',{params:{...params(),format},responseType:'blob'});const url=URL.createObjectURL(response.data);const anchor=document.createElement('a');anchor.href=url;anchor.download=`attendance-${filters.period}.${format}`;anchor.click();URL.revokeObjectURL(url)}catch{error.value='Не удалось сформировать экспорт.'}finally{exporting.value=''}}
function date(value){return value?new Date(value).toLocaleDateString('ru-RU'):'Никогда'}
function periodLabel(label){if(label.includes('-W')){const [year,week]=label.split('-W');return `${week} нед. ${year}`}return new Date(`${label}T00:00:00`).toLocaleDateString('ru-RU',{day:'2-digit',month:'short'})}
watch(filters,()=>{clearTimeout(timer);timer=setTimeout(load,250)})
onMounted(load)
</script>

<template><section>
  <div class="page-heading attendance-heading"><div><p class="eyebrow">GAZ ARMORY · РУКОВОДИТЕЛЯМ</p><h1>Аналитика посещаемости</h1><p class="muted">Праймы, серии посещений, отчёты по событиям и констам</p></div><div class="attendance-export"><button :disabled="exporting" @click="download('csv')">{{ exporting==='csv'?'Готовим…':'Экспорт CSV' }}</button><button class="primary" :disabled="exporting" @click="download('xlsx')">{{ exporting==='xlsx'?'Готовим…':'Экспорт XLSX' }}</button></div></div>
  <div class="panel attendance-filters"><label>Период<select v-model="filters.period"><option value="7">7 дней</option><option value="30">30 дней</option><option value="90">90 дней</option><option value="all">Всё время</option></select></label><label>Конста<select v-model="filters.group_id"><option value="">Все консты</option><option v-for="group in data.options.groups" :key="group.id" :value="group.id">{{ group.name }}</option></select></label><label>Событие<select v-model="filters.definition_id"><option value="">Все праймы</option><option v-for="definition in data.options.definitions" :key="definition.id" :value="definition.id">{{ definition.name }}</option></select></label><label>Динамика игрока<select v-model="filters.player_id"><option v-for="player in data.options.players" :key="player.id" :value="player.id">{{ player.nickname }}</option></select></label></div>
  <p v-if="error" class="notice error">{{ error }}</p><div v-if="loading" class="panel empty">Собираем статистику…</div>
  <template v-else>
    <div class="attendance-summary"><article><span>Общая посещаемость</span><strong>{{ Number(data.summary.percentage??0).toLocaleString('ru-RU') }}%</strong><small>Посещено {{ data.summary.attended??0 }} из {{ data.summary.available??0 }} доступных праймов</small></article><article><span>Праймов в периоде</span><strong>{{ data.period.total_primes??0 }}</strong><small>только завершённые события</small></article><article><span>Игроков в выборке</span><strong>{{ data.summary.players??0 }}</strong><small>активный состав</small></article></div>

    <div class="panel attendance-timeline"><div class="panel-title"><div><h2>Динамика посещаемости игрока</h2><p class="muted">Доля посещённых праймов по дням или неделям</p></div></div><div v-if="data.timeline.length" class="timeline-bars"><div v-for="point in data.timeline" :key="point.label"><span class="timeline-value">{{ point.percentage }}%</span><span class="timeline-track"><i :style="{height:`${Math.max(3,point.percentage/maxTimeline*100)}%`}"></i></span><small>{{ periodLabel(point.label) }}</small><em>{{ point.attended }}/{{ point.available }}</em></div></div><p v-else class="empty">Для выбранного игрока пока нет доступных праймов.</p></div>

    <div class="panel attendance-players"><div class="panel-title"><div><h2>Игроки</h2><p class="muted">Процент всегда сопровождается фактическим количеством посещений</p></div></div><div class="table-wrap flat"><table><thead><tr><th>Игрок</th><th>Конста</th><th>Посещаемость</th><th>Текущая серия</th><th>Последний прайм</th></tr></thead><tbody><tr v-for="player in data.players" :key="player.id"><td><RouterLink class="player-identity" :to="`/players/${player.id}`"><PlayerAvatar :player="player" size="tiny"/><span class="attendance-player-name"><strong>{{ player.nickname }}</strong><small :class="['class-tag', `class-${player.class}`]">{{ classLabels[player.class] }}</small></span></RouterLink></td><td>{{ player.group_name }}</td><td><strong>{{ player.percentage }}%</strong><small class="attendance-fraction">Посещено {{ player.attended }} из {{ player.available }}</small></td><td><span v-if="player.attendance_streak" class="streak-good">✓ {{ player.attendance_streak }} подряд</span><span v-else-if="player.absence_streak" class="streak-bad">× {{ player.absence_streak }} пропуск.</span><span v-else>—</span></td><td>{{ date(player.last_attended_at) }}</td></tr></tbody></table></div></div>

    <div class="attendance-report-grid"><div class="panel"><div class="panel-title"><div><h2>Статистика событий</h2><p class="muted">В выбранном периоде</p></div></div><div v-if="data.events.length" class="event-report-list"><article v-for="event in data.events" :key="event.id"><span class="profile-activity-icon"><img v-if="event.icon_url" :src="event.icon_url"><i v-else>◆</i></span><strong>{{ event.name }}</strong><span><b>{{ event.total }}</b><small>проведено</small></span><span><b>{{ event.attendances }}</b><small>посещений</small></span><span><b>{{ event.average_participants }}</b><small>сред. состав</small></span></article></div><p v-else class="empty">Событий нет.</p></div><div class="panel"><div class="panel-title"><div><h2>Отчёт по констам</h2><p class="muted">По текущему составу конст</p></div></div><div v-if="data.groups.length" class="group-report-list"><article v-for="group in data.groups" :key="group.name"><strong>{{ group.name }}</strong><span>{{ group.players }} игроков</span><b>{{ group.percentage }}%</b><small>Посещено {{ group.attended }} из {{ group.available }}</small></article></div><p v-else class="empty">Данных по констам нет.</p></div></div>

    <div class="panel inactive-report"><div class="panel-title"><div><h2>Давно неактивные игроки</h2><p class="muted">Не посещали праймы указанное количество дней</p></div><label>Дней<input v-model.number="filters.inactive_days" type="number" min="7" max="3650"></label></div><div v-if="data.inactive.length" class="inactive-list"><RouterLink v-for="player in data.inactive" :key="player.id" :to="`/players/${player.id}`"><PlayerAvatar :player="player" size="tiny"/><strong>{{ player.nickname }}</strong><span>{{ player.group_name }}</span><small>Последнее посещение: {{ date(player.last_attended_at) }}</small></RouterLink></div><p v-else class="empty">Все игроки проявляли активность в заданный срок.</p></div>
  </template>
</section></template>
