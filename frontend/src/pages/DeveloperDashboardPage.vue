<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api.js'
import { formatInteger, localeTag } from '../utils/format.js'
import { useLocale } from '../i18n.js'

const classLabels = { melee: 'Милик', archer: 'Лук', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
const data = ref({ gold: 0, gold_token_count: 0, inventory_value: 0, inventory_token_count: 0, pending_payout: 0, pending_payout_token_count: 0, average_gear_score: 0, active_auctions: 0, class_distribution: {}, weekly_events: [], upcoming_events: [] })
const { t } = useLocale()
const loading = ref(true)
const error = ref('')
const formatDay = value => new Intl.DateTimeFormat(localeTag(), { weekday: 'short' }).format(value)
const formatEventDate = value => new Intl.DateTimeFormat(localeTag(), { day: 'numeric', month: 'short' }).format(value)
const formatEventTime = value => new Intl.DateTimeFormat(localeTag(), { hour: '2-digit', minute: '2-digit' }).format(value)
const formatEventDateTime = value => new Intl.DateTimeFormat(localeTag(), { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }).format(value)

const days = computed(() => {
  const grouped = new Map()
  for (const event of data.value.weekly_events ?? []) {
    const date = new Date(event.starts_at)
    const key = date.toISOString().slice(0, 10)
    if (!grouped.has(key)) grouped.set(key, { key, date, events: [] })
    grouped.get(key).events.push(event)
  }
  return [...grouped.values()].slice(0, 7)
})
const nearestEvent = computed(() => data.value.upcoming_events?.[0]
  ?? data.value.weekly_events?.find(event => new Date(event.starts_at).getTime() >= Date.now())
  ?? null)
const isNearestEvent = event => {
  if (!nearestEvent.value) return false
  if (event.id != null && nearestEvent.value.id != null) return event.id === nearestEvent.value.id
  return event.name === nearestEvent.value.name && event.starts_at === nearestEvent.value.starts_at
}
const eventTarget = event => event.activity_id ? `/activities/${event.activity_id}` : null

onMounted(async () => {
  try {
    data.value = (await api.get('/api/dashboard')).data
  } catch (requestError) {
    error.value = requestError.response?.data?.message ?? t('Не удалось загрузить дашборд.')
  } finally { loading.value = false }
})
</script>

<template>
  <div class="developer-dashboard">
    <div class="developer-main">
      <div class="developer-heading"><div><h1>{{ t('Главная') }}</h1><p>{{ t('Расписание') }}</p></div><span>{{ t('Ворованное армори') }}</span></div>
      <p v-if="loading" class="developer-state">Загрузка…</p><p v-else-if="error" class="developer-state error">{{ error }}</p>
      <template v-else>
        <section class="developer-calendar">
          <article v-for="day in days" :key="day.key" class="developer-day"><header><b>{{ formatDay(day.date) }}</b><small>{{ formatEventDate(day.date) }}</small></header><component :is="eventTarget(event)?'RouterLink':'div'" v-for="event in day.events" :key="`${event.name}-${event.starts_at}`" :to="eventTarget(event)??undefined" :title="t(eventTarget(event)?'Открыть созданную активность':'Активность для этого времени ещё не создана')" :class="['developer-event',{active:isNearestEvent(event),linked:Boolean(eventTarget(event))}]"><img v-if="event.icon_url" :src="event.icon_url" :alt="event.name"><span v-else>◆</span><strong>{{ event.name }}</strong><time>{{ formatEventTime(new Date(event.starts_at)) }}</time></component></article>
        </section>
        <div class="developer-after">
          <section class="developer-selected"><template v-if="nearestEvent"><img v-if="nearestEvent.icon_url" :src="nearestEvent.icon_url" :alt="nearestEvent.name"><div><p>{{ t('БЛИЖАЙШЕЕ СОБЫТИЕ') }}</p><h2>{{ nearestEvent.name }}</h2><span>{{ formatEventDateTime(new Date(nearestEvent.starts_at)) }}</span><RouterLink v-if="eventTarget(nearestEvent)" :to="eventTarget(nearestEvent)">{{ t('Открыть созданную активность') }} →</RouterLink><small v-else class="developer-event-unlinked">{{ t('Активность для этого времени ещё не создана') }}</small></div></template><p v-else class="developer-empty">{{ t('Ближайших событий нет.') }}</p></section>
          <section class="developer-classes"><header><h2>Состав по классам</h2><RouterLink to="/roster">Весь состав →</RouterLink></header><div><span v-for="(label,key) in classLabels" :key="key"><small>{{ label }}</small><i><b :style="{width:`${Math.min(100,Number(data.class_distribution?.[key]??0)/Math.max(1,...Object.values(data.class_distribution??{}).map(Number))*100)}%`}"></b></i><strong>{{ Number(data.class_distribution?.[key]??0) }}</strong></span></div></section>
        </div>
        <a class="gear-calculator" href="https://archa.ge/" target="_blank" rel="noopener noreferrer">
          <span class="gear-calculator-icon" aria-hidden="true">⚔</span>
          <span class="gear-calculator-copy"><small>{{ t('ИНСТРУМЕНТ') }}</small><strong>{{ t('Калькулятор шмота персонажа') }}</strong><span>{{ t('Соберите комплект и рассчитайте характеристики персонажа на archa.ge') }}</span></span>
          <span class="gear-calculator-action">{{ t('Открыть калькулятор') }} <b aria-hidden="true">↗</b></span>
        </a>
        <section class="developer-ticker"><div><span>Золото в казне / жетоны</span><p><img src="/images/gold.png" alt="Золото"><b>{{ formatInteger(data.gold) }}</b><img src="/images/treasury-token.png" alt="Жетоны"><small>{{ formatInteger(data.gold_token_count) }}</small></p></div><div><span>Дроп с РБ / жетоны</span><p><img src="/images/gold.png" alt="Золото"><b>{{ formatInteger(data.inventory_value) }}</b><img src="/images/treasury-token.png" alt="Жетоны"><small>{{ formatInteger(data.inventory_token_count) }}</small></p></div><div><span>Ожидаемый нахрюк / жетоны</span><p><img src="/images/gold.png" alt="Золото"><b>{{ formatInteger(data.pending_payout) }}</b><img src="/images/treasury-token.png" alt="Жетоны"><small>{{ formatInteger(data.pending_payout_token_count) }}</small></p></div><div><span>Средний ГС</span><p><b>{{ formatInteger(data.average_gear_score) }}</b></p></div><div><span>Активных аукционов</span><p><b>{{ data.active_auctions }}</b></p></div></section>
      </template>
    </div>
  </div>
</template>

<style scoped>
.developer-dashboard{min-height:100vh;color:#f3ede3;background:linear-gradient(90deg,rgba(3,3,3,.55),rgba(5,4,3,.3) 48%,rgba(3,3,3,.54)),linear-gradient(rgba(0,0,0,.2),rgba(0,0,0,.48)),url('/images/gaz-armory-noir-background.png') center top/cover fixed}.developer-topbar{display:grid;grid-template-columns:260px 1fr;min-height:104px;border-bottom:1px solid rgba(217,154,62,.3);background:rgba(6,6,6,.92)}.developer-brand{display:flex;align-items:center;gap:12px;padding:0 22px;color:#f3ede3;text-decoration:none}.developer-brand img{width:60px;height:60px;object-fit:contain}.developer-brand b,.developer-brand small{display:block}.developer-brand b{font:500 20px Georgia,serif;letter-spacing:.08em}.developer-brand small{color:#9d9180}.developer-menus{display:grid;align-content:center;gap:7px}.developer-menus nav{display:flex;justify-content:center;gap:6px}.developer-menus a{display:flex;align-items:center;gap:7px;padding:8px 11px;color:#c9bfb2;text-decoration:none;border:1px solid rgba(217,154,62,.18);background:rgba(255,255,255,.025)}.developer-menus a.active{color:#171008;border-color:#efb85f;background:linear-gradient(135deg,#efbd68,#ad702b)}.developer-menus .secondary a{padding:6px 9px;color:#aaa092;background:rgba(0,0,0,.28)}.developer-main{width:min(1500px,calc(100% - 52px));margin:auto;padding:24px 0 38px}.developer-heading{display:flex;align-items:end;justify-content:space-between;margin-bottom:16px}.developer-heading p,.developer-selected p{margin:0 0 5px;color:#dfa650;font-size:10px;letter-spacing:.14em}.developer-heading h1{margin:0;font:500 31px Georgia,serif}.developer-heading>span{color:#a99c8b}.developer-state{padding:30px;border:1px solid rgba(193,139,57,.4);background:rgba(8,8,8,.85)}.developer-calendar{display:grid;grid-template-columns:repeat(7,minmax(125px,1fr));min-height:390px;border-block:1px solid rgba(217,154,62,.46);background:rgba(7,7,7,.72);backdrop-filter:blur(7px)}.developer-day{padding:13px 8px;border-right:1px solid rgba(217,154,62,.22)}.developer-day:last-child{border:0}.developer-day>header{display:flex;justify-content:space-between;padding:0 3px 11px;border-bottom:1px solid rgba(217,154,62,.17);text-transform:uppercase}.developer-day header small{color:#948878}.developer-event{display:grid;grid-template-columns:1fr auto;width:100%;margin-top:8px;padding:0;overflow:hidden;color:#eee4d6;text-align:left;border:1px solid rgba(193,139,57,.35);background:#0a0a0a}.developer-event img,.developer-event>span{grid-column:1/-1;width:100%;height:66px;object-fit:cover}.developer-event>span{display:grid;place-items:center}.developer-event strong,.developer-event time{padding:7px}.developer-event time{color:#dba650}.developer-event.active{border-color:#efb85f;box-shadow:0 0 0 1px rgba(239,184,95,.25)}.developer-after{display:grid;grid-template-columns:1.2fr .8fr;gap:13px;margin-top:13px}.developer-selected,.developer-classes,.developer-ticker{border:1px solid rgba(193,139,57,.45);background:linear-gradient(145deg,rgba(24,23,21,.91),rgba(10,10,10,.96));backdrop-filter:blur(7px)}.developer-selected{display:grid;grid-template-columns:180px 1fr;gap:18px;padding:17px}.developer-selected img{width:180px;height:108px;object-fit:cover}.developer-selected h2{margin:0 0 7px;font:500 24px Georgia,serif}.developer-selected span{display:block;color:#a99c8b}.developer-selected a,.developer-classes a{display:inline-block;margin-top:13px;color:#dda64e;text-decoration:none}.developer-classes{padding:17px}.developer-classes header{display:flex;justify-content:space-between}.developer-classes h2{margin:0;font:500 17px Georgia,serif}.developer-classes header a{margin:0}.developer-classes>div{display:grid;grid-template-columns:1fr 1fr;gap:9px 18px;margin-top:13px}.developer-classes>div>span{display:grid;grid-template-columns:46px 1fr 18px;align-items:center;gap:8px}.developer-classes i{height:6px;background:rgba(255,255,255,.09)}.developer-classes i b{display:block;height:100%;background:#d99a3e}.gear-calculator{display:grid;grid-template-columns:52px minmax(0,1fr) auto;align-items:center;gap:15px;margin-top:13px;padding:14px 17px;color:#f2e8d9;text-decoration:none;border:1px solid rgba(222,162,72,.5);background:radial-gradient(circle at 8% 50%,rgba(128,79,22,.31),transparent 30%),linear-gradient(100deg,rgba(30,24,17,.96),rgba(10,10,10,.97));box-shadow:inset 0 1px rgba(255,222,167,.05);transition:.18s ease}.gear-calculator:hover{border-color:#efb85f;transform:translateY(-1px);box-shadow:0 8px 25px rgba(0,0,0,.26),inset 0 1px rgba(255,222,167,.08)}.gear-calculator-icon{display:grid;place-items:center;width:48px;height:48px;color:#f1b85f;border:1px solid rgba(225,163,72,.62);border-radius:50%;background:rgba(75,46,16,.42);font-size:23px}.gear-calculator-copy{display:grid;gap:3px}.gear-calculator-copy small{color:#d89d48;font-size:9px;letter-spacing:.14em}.gear-calculator-copy strong{font:500 18px Georgia,serif}.gear-calculator-copy>span{color:#9f9485;font-size:11px}.gear-calculator-action{padding:10px 14px;color:#171008;border:1px solid #efb85f;border-radius:4px;background:linear-gradient(135deg,#f0bd68,#b6782e);font-size:11px;font-weight:700;white-space:nowrap}.gear-calculator-action b{margin-left:4px;font-size:14px}.developer-ticker{display:grid;grid-template-columns:1.35fr 1.35fr 1.35fr .9fr .9fr;margin-top:13px}.developer-ticker>div{padding:13px 15px;border-right:1px solid rgba(217,154,62,.2)}.developer-ticker>div>span{color:#9e9281;font-size:9px;text-transform:uppercase}.developer-ticker p{display:flex;align-items:center;gap:6px;margin:8px 0 0}.developer-ticker img{width:21px;height:21px;object-fit:contain}.developer-ticker b{color:#f0bc65;font:500 18px ui-monospace,monospace}.developer-ticker small{color:#a99c8c}@media(max-width:1050px){.developer-topbar{grid-template-columns:1fr}.developer-brand{display:none}.developer-menus{padding:10px;overflow:auto}.developer-menus nav{justify-content:flex-start}.developer-calendar{grid-template-columns:repeat(4,1fr)}.developer-after{grid-template-columns:1fr}.gear-calculator{grid-template-columns:48px minmax(0,1fr)}.gear-calculator-action{grid-column:1/-1;text-align:center}.developer-ticker{grid-template-columns:repeat(2,1fr)}}
.developer-heading p{margin:5px 0 0;font-size:11px}.developer-day header small{color:#a89c8c;font-size:11px}.developer-event{text-decoration:none;transition:.16s ease;opacity:.72;cursor:default}.developer-event.linked{opacity:1;cursor:pointer}.developer-event.linked:hover{border-color:#d99a3e;transform:translateY(-1px);box-shadow:0 6px 16px rgba(0,0,0,.3)}.developer-event-unlinked{display:block;margin-top:13px;color:#8f8476}.gear-calculator-copy small,.developer-ticker>div>span{font-size:10px}.gear-calculator-copy>span{color:#aaa092;font-size:12px}.developer-ticker small{color:#b2a696}
</style>
