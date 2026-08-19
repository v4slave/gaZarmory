<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'

const loading = ref(true)
const error = ref('')
const data = ref({ players: [], groups: [], summary: {} })
const filters = reactive({ search: '', group_id: '', class: '', min_gear_score: '', max_gear_score: '', missing_asset: '' })
const classLabels = { melee: 'Милик', archer: 'Лучник', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
const assets = [
  ['has_ship','Корабль'], ['has_tank','Танк'], ['has_fuchsias','Фуксория'], ['has_clouds','Облачко'],
  ['has_machaon','Махаон'], ['has_tare','Таре'], ['has_deer','Олень'], ['has_invulnerable_pet','Пет на неуяз'],
  ['has_shield_swap','Свап на щит'], ['has_flippers','Ласты'],
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
function resetFilters() { Object.keys(filters).forEach(key => { filters[key] = '' }) }
function delta(value) { const number = Number(value); return `${number > 0 ? '+' : ''}${number.toLocaleString('ru-RU')}` }
function missing(player) { return assets.filter(([key]) => !player[key]).map(([, label]) => label) }
watch(filters, () => { clearTimeout(timer); timer = setTimeout(load, 250) })
onMounted(load)
</script>

<template>
  <section>
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · РУКОВОДИТЕЛЯМ</p><h1>Готовность состава</h1><p class="muted">ГС, его динамика и оснащение игроков для формирования рейда</p></div></div>

    <div class="readiness-summary">
      <article><span>В выборке</span><strong>{{ data.summary.players ?? 0 }}</strong><small>игроков</small></article>
      <article><span>Средний ГС</span><strong>{{ Number(data.summary.average_gear_score ?? 0).toLocaleString('ru-RU') }}</strong><small>по текущему фильтру</small></article>
      <article><span>Полностью оснащены</span><strong>{{ data.summary.ready ?? 0 }}</strong><small>все отметки профиля</small></article>
    </div>

    <div class="panel readiness-filters">
      <input v-model="filters.search" placeholder="Никнейм">
      <select v-model="filters.group_id"><option value="">Все консты</option><option v-for="group in data.groups" :key="group.id" :value="group.id">{{ group.name }}</option></select>
      <select v-model="filters.class"><option value="">Все классы</option><option v-for="(label,key) in classLabels" :key="key" :value="key">{{ label }}</option></select>
      <input v-model.number="filters.min_gear_score" type="number" min="0" placeholder="ГС от">
      <input v-model.number="filters.max_gear_score" type="number" min="0" placeholder="ГС до">
      <select v-model="filters.missing_asset"><option value="">Любое оснащение</option><option v-for="asset in assets" :key="asset[0]" :value="asset[0]">Нет: {{ asset[1] }}</option></select>
      <button v-if="activeFilterCount" type="button" @click="resetFilters">Сбросить · {{ activeFilterCount }}</button>
    </div>

    <p v-if="error" class="notice error">{{ error }}</p>
    <div v-if="loading" class="panel empty">Загружаем данные…</div>
    <div v-else-if="data.players.length" class="readiness-list">
      <article v-for="player in data.players" :key="player.id" class="panel readiness-player">
        <RouterLink class="readiness-identity" :to="`/players/${player.id}`"><PlayerAvatar :player="player" size="small"/><span><strong>{{ player.nickname }}</strong><span class="readiness-player-meta"><small>{{ player.group?.name ?? 'Без консты' }}</small><small :class="['class-tag', `class-${player.class}`]">{{ classLabels[player.class] }}</small></span></span></RouterLink>
        <div class="readiness-gear"><span><small>ГС</small><b>{{ Number(player.gear_score).toLocaleString('ru-RU') }}</b></span><span><small>Неделя</small><b :class="Number(player.gear_score_week_delta) >= 0 ? 'positive' : 'negative'">{{ delta(player.gear_score_week_delta) }}</b></span><span><small>Месяц</small><b :class="Number(player.gear_score_month_delta) >= 0 ? 'positive' : 'negative'">{{ delta(player.gear_score_month_delta) }}</b></span></div>
        <div class="readiness-assets"><span v-for="asset in assets" :key="asset[0]" :class="player[asset[0]] ? 'available' : 'missing'" :title="asset[1]">{{ player[asset[0]] ? '✓' : '×' }} {{ asset[1] }}</span></div>
        <p v-if="missing(player).length" class="readiness-missing"><b>Не хватает:</b> {{ missing(player).join(', ') }}</p><p v-else class="readiness-complete">✓ Оснащение заполнено полностью</p>
      </article>
    </div>
    <div v-else class="panel empty">По выбранным условиям игроков не найдено.</div>
  </section>
</template>
