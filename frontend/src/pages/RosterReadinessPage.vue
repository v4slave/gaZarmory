<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import AsyncState from '../components/AsyncState.vue'
import { formatInteger } from '../utils/format.js'

const loading = ref(true)
const route = useRoute(), router = useRouter()
const error = ref('')
const data = ref({ players: [], groups: [], summary: {} })
const filters = reactive({ search: '', group_id: '', class: '', min_gear_score: '', max_gear_score: '', missing_asset: '' })
const classLabels = { melee: 'Милик', archer: 'Лучник', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
const assets = [
  ['has_ship','Корабль'], ['has_tank','Танк'], ['has_fuchsias','Фуксория'], ['has_clouds','Облачко'],
  ['has_machaon','Махаон'], ['has_tare','Таре'], ['has_deer','Олень'], ['has_invulnerable_pet','Пет на неуяз'],
  ['has_shield_swap','Щит на свап'], ['has_flippers','Ласты'],
]
const activeFilterCount = computed(() => Object.values(filters).filter(value => String(value).trim() !== '').length)
let timer
async function load() {
  loading.value = true; error.value = ''
  const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value).trim() !== ''))
  try { data.value = (await api.get('/api/roster-readiness', { params })).data }
  catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить готовность состава.' }
  finally { loading.value = false }
}
function applyFilters(){router.replace({query:Object.fromEntries(Object.entries(filters).filter(([,value])=>String(value).trim()!==''))})}
function resetFilters() { Object.keys(filters).forEach(key => { filters[key] = '' });applyFilters() }
function removeFilter(key){filters[key]=''}
function delta(value) { const number = Number(value); return `${number > 0 ? '+' : ''}${formatInteger(number)}` }
function missing(player) { return assets.filter(([key]) => !player[key]).map(([, label]) => label) }
watch(filters, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 250) })
watch(()=>route.query,()=>{Object.keys(filters).forEach(key=>{filters[key]=String(route.query[key]??'')});load()},{deep:true})
onMounted(()=>{Object.keys(filters).forEach(key=>{filters[key]=String(route.query[key]??'')});load()})
</script>

<template>
  <section :class="{ 'report-loading': loading||error }">
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · РУКОВОДИТЕЛЯМ</p><h1>Готовность состава</h1></div></div>

    <div class="readiness-summary">
      <article><span>В выборке</span><strong>{{ data.summary.players ?? 0 }}</strong><small>игроков</small></article>
      <article><span>Средний ГС</span><strong>{{ formatInteger(data.summary.average_gear_score ?? 0) }}</strong><small>по текущему фильтру</small></article>
      <article><span>Полностью оснащены</span><strong>{{ data.summary.ready ?? 0 }}</strong><small>все отметки профиля</small></article>
    </div>

    <div class="panel readiness-filters">
      <input v-model="filters.search" placeholder="Никнейм">
      <select v-model="filters.group_id"><option value="">Все консты</option><option v-for="group in data.groups" :key="group.id" :value="group.id">{{ group.name }}</option></select>
      <select v-model="filters.class"><option value="">Все классы</option><option v-for="(label,key) in classLabels" :key="key" :value="key">{{ label }}</option></select>
      <input v-model.number="filters.min_gear_score" type="number" min="0" placeholder="ГС от">
      <input v-model.number="filters.max_gear_score" type="number" min="0" placeholder="ГС до">
      <select v-model="filters.missing_asset"><option value="">Любое оснащение</option><option v-for="asset in assets" :key="asset[0]" :value="asset[0]">Нет: {{ asset[1] }}</option></select>
      <button v-if="activeFilterCount" type="button" @click="resetFilters">Сбросить · {{ activeFilterCount }}</button><span class="filter-result">{{ loading?'Обновляем…':`${data.summary.players??0} игроков` }}</span>
    </div>
    <div v-if="activeFilterCount" class="active-filters" aria-label="Активные фильтры"><span v-for="(value,key) in filters" v-show="String(value).trim()" :key="key">{{ key==='search'?'Поиск':key==='group_id'?'Конста':key==='class'?'Класс':key==='min_gear_score'?'ГС от':key==='max_gear_score'?'ГС до':'Нет оснащения' }}: <b>{{ value }}</b><button aria-label="Убрать фильтр" @click="removeFilter(key)">×</button></span></div>

    <AsyncState :loading="loading" :error="error" loading-text="Загружаем готовность состава…" @retry="load" />
    <div v-if="!loading&&!error&&data.players.length" class="readiness-list">
      <article v-for="player in data.players" :key="player.id" class="panel readiness-player">
        <RouterLink class="readiness-identity" :to="`/players/${player.id}`"><PlayerAvatar :player="player" size="small"/><span><strong>{{ player.nickname }}</strong><span class="readiness-player-meta"><small>{{ player.group?.name ?? 'Без консты' }}</small><small :class="['class-tag', `class-${player.class}`]">{{ classLabels[player.class] }}</small></span></span></RouterLink>
        <div class="readiness-gear"><span><small>ГС</small><b>{{ formatInteger(player.gear_score) }}</b></span><span><small>Неделя</small><b :class="Number(player.gear_score_week_delta) >= 0 ? 'positive' : 'negative'">{{ delta(player.gear_score_week_delta) }}</b></span><span><small>Месяц</small><b :class="Number(player.gear_score_month_delta) >= 0 ? 'positive' : 'negative'">{{ delta(player.gear_score_month_delta) }}</b></span></div>
        <div class="readiness-equipment"><div class="readiness-assets"><span v-for="asset in assets" :key="asset[0]" :class="player[asset[0]] ? 'available' : 'missing'" :title="asset[1]">{{ player[asset[0]] ? '✓' : '×' }} {{ asset[1] }}</span></div><p v-if="missing(player).length" class="readiness-missing"><b>Не хватает:</b> {{ missing(player).join(', ') }}</p><p v-else class="readiness-complete">✓ Оснащение заполнено полностью</p></div>
      </article>
    </div>
    <div v-if="!loading&&!error&&!data.players.length" class="panel empty-state"><strong>{{ activeFilterCount?'По фильтрам ничего не найдено':'В составе пока нет игроков' }}</strong><p>{{ activeFilterCount?'Сбросьте или измените активные фильтры.':'Данные готовности появятся после добавления игроков.' }}</p><button v-if="activeFilterCount" @click="resetFilters">Сбросить фильтры</button></div>
  </section>
</template>
