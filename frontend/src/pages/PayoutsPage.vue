<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import TokenAmount from '../components/TokenAmount.vue'
import AsyncState from '../components/AsyncState.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'
import { formatDate, formatDateTime, formatDecimal, formatInteger } from '../utils/format.js'

const auth = useAuthStore()
const router = useRouter()
const notifications = useNotificationsStore()
const data = ref({ summary: { gold: 0, primes: 0, participants: 0, paid_gold: 0 }, players: [] })
const search = ref('')
const error = ref('')
const earningsError = ref('')
const historyError = ref('')
const earningsLoading = ref(true)
const historyLoading = ref(true)
const showForm = ref(false)
const from = ref('')
const to = ref('')
const busy = ref(false)
const preview = ref(null)
const previewLoading = ref(false)
const selectionMode = ref('period'), selectedActivities = ref([])
const history = ref([])
const historyPagination = ref({ current_page: 1, last_page: 1, total: 0 })
const historyPages = computed(() => {
  const start = Math.max(1, Math.min(historyPagination.value.current_page - 2, historyPagination.value.last_page - 4))
  const end = Math.min(historyPagination.value.last_page, start + 4)
  return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})
const rows = computed(() => data.value.players.filter(row => row.nickname.toLowerCase().includes(search.value.toLowerCase())))
const attendance = row => data.value.summary.primes ? Math.min(100, Number(row.primes_count) / Number(data.value.summary.primes) * 100) : 0
const percent = value => `${formatDecimal(value, { maximumFractionDigits: 2 })}%`
const tokens = (value, unit = data.value.summary.token_unit_value) => Number(unit) > 0 ? Math.floor(Number(value) / Number(unit)) : 0
async function load() {
  earningsLoading.value = true; earningsError.value = ''
  try { data.value = (await api.get('/api/earnings/pending')).data }
  catch (e) { earningsError.value = apiErrorMessage(e, 'Не удалось загрузить начисления.') }
  finally { earningsLoading.value = false }
}
async function loadHistory(page = historyPagination.value.current_page) {
  historyLoading.value = true; historyError.value = ''
  try { const response = (await api.get('/api/payouts', { params: { page, per_page: 10 } })).data;history.value=response.data;historyPagination.value={current_page:response.current_page,last_page:response.last_page,total:response.total} }
  catch (e) { historyError.value = apiErrorMessage(e, 'Не удалось загрузить историю выплат.') }
  finally { historyLoading.value = false }
}
const payoutStatus = status => ({draft:'Черновик',calculated:'Рассчитан',paid:'Выплачен',cancelled:'Отменён'})[status] ?? status
const payoutDate = value => formatDate(value)
function openCreateForm() {
  const today = new Date(); const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)
  from.value = firstDay.toISOString().slice(0, 10); to.value = today.toISOString().slice(0, 10); selectionMode.value='period';selectedActivities.value=[];preview.value = null; error.value = ''; showForm.value = true; loadPreview()
}
async function loadPreview() {
  if (!from.value || !to.value || to.value < from.value) { preview.value = null; return }
  previewLoading.value = true; error.value = ''
  try { const params=selectionMode.value==='activities'&&selectedActivities.value.length?{activity_ids:selectedActivities.value}:{period_from:from.value,period_to:to.value};preview.value = (await api.get('/api/payouts-preview', { params })).data }
  catch (e) { preview.value = null; error.value = apiErrorMessage(e, 'Не удалось рассчитать предварительную выплату.') }
  finally { previewLoading.value = false }
}
async function create() { busy.value = true; error.value = ''; try { const payload=selectionMode.value==='activities'?{activity_ids:selectedActivities.value}:{period_from:from.value,period_to:to.value};const created=(await api.post('/api/payouts',payload)).data;showForm.value=false;notifications.success('Ведомость создана. Подтвердите фактическую выдачу.');await router.push(`/payouts/${created.id}`) } catch (e) { error.value = apiErrorMessage(e, 'Не удалось создать ведомость.'); notifications.error(error.value) } finally { busy.value = false } }
onMounted(() => Promise.all([load(), loadHistory()]))
</script>

<template>
  <section :class="{ 'earnings-unavailable': earningsLoading||earningsError||!data.players.length, 'history-unavailable': historyLoading||historyError||!history.length }">
    <div class="page-heading"><div><p class="eyebrow">ЭКОНОМИКА · НАХРЮК</p><h1>Нахрюк</h1></div><button v-if="auth.canCreatePayouts" class="primary" @click="openCreateForm">Создать нахрюк</button></div>
    <AsyncState :loading="earningsLoading" :error="earningsError" :empty="!data.players.length" loading-text="Загружаем ожидаемые начисления…" empty-title="Начислений пока нет" empty-text="Свободные начисления появятся после расчёта праймов." @retry="load" />
    <AsyncState :loading="historyLoading" :error="historyError" :empty="!history.length" loading-text="Загружаем историю ведомостей…" empty-title="Ведомостей пока нет" empty-text="Созданные ведомости выплат появятся здесь." @retry="loadHistory" />
    <div class="payout-stats"><StatCard label="На расплит / жетоны" :value="formatInteger(data.summary.gold)" gold :secondary-value="formatInteger(tokens(data.summary.gold))" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны" accent/><StatCard label="Праймы" :value="data.summary.primes"/><StatCard label="Участников" :value="data.summary.participants"/><StatCard label="Выплачено / жетоны" :value="formatInteger(data.summary.paid_gold ?? 0)" gold :secondary-value="formatInteger(tokens(data.summary.paid_gold))" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны"/></div>
    <p v-if="error" class="notice error">{{ error }}</p>
    <div class="panel payout-roster"><div class="panel-title payout-toolbar"><div><h2>Ожидаемые начисления</h2><p class="muted">Текущий незакрытый период</p></div><label class="search-field"><span>⌕</span><input v-model="search" placeholder="Поиск по нику…"></label></div><div class="table-wrap flat"><table><thead><tr><th>Ник</th><th>% посещения</th><th>Праймы</th><th class="right">Общая сумма</th></tr></thead><tbody><tr v-for="row in rows" :key="row.player_id"><td><RouterLink class="player-table-link" :to="`/players/${row.player_id}`">{{ row.nickname }}</RouterLink></td><td><strong class="attendance-value">{{ percent(attendance(row)) }}</strong></td><td>{{ row.primes_count }} <small v-if="data.summary.primes" class="success-text">({{ percent(attendance(row)) }})</small></td><td class="right payout-gold"><GoldAmount :value="formatInteger(row.amount)"/> <span class="stat-divider">/</span> <TokenAmount :value="formatInteger(tokens(row.amount))"/></td></tr><tr v-if="!rows.length"><td colspan="4" class="empty">Начислений пока нет.</td></tr></tbody></table></div></div>
    <div class="panel payout-history-panel"><div class="panel-title"><div><h2>История ведомостей</h2><p class="muted">{{ historyPagination.total }} записей</p></div></div><div class="table-wrap flat"><table><thead><tr><th>Период</th><th>Статус</th><th>Игроков</th><th>Активностей</th><th class="right">Сумма</th></tr></thead><tbody><tr v-for="payout in history" :key="payout.id"><td><RouterLink :to="`/payouts/${payout.id}`">#{{ payout.id }} · {{ payoutDate(payout.period_from) }} — {{ payoutDate(payout.period_to) }}</RouterLink></td><td><span :class="['import-status',payout.status==='paid'?'confirmed':'draft']">{{ payoutStatus(payout.status) }}</span></td><td>{{ payout.players_count }}</td><td>{{ payout.activities_count }}</td><td class="right"><GoldAmount :value="formatInteger(payout.total_amount)"/></td></tr><tr v-if="!history.length"><td colspan="5" class="empty">Ведомостей пока нет.</td></tr></tbody></table></div></div>
    <nav v-if="historyPagination.last_page > 1" class="roster-pagination" aria-label="Страницы истории выплат"><button :disabled="historyPagination.current_page === 1" @click="loadHistory(historyPagination.current_page - 1)">‹</button><button v-for="page in historyPages" :key="page" :class="{active:page===historyPagination.current_page}" @click="loadHistory(page)">{{ page }}</button><button :disabled="historyPagination.current_page === historyPagination.last_page" @click="loadHistory(historyPagination.current_page + 1)">›</button></nav>
    <div v-if="showForm" class="modal">
      <form class="form-card payout-preview-card" @submit.prevent="create">
        <header class="payout-create-heading"><div><p class="eyebrow">ЭКОНОМИКА · ВЕДОМОСТЬ</p><h2>Новая платёжная ведомость</h2></div><button type="button" class="modal-close" aria-label="Закрыть" @click="showForm=false">×</button></header>
        <div class="payout-mode"><button type="button" :class="{active:selectionMode==='period'}" @click="selectionMode='period';loadPreview()"><b>По периоду</b><small>Все свободные начисления за даты</small></button><button type="button" :class="{active:selectionMode==='activities'}" @click="selectionMode='activities';loadPreview()"><b>Конкретные активности</b><small>Выберите нужные праймы вручную</small></button></div>
        <div v-if="selectionMode==='period'" class="payout-period-fields"><label>Период с<input v-model="from" type="date" required @change="loadPreview"></label><label>Период по<input v-model="to" type="date" :min="from" required @change="loadPreview"></label></div>
        <div v-else class="payout-activity-options"><label v-for="item in preview?.activity_options??[]" :key="item.id" :class="{selected:selectedActivities.includes(item.id)}"><input v-model="selectedActivities" type="checkbox" :value="item.id" @change="loadPreview"><img v-if="item.definition?.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else class="payout-activity-placeholder">◆</span><span class="payout-activity-copy"><strong>{{ item.definition?.name }}</strong><small>{{ formatDateTime(item.occurred_at) }}</small></span><i>{{ selectedActivities.includes(item.id)?'✓':'+' }}</i></label></div>
        <div v-if="previewLoading" class="preview-loading">Рассчитываем выплату…</div>
        <template v-else-if="preview"><div class="financial-preview"><div><span>Активностей</span><strong>{{ preview.activities }}</strong></div><div><span>Игроков</span><strong>{{ preview.players }}</strong></div><div><span>К выдаче</span><strong><GoldAmount :value="formatInteger(preview.amount)"/></strong></div><div><span>Баланс до</span><strong><GoldAmount :value="formatInteger(preview.balance_before)"/></strong></div><div><span>Баланс после</span><strong :class="{negative:!preview.sufficient}"><GoldAmount :value="formatInteger(preview.balance_after)"/></strong></div></div><div class="payout-preview-players"><span v-for="row in preview.rows" :key="row.player_id"><b>{{ row.nickname }}</b><small>{{ row.activities_count }} акт. · {{ formatInteger(row.amount) }} зол.</small></span></div></template>
        <p v-if="preview&&!preview.sufficient" class="notice error">В казне недостаточно реального золота.</p><p v-else-if="preview&&preview.amount===0" class="notice">В выборке нет свободных начислений.</p><p class="payout-create-note">Создание фиксирует ведомость, но не списывает золото. Фактическая выдача отмечается на следующем экране.</p><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" @click="showForm=false">Отмена</button><button class="primary" :disabled="busy||previewLoading||!preview?.sufficient||!preview?.amount||(selectionMode==='activities'&&!selectedActivities.length)">{{ busy ? 'Создание…' : 'Создать ведомость' }}</button></div>
      </form>
    </div>
  </section>
</template>
