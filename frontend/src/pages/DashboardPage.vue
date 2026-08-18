<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'

const data = ref({
  gold: 0,
  inventory_value: 0,
  pending_payout: 0,
  active_auctions: 0,
  attendance_period_days: 30,
  attendance_top: [],
  recent_activities: [],
  treasury_dynamics: [],
  upcoming_events: [],
})
const classLabels = { melee: 'Милик', archer: 'Лук', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
const now = ref(Date.now())
let timer
const chartMax = computed(() => Math.max(1, ...data.value.treasury_dynamics.flatMap(item => [Number(item.gold), Number(item.inventory_value)])))
function chartPoints(field) { const items=data.value.treasury_dynamics; return items.map((item,index) => `${40 + index * (650 / Math.max(1,items.length-1))},${185 - Number(item[field]) / chartMax.value * 145}`).join(' ') }
function compactGold(value) { return Intl.NumberFormat('ru-RU',{notation:'compact',maximumFractionDigits:1}).format(value) }
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
    <div class="dashboard-heading"><p class="eyebrow">GAZ ARMORY · ARCHEAGE</p><h1>Дашборд</h1><p class="muted">Оперативное состояние гильдии</p></div>
    <div class="stats-row four"><StatCard label="Золото в казне" :value="Number(data.gold).toLocaleString('ru-RU')" gold note="Фактический баланс" accent/><StatCard label="Дроп с РБ" :value="Number(data.inventory_value).toLocaleString('ru-RU')" gold note="Эквивалент в золоте"/><StatCard label="Ожидаемый нахрюк" :value="Number(data.pending_payout).toLocaleString('ru-RU')" gold/><StatCard label="Активных аукционов" :value="data.active_auctions"/></div>
    <div class="dashboard-overview-grid">
      <div class="panel treasury-chart"><div class="panel-title"><h2>↗ Динамика казны</h2><span class="chart-legend"><i class="gold-line"></i> Золото <i class="inventory-line"></i> Стоимость предметов</span></div><svg v-if="data.treasury_dynamics.length" viewBox="0 0 720 220" role="img" aria-label="Динамика золота и стоимости предметов за 14 дней"><g class="chart-grid"><line v-for="step in 4" :key="step" x1="40" x2="690" :y1="185-step*36.25" :y2="185-step*36.25"/><text v-for="step in 4" :key="`label-${step}`" x="34" :y="190-step*36.25" text-anchor="end">{{ compactGold(chartMax*step/4) }}</text></g><polyline class="chart-area-gold" :points="`40,185 ${chartPoints('gold')} 690,185`"/><polyline class="chart-line gold" :points="chartPoints('gold')"/><polyline class="chart-line inventory" :points="chartPoints('inventory_value')"/><g class="chart-dates"><text v-for="(item,index) in data.treasury_dynamics" :key="item.date" v-show="index%2===0||index===data.treasury_dynamics.length-1" :x="40+index*(650/Math.max(1,data.treasury_dynamics.length-1))" y="208" text-anchor="middle">{{ new Date(`${item.date}T00:00:00`).toLocaleDateString('ru-RU',{day:'numeric',month:'short'}) }}</text></g></svg><p v-else class="empty">Операций казны пока нет.</p></div>
      <div class="panel upcoming-events"><div class="panel-title"><h2>▣ Ближайшие события</h2></div><div v-if="data.upcoming_events.length" class="upcoming-list"><article v-for="event in data.upcoming_events" :key="`${event.name}-${event.starts_at}`"><img v-if="event.icon_url" :src="event.icon_url" :alt="event.name"><span v-else class="event-placeholder">◆</span><div><strong>{{ event.name }}</strong><small>{{ new Date(event.starts_at).toLocaleString('ru-RU',{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) }}</small></div><b>{{ eventCountdown(event.starts_at) }}</b></article></div></div>
    </div>
    <div class="dashboard-grid dashboard-grid-two">
      <div class="panel attendance-top"><div class="panel-title"><div><h2>Топ-5 по посещаемости</h2><p class="muted">Праймы за {{ data.attendance_period_days }} дней</p></div><RouterLink to="/roster">Весь состав →</RouterLink></div><div v-if="data.attendance_top.length" class="attendance-ranking"><RouterLink v-for="(player,index) in data.attendance_top" :key="player.id" :to="`/players/${player.id}`"><b class="rank-place">{{ index + 1 }}</b><span class="rank-player"><strong>{{ player.nickname }}</strong><small>{{ classLabels[player.class] }} · {{ player.primes_count }} прайм. · {{ player.mini_activities_count }} мини</small></span><strong class="rank-percent">{{ Number(player.attendance_percentage).toLocaleString('ru-RU') }}%</strong></RouterLink></div><p v-else class="empty">Посещений за этот период пока нет.</p></div>
      <div class="panel recent-activities"><div class="panel-title"><h2>Последние активности</h2><RouterLink to="/activities">Все события →</RouterLink></div><div v-if="data.recent_activities.length" class="recent-activity-list"><RouterLink v-for="item in data.recent_activities" :key="item.id" :to="`/activities/${item.id}`"><span class="recent-activity-mark"><img v-if="item.definition.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else>◆</span></span><span class="recent-activity-name"><strong>{{ item.definition.name }}</strong><small>{{ item.definition.type==='mini_activity'?'Мини-прайм':'Основной прайм' }} · {{ new Date(item.occurred_at).toLocaleDateString('ru-RU') }}</small></span><span class="recent-activity-count"><b>{{ item.players_count }}</b><small>участн.</small></span></RouterLink></div><p v-else class="empty">Событий пока нет.</p></div>
    </div>
  </section>
</template>
