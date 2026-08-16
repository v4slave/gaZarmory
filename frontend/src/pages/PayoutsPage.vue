<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const data = ref({ summary: { gold: 0, primes: 0, mini_activities: 0, participants: 0, paid_gold: 0 }, players: [] })
const payouts = ref([])
const search = ref('')
const error = ref('')
const showForm = ref(false)
const from = ref('')
const to = ref('')
const busy = ref(false)
const expanded = ref(null)
const rows = computed(() => data.value.players.filter(row => row.nickname.toLowerCase().includes(search.value.toLowerCase())))
const totalActivities = computed(() => Number(data.value.summary.primes) + Number(data.value.summary.mini_activities))
const attendance = row => data.value.summary.primes ? Math.min(100, Number(row.primes_count) / Number(data.value.summary.primes) * 100) : 0
const percent = value => `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })}%`
const payoutStatus = { draft: 'Черновик', calculated: 'Рассчитан', paid: 'Выплачен', cancelled: 'Отменён' }

async function load() { try { const [pending, batches] = await Promise.all([api.get('/api/earnings/pending'), api.get('/api/payouts')]); data.value = pending.data; payouts.value = batches.data } catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить начисления.' } }
async function create() { busy.value = true; error.value = ''; try { await api.post('/api/payouts', { period_from: from.value, period_to: to.value }); showForm.value = false; await load(); notifications.success('Нахрюк создан и рассчитан.') } catch (e) { error.value = apiErrorMessage(e, 'Не удалось рассчитать нахрюк. Изменения отменены.'); notifications.error(error.value) } finally { busy.value = false } }
async function action(id, name) { busy.value = true; error.value = ''; try { await api.post(`/api/payouts/${id}/${name}`); await load(); notifications.success(name === 'complete' ? 'Нахрюк отмечен выплаченным.' : name === 'cancel' ? 'Нахрюк отменён.' : 'Нахрюк пересчитан.') } catch (e) { error.value = apiErrorMessage(e); notifications.error(error.value) } finally { busy.value = false } }
onMounted(load)
</script>

<template>
  <section>
    <div class="page-heading"><div><p class="eyebrow">ЭКОНОМИКА · НАХРЮК</p><h1>Нахрюк</h1><p class="muted">Начисления за фактически посещённые праймы и мини-праймы</p></div><button v-if="auth.canManage" class="primary" @click="showForm=true">Создать нахрюк</button></div>
    <div class="payout-stats"><StatCard label="Золото на расплит" :value="Number(data.summary.gold).toLocaleString('ru-RU')" accent/><StatCard label="Всего активностей" :value="totalActivities"/><StatCard label="Праймы / мини-праймы" :value="`${data.summary.primes} / ${data.summary.mini_activities}`"/><StatCard label="Участников" :value="data.summary.participants"/><StatCard label="Выплачено за всё время" :value="Number(data.summary.paid_gold ?? 0).toLocaleString('ru-RU')"/></div>
    <p v-if="error" class="notice error">{{ error }}</p>
    <div class="panel payout-roster"><div class="panel-title payout-toolbar"><div><h2>Ожидаемые начисления</h2><p class="muted">Текущий незакрытый период</p></div><label class="search-field"><span>⌕</span><input v-model="search" placeholder="Поиск по нику…"></label></div><div class="table-wrap flat"><table><thead><tr><th>Ник</th><th>% посещения</th><th>Праймы</th><th>Мини-праймы</th><th class="right">Общая сумма</th></tr></thead><tbody><tr v-for="row in rows" :key="row.player_id"><td><RouterLink class="player-table-link" :to="`/players/${row.player_id}`">{{ row.nickname }}</RouterLink></td><td><strong class="attendance-value">{{ percent(attendance(row)) }}</strong></td><td>{{ row.primes_count }} <small v-if="data.summary.primes" class="success-text">({{ percent(attendance(row)) }})</small></td><td>{{ row.mini_activities_count }}</td><td class="right payout-gold">{{ Number(row.amount).toLocaleString('ru-RU') }} 🪙</td></tr><tr v-if="!rows.length"><td colspan="5" class="empty">Начислений пока нет.</td></tr></tbody></table></div></div>
    <div class="panel payout-batches"><div class="panel-title"><div><h2>История нахрюков</h2><p class="muted">Черновики, рассчитанные и завершённые выплаты</p></div><span class="muted">{{ payouts.length }}</span></div><article v-for="payout in payouts" :key="payout.id" class="payout-batch"><div><strong>Нахрюк #{{ payout.id }}</strong><span>{{ new Date(payout.period_from).toLocaleDateString('ru-RU') }} — {{ new Date(payout.period_to).toLocaleDateString('ru-RU') }}</span></div><b>{{ Number(payout.total_amount).toLocaleString('ru-RU') }} 🪙</b><span :class="['import-status', payout.status === 'paid' ? 'confirmed' : 'draft']">{{ payoutStatus[payout.status] }}</span><div class="batch-actions"><RouterLink class="secondary button-link" :to="`/payouts/${payout.id}`">Открыть</RouterLink><button class="secondary" @click="expanded=expanded===payout.id?null:payout.id">{{ expanded===payout.id?'Скрыть состав':'Быстрый просмотр' }}</button><template v-if="auth.canManage&&payout.status==='calculated'"><button class="primary" :disabled="busy" @click="action(payout.id,'complete')">Подтвердить выплату</button><button class="danger" :disabled="busy" @click="action(payout.id,'cancel')">Отменить нахрюк</button></template></div><div v-if="expanded===payout.id" class="payout-details"><div><h3>События</h3><RouterLink v-for="activity in payout.activities" :key="activity.id" :to="`/activities/${activity.id}`">{{ activity.definition?.name }} <small>{{ activity.definition?.type==='mini_activity'?'Мини-прайм':'Прайм' }}</small></RouterLink><p v-if="!payout.activities?.length" class="empty">События ещё не рассчитаны.</p></div><div><h3>Участники</h3><span v-for="player in payout.players" :key="player.id"><strong>{{ player.nickname_snapshot }}</strong><small>{{ player.primes_count }} прайм. · {{ player.mini_activities_count }} мини</small><b>{{ Number(player.amount).toLocaleString('ru-RU') }} 🪙</b></span></div></div></article><p v-if="!payouts.length" class="empty">История нахрюков пока пуста.</p></div>
    <div v-if="showForm" class="modal" @click.self="showForm=false"><form class="form-card" @submit.prevent="create"><h2>Новый нахрюк</h2><label>Период с<input v-model="from" type="date" required></label><label>Период по<input v-model="to" type="date" :min="from" required></label><p class="muted">Будут рассчитаны все свободные начисления выбранного периода. Если расчёт завершится ошибкой, нахрюк не сохранится.</p><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" @click="showForm=false">Отмена</button><button class="primary" :disabled="busy">{{ busy ? 'Расчёт…' : 'Рассчитать' }}</button></div></form></div>
  </section>
</template>
