<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import TokenAmount from '../components/TokenAmount.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const auth = useAuthStore()
const router = useRouter()
const notifications = useNotificationsStore()
const data = ref({ summary: { gold: 0, primes: 0, participants: 0, paid_gold: 0 }, players: [] })
const search = ref('')
const error = ref('')
const showForm = ref(false)
const from = ref('')
const to = ref('')
const busy = ref(false)
const preview = ref(null)
const previewLoading = ref(false)
const selectionMode = ref('period'), selectedActivities = ref([])
const rows = computed(() => data.value.players.filter(row => row.nickname.toLowerCase().includes(search.value.toLowerCase())))
const attendance = row => data.value.summary.primes ? Math.min(100, Number(row.primes_count) / Number(data.value.summary.primes) * 100) : 0
const percent = value => `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })}%`
const tokens = (value, unit = data.value.summary.token_unit_value) => Number(unit) > 0 ? Math.floor(Number(value) / Number(unit)) : 0
async function load() { try { data.value = (await api.get('/api/earnings/pending')).data } catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить начисления.' } }
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
onMounted(load)
</script>

<template>
  <section>
    <div class="page-heading"><div><p class="eyebrow">ЭКОНОМИКА · НАХРЮК</p><h1>Нахрюк</h1><p class="muted">Начисления за фактически посещённые праймы</p></div><button v-if="auth.canCreatePayouts" class="primary" @click="openCreateForm">Создать нахрюк</button></div>
    <div class="payout-stats"><StatCard label="На расплит / жетоны" :value="Number(data.summary.gold).toLocaleString('ru-RU')" gold :secondary-value="tokens(data.summary.gold).toLocaleString('ru-RU')" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны" accent/><StatCard label="Праймы" :value="data.summary.primes"/><StatCard label="Участников" :value="data.summary.participants"/><StatCard label="Выплачено / жетоны" :value="Number(data.summary.paid_gold ?? 0).toLocaleString('ru-RU')" gold :secondary-value="tokens(data.summary.paid_gold).toLocaleString('ru-RU')" secondary-icon="/images/treasury-token.png" secondary-alt="жетоны"/></div>
    <p v-if="error" class="notice error">{{ error }}</p>
    <div class="panel payout-roster"><div class="panel-title payout-toolbar"><div><h2>Ожидаемые начисления</h2><p class="muted">Текущий незакрытый период</p></div><label class="search-field"><span>⌕</span><input v-model="search" placeholder="Поиск по нику…"></label></div><div class="table-wrap flat"><table><thead><tr><th>Ник</th><th>% посещения</th><th>Праймы</th><th class="right">Общая сумма</th></tr></thead><tbody><tr v-for="row in rows" :key="row.player_id"><td><RouterLink class="player-table-link" :to="`/players/${row.player_id}`">{{ row.nickname }}</RouterLink></td><td><strong class="attendance-value">{{ percent(attendance(row)) }}</strong></td><td>{{ row.primes_count }} <small v-if="data.summary.primes" class="success-text">({{ percent(attendance(row)) }})</small></td><td class="right payout-gold"><GoldAmount :value="Number(row.amount).toLocaleString('ru-RU')"/> <span class="stat-divider">/</span> <TokenAmount :value="tokens(row.amount).toLocaleString('ru-RU')"/></td></tr><tr v-if="!rows.length"><td colspan="4" class="empty">Начислений пока нет.</td></tr></tbody></table></div></div>
    <div v-if="showForm" class="modal" @click.self="showForm=false">
      <form class="form-card payout-preview-card" @submit.prevent="create">
        <header class="payout-create-heading"><div><p class="eyebrow">ЭКОНОМИКА · ВЕДОМОСТЬ</p><h2>Новая платёжная ведомость</h2></div><button type="button" class="modal-close" aria-label="Закрыть" @click="showForm=false">×</button></header>
        <div class="payout-mode"><button type="button" :class="{active:selectionMode==='period'}" @click="selectionMode='period';loadPreview()"><b>По периоду</b><small>Все свободные начисления за даты</small></button><button type="button" :class="{active:selectionMode==='activities'}" @click="selectionMode='activities';loadPreview()"><b>Конкретные активности</b><small>Выберите нужные праймы вручную</small></button></div>
        <div v-if="selectionMode==='period'" class="payout-period-fields"><label>Период с<input v-model="from" type="date" required @change="loadPreview"></label><label>Период по<input v-model="to" type="date" :min="from" required @change="loadPreview"></label></div>
        <div v-else class="payout-activity-options"><label v-for="item in preview?.activity_options??[]" :key="item.id" :class="{selected:selectedActivities.includes(item.id)}"><input v-model="selectedActivities" type="checkbox" :value="item.id" @change="loadPreview"><img v-if="item.definition?.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><span v-else class="payout-activity-placeholder">◆</span><span class="payout-activity-copy"><strong>{{ item.definition?.name }}</strong><small>{{ new Date(item.occurred_at).toLocaleString('ru-RU') }}</small></span><i>{{ selectedActivities.includes(item.id)?'✓':'+' }}</i></label></div>
        <div v-if="previewLoading" class="preview-loading">Рассчитываем выплату…</div>
        <template v-else-if="preview"><div class="financial-preview"><div><span>Активностей</span><strong>{{ preview.activities }}</strong></div><div><span>Игроков</span><strong>{{ preview.players }}</strong></div><div><span>К выдаче</span><strong><GoldAmount :value="Number(preview.amount).toLocaleString('ru-RU')"/></strong></div><div><span>Баланс до</span><strong><GoldAmount :value="Number(preview.balance_before).toLocaleString('ru-RU')"/></strong></div><div><span>Баланс после</span><strong :class="{negative:!preview.sufficient}"><GoldAmount :value="Number(preview.balance_after).toLocaleString('ru-RU')"/></strong></div></div><div class="payout-preview-players"><span v-for="row in preview.rows" :key="row.player_id"><b>{{ row.nickname }}</b><small>{{ row.activities_count }} акт. · {{ Number(row.amount).toLocaleString('ru-RU') }} зол.</small></span></div></template>
        <p v-if="preview&&!preview.sufficient" class="notice error">В казне недостаточно реального золота.</p><p v-else-if="preview&&preview.amount===0" class="notice">В выборке нет свободных начислений.</p><p class="payout-create-note">Создание фиксирует ведомость, но не списывает золото. Фактическая выдача отмечается на следующем экране.</p><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" @click="showForm=false">Отмена</button><button class="primary" :disabled="busy||previewLoading||!preview?.sufficient||!preview?.amount||(selectionMode==='activities'&&!selectedActivities.length)">{{ busy ? 'Создание…' : 'Создать ведомость' }}</button></div>
      </form>
    </div>
  </section>
</template>
