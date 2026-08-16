<script setup>
import { onMounted, ref } from 'vue'
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
})
const classLabels = { melee: 'Милик', archer: 'Лук', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
onMounted(async () => { data.value = (await api.get('/api/dashboard')).data })
</script>

<template>
  <section>
    <p class="eyebrow">GAZ ARMORY · ARCHEAGE</p><h1>Дашборд</h1><p class="muted">Оперативное состояние гильдии</p>
    <div class="stats-row four"><StatCard label="Золото в казне" :value="Number(data.gold).toLocaleString('ru-RU')" gold note="Фактический баланс" accent/><StatCard label="Дроп с РБ" :value="Number(data.inventory_value).toLocaleString('ru-RU')" gold note="Эквивалент в золоте"/><StatCard label="Ожидаемый нахрюк" :value="Number(data.pending_payout).toLocaleString('ru-RU')" gold/><StatCard label="Активных аукционов" :value="data.active_auctions"/></div>
    <div class="dashboard-grid dashboard-grid-two">
      <div class="panel attendance-top"><div class="panel-title"><div><h2>Топ-5 по посещаемости</h2><p class="muted">Праймы за {{ data.attendance_period_days }} дней</p></div><RouterLink to="/roster">Весь состав →</RouterLink></div><div v-if="data.attendance_top.length" class="attendance-ranking"><RouterLink v-for="(player,index) in data.attendance_top" :key="player.id" :to="`/players/${player.id}`"><b class="rank-place">{{ index + 1 }}</b><span class="rank-player"><strong>{{ player.nickname }}</strong><small>{{ classLabels[player.class] }} · {{ player.primes_count }} прайм. · {{ player.mini_activities_count }} мини</small></span><strong class="rank-percent">{{ Number(player.attendance_percentage).toLocaleString('ru-RU') }}%</strong></RouterLink></div><p v-else class="empty">Посещений за этот период пока нет.</p></div>
      <div class="panel recent-activities"><div class="panel-title"><h2>Последние активности</h2><RouterLink to="/activities">Все события →</RouterLink></div><div v-if="data.recent_activities.length" class="recent-activity-list"><RouterLink v-for="item in data.recent_activities" :key="item.id" :to="`/activities/${item.id}`"><span class="recent-activity-mark"><img v-if="item.definition.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else>◆</span></span><span class="recent-activity-name"><strong>{{ item.definition.name }}</strong><small>{{ item.definition.type==='mini_activity'?'Мини-прайм':'Основной прайм' }} · {{ new Date(item.occurred_at).toLocaleDateString('ru-RU') }}</small></span><span class="recent-activity-count"><b>{{ item.players_count }}</b><small>участн.</small></span></RouterLink></div><p v-else class="empty">Событий пока нет.</p></div>
    </div>
  </section>
</template>
