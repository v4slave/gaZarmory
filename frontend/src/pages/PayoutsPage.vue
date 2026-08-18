<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const data = ref({ summary: { gold: 0, primes: 0, mini_activities: 0, participants: 0, paid_gold: 0 }, players: [] })
const search = ref('')
const error = ref('')
const showForm = ref(false)
const from = ref('')
const to = ref('')
const busy = ref(false)
const preview = ref(null)
const previewLoading = ref(false)
const rows = computed(() => data.value.players.filter(row => row.nickname.toLowerCase().includes(search.value.toLowerCase())))
const totalActivities = computed(() => Number(data.value.summary.primes) + Number(data.value.summary.mini_activities))
const attendance = row => data.value.summary.primes ? Math.min(100, Number(row.primes_count) / Number(data.value.summary.primes) * 100) : 0
const percent = value => `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })}%`
async function load() { try { data.value = (await api.get('/api/earnings/pending')).data } catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить начисления.' } }
function openCreateForm() {
  const today = new Date(); const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)
  from.value = firstDay.toISOString().slice(0, 10); to.value = today.toISOString().slice(0, 10); preview.value = null; error.value = ''; showForm.value = true; loadPreview()
}
async function loadPreview() {
  if (!from.value || !to.value || to.value < from.value) { preview.value = null; return }
  previewLoading.value = true; error.value = ''
  try { preview.value = (await api.get('/api/payouts-preview', { params: { period_from: from.value, period_to: to.value } })).data }
  catch (e) { preview.value = null; error.value = apiErrorMessage(e, 'Не удалось рассчитать предварительную выплату.') }
  finally { previewLoading.value = false }
}
async function create() { busy.value = true; error.value = ''; try { await api.post('/api/payouts', { period_from: from.value, period_to: to.value }); showForm.value = false; await load(); notifications.success('Нахрюк создан и выплачен.') } catch (e) { error.value = apiErrorMessage(e, 'Не удалось создать и выплатить нахрюк. Изменения отменены.'); notifications.error(error.value) } finally { busy.value = false } }
onMounted(load)
</script>

<template>
  <section>
    <div class="page-heading"><div><p class="eyebrow">ЭКОНОМИКА · НАХРЮК</p><h1>Нахрюк</h1><p class="muted">Начисления за фактически посещённые праймы и мини-праймы</p></div><button v-if="auth.canCreatePayouts" class="primary" @click="openCreateForm">Создать нахрюк</button></div>
    <div class="payout-stats"><StatCard label="Золото на расплит" :value="Number(data.summary.gold).toLocaleString('ru-RU')" gold accent/><StatCard label="Всего активностей" :value="totalActivities"/><StatCard label="Праймы / мини-праймы" :value="`${data.summary.primes} / ${data.summary.mini_activities}`"/><StatCard label="Участников" :value="data.summary.participants"/><StatCard label="Выплачено за всё время" :value="Number(data.summary.paid_gold ?? 0).toLocaleString('ru-RU')" gold/></div>
    <p v-if="error" class="notice error">{{ error }}</p>
    <div class="panel payout-roster"><div class="panel-title payout-toolbar"><div><h2>Ожидаемые начисления</h2><p class="muted">Текущий незакрытый период</p></div><label class="search-field"><span>⌕</span><input v-model="search" placeholder="Поиск по нику…"></label></div><div class="table-wrap flat"><table><thead><tr><th>Ник</th><th>% посещения</th><th>Праймы</th><th>Мини-праймы</th><th class="right">Общая сумма</th></tr></thead><tbody><tr v-for="row in rows" :key="row.player_id"><td><RouterLink class="player-table-link" :to="`/players/${row.player_id}`">{{ row.nickname }}</RouterLink></td><td><strong class="attendance-value">{{ percent(attendance(row)) }}</strong></td><td>{{ row.primes_count }} <small v-if="data.summary.primes" class="success-text">({{ percent(attendance(row)) }})</small></td><td>{{ row.mini_activities_count }}</td><td class="right payout-gold"><GoldAmount :value="Number(row.amount).toLocaleString('ru-RU')"/></td></tr><tr v-if="!rows.length"><td colspan="5" class="empty">Начислений пока нет.</td></tr></tbody></table></div></div>
    <div v-if="showForm" class="modal" @click.self="showForm=false"><form class="form-card payout-preview-card" @submit.prevent="create"><h2>Новый нахрюк</h2><label>Период с<input v-model="from" type="date" required @change="loadPreview"></label><label>Период по<input v-model="to" type="date" :min="from" required @change="loadPreview"></label><div v-if="previewLoading" class="preview-loading">Рассчитываем выплату…</div><div v-else-if="preview" class="financial-preview"><div><span>Активностей</span><strong>{{ preview.activities }}</strong></div><div><span>Игроков</span><strong>{{ preview.players }}</strong></div><div><span>Будет выплачено</span><strong><GoldAmount :value="Number(preview.amount).toLocaleString('ru-RU')"/></strong></div><div><span>Баланс до</span><strong><GoldAmount :value="Number(preview.balance_before).toLocaleString('ru-RU')"/></strong></div><div><span>Баланс после</span><strong :class="{negative:!preview.sufficient}"><GoldAmount :value="Number(preview.balance_after).toLocaleString('ru-RU')"/></strong></div></div><p v-if="preview&&!preview.sufficient" class="notice error">В казне недостаточно реального золота. Выплата не будет создана.</p><p v-else-if="preview&&preview.amount===0" class="notice">В выбранном периоде нет свободных начислений.</p><p class="muted">Суммы берутся из зафиксированных начислений и не редактируются вручную. Операция выполняется целиком либо полностью откатывается.</p><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" @click="showForm=false">Отмена</button><button class="primary" :disabled="busy||previewLoading||!preview?.sufficient||!preview?.amount">{{ busy ? 'Выплата…' : 'Создать и выплатить' }}</button></div></form></div>
  </section>
</template>
