<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'

const data = ref({
  gold: 0,
  inventory_value: 0,
  pending_payout: 0,
  active_auctions: 0,
  average_gear_score: 0,
  class_distribution: {},
  attendance_period_days: 30,
  attendance_top: [],
  recent_activities: [],
  treasury_dynamics: [],
  upcoming_events: [],
})
const classLabels = { melee: 'Милик', archer: 'Лук', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
const now = ref(Date.now())
let timer
const chart = { left: 88, right: 958, top: 30, bottom: 220, labelY: 253 }
const chartMax = computed(() => Math.max(1, ...data.value.treasury_dynamics.flatMap(item => [Number(item.gold), Number(item.inventory_value)])))
function chartX(index) { return chart.left + index * ((chart.right - chart.left) / Math.max(1, data.value.treasury_dynamics.length - 1)) }
function chartY(value) { return chart.bottom - Number(value) / chartMax.value * (chart.bottom - chart.top) }
function chartPoints(field) { return data.value.treasury_dynamics.map((item,index) => `${chartX(index)},${chartY(item[field])}`).join(' ') }
function compactGold(value) {
  const amount = Number(value)
  const absolute = Math.abs(amount)
  const units = [[1e15, 'квадр.'], [1e12, 'трлн'], [1e9, 'млрд'], [1e6, 'млн'], [1e3, 'тыс.']]
  const unit = units.find(([threshold]) => absolute >= threshold)
  if (!unit) return Math.round(amount).toLocaleString('ru-RU')
  return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(amount / unit[0])} ${unit[1]}`
}
function eventCountdown(startsAt) {
  const seconds=Math.max(0,Math.floor((new Date(startsAt).getTime()-now.value)/1000));
  if(seconds<60)return `${seconds} сек.`
  const minutes=Math.floor(seconds/60); if(minutes<60)return `${minutes} мин.`
  const hours=Math.floor(minutes/60); const rest=minutes%60
  return hours<24?`${hours} ч. ${rest} мин.`:`${Math.floor(hours/24)} д. ${hours%24} ч.`
}
onMounted(async () => { data.value = (await api.get('/api/dashboard')).data; timer=window.setInterval(()=>{now.value=Date.now()},1000) })
onBeforeUnmount(()=>window.clearInterval(timer))
</script>

<template>
  <section class="dashboard-page">
    <div class="dashboard-heading"><p class="eyebrow">GAZ ARMORY · ARCHEAGE</p><h1>Дашборд</h1></div>
    <div class="stats-row dashboard-main-stats"><StatCard label="Золото в казне / жетоны" :value="Number(data.gold).toLocaleString('ru-RU')" gold :secondary-value="Number(data.gold_token_count).toLocaleString('ru-RU')" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны" note="Фактический баланс" accent/><StatCard label="Дроп с РБ / жетоны" :value="Number(data.inventory_value).toLocaleString('ru-RU')" gold :secondary-value="Number(data.inventory_token_count).toLocaleString('ru-RU')" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны" note="Эквивалент в золоте"/><StatCard label="Ожидаемый нахрюк / жетоны" :value="Number(data.pending_payout).toLocaleString('ru-RU')" gold :secondary-value="Number(data.pending_payout_token_count).toLocaleString('ru-RU')" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны"/><StatCard label="Средний ГС гильдии" :value="Number(data.average_gear_score).toLocaleString('ru-RU')"/><StatCard label="Активных аукционов" :value="data.active_auctions"/></div>
    <div class="panel class-distribution"><div class="panel-title"><h2>Состав по классам</h2><RouterLink to="/roster">Весь состав →</RouterLink></div><div><span v-for="(label,key) in classLabels" :key="key" :class="`class-${key}`"><small>{{ label }}</small><b>{{ Number(data.class_distribution?.[key]??0) }}</b></span></div></div>
    <div class="dashboard-overview-grid">
      <div class="panel treasury-chart"><div class="panel-title"><h2>↗ Динамика казны</h2><span class="chart-legend"><i class="gold-line"></i> Золото <i class="inventory-line"></i> Стоимость предметов</span></div><svg v-if="data.treasury_dynamics.length" viewBox="0 0 1000 270" role="img" aria-label="Динамика золота и стоимости предметов за 14 дней"><g class="chart-grid"><line v-for="step in 4" :key="step" :x1="chart.left" :x2="chart.right" :y1="chart.bottom-step*((chart.bottom-chart.top)/4)" :y2="chart.bottom-step*((chart.bottom-chart.top)/4)"/><text v-for="step in 4" :key="`label-${step}`" :x="chart.left-10" :y="chart.bottom+5-step*((chart.bottom-chart.top)/4)" text-anchor="end">{{ compactGold(chartMax*step/4) }}</text></g><polyline class="chart-area-gold" :points="`${chart.left},${chart.bottom} ${chartPoints('gold')} ${chart.right},${chart.bottom}`"/><polyline class="chart-line gold" :points="chartPoints('gold')"/><polyline class="chart-line inventory" :points="chartPoints('inventory_value')"/><g class="chart-dates"><text v-for="(item,index) in data.treasury_dynamics" :key="item.date" v-show="index%2===0||index===data.treasury_dynamics.length-1" :x="chartX(index)" :y="chart.labelY" text-anchor="middle">{{ new Date(`${item.date}T00:00:00`).toLocaleDateString('ru-RU',{day:'numeric',month:'short'}) }}</text></g></svg><p v-else class="empty">Операций казны пока нет.</p></div>
      <div class="panel upcoming-events"><div class="panel-title"><h2>▣ Ближайшие события</h2></div><div v-if="data.upcoming_events.length" class="upcoming-list"><article v-for="event in data.upcoming_events" :key="`${event.name}-${event.starts_at}`"><img v-if="event.icon_url" :src="event.icon_url" :alt="event.name"><span v-else class="event-placeholder">◆</span><div><strong>{{ event.name }}</strong><small>{{ new Date(event.starts_at).toLocaleString('ru-RU',{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) }}</small></div><b>{{ eventCountdown(event.starts_at) }}</b></article></div></div>
    </div>
    <div class="dashboard-grid dashboard-grid-two">
      <div class="panel attendance-top"><div class="panel-title"><div><h2>Топ-5 по посещаемости</h2><p class="muted">Праймы за {{ data.attendance_period_days }} дней</p></div><RouterLink to="/roster">Весь состав →</RouterLink></div><div v-if="data.attendance_top.length" class="attendance-ranking"><RouterLink v-for="(player,index) in data.attendance_top" :key="player.id" :to="`/players/${player.id}`"><b class="rank-place">{{ index + 1 }}</b><PlayerAvatar :player="player" size="small"/><span class="rank-player"><strong>{{ player.nickname }}</strong><small>{{ classLabels[player.class] }} · {{ player.primes_count }} прайм. · {{ player.mini_activities_count }} мини</small></span><strong class="rank-percent">{{ Number(player.attendance_percentage).toLocaleString('ru-RU') }}%</strong></RouterLink></div><p v-else class="empty">Посещений за этот период пока нет.</p></div>
      <div class="panel recent-activities"><div class="panel-title"><h2>Последние активности</h2><RouterLink to="/activities">Все события →</RouterLink></div><div v-if="data.recent_activities.length" class="recent-activity-list"><RouterLink v-for="item in data.recent_activities" :key="item.id" :to="`/activities/${item.id}`"><span class="recent-activity-mark"><img v-if="item.definition.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else>◆</span></span><span class="recent-activity-name"><strong>{{ item.definition.name }}</strong><small>{{ item.definition.type==='mini_activity'?'Мини-прайм':'Основной прайм' }} · {{ new Date(item.occurred_at).toLocaleDateString('ru-RU') }}</small></span><span class="recent-activity-count"><b>{{ item.players_count }}</b><small>участн.</small></span></RouterLink></div><p v-else class="empty">Событий пока нет.</p></div>
    </div>
  </section>
</template>
