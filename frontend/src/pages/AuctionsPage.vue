<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'
import TokenAmount from '../components/TokenAmount.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import AsyncState from '../components/AsyncState.vue'
import { formatDateTime, formatInteger } from '../utils/format.js'

const auth = useAuthStore()
const auctions = ref([])
const items = ref([])
const showForm = ref(false)
const editingId = ref(null)
const error = ref('')
const loadError = ref('')
const loading = ref(true)
const busy = ref(false)
const clock = ref(Date.now())
const selectedAuction = ref(null)
const selectedBidsPage = ref(1)
const modalMode = ref('bid')
const bidAmount = ref(0)
const modalError = ref('')
const modalBusy = ref(false)
const archive = ref(null)
let ticker, liveTicker

function stopPolling() {
  window.clearInterval(ticker)
  window.clearInterval(liveTicker)
  ticker = undefined
  liveTicker = undefined
}

function startPolling() {
  stopPolling()
  if (document.visibilityState !== 'visible') return
  ticker = window.setInterval(() => { clock.value = Date.now() }, 1000)
  liveTicker=window.setInterval(async()=>{try{await loadAll();if(selectedAuction.value)selectedAuction.value=(await api.get(`/api/auctions/${selectedAuction.value.id}`,{params:{bids_page:selectedBidsPage.value,bids_per_page:20}})).data}catch{/* Keep the last successful live state. */}},5000)
}

async function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    clock.value = Date.now()
    try { await loadAll();if(selectedAuction.value)await loadSelectedBids(selectedBidsPage.value) } catch {/* Keep the last successful state. */}
  }
  startPolling()
}

const form = reactive({ treasury_item_id: '', quantity: 1, starting_bid: 0, minimum_step: 1, extension_minutes: 3, ends_at: '' })
const activeCount = ref(0)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const pageNumbers = computed(() => {
  const start = Math.max(1, Math.min(pagination.value.current_page - 2, pagination.value.last_page - 4))
  const end = Math.min(pagination.value.last_page, start + 4)
  return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})
const selectedTopBid = computed(() => selectedAuction.value?.top_bid ?? selectedAuction.value?.bids?.[0] ?? null)
const minimumBid = computed(() => selectedTopBid.value
  ? Number(selectedTopBid.value.amount) + Number(selectedAuction.value.minimum_step)
  : Number(selectedAuction.value?.starting_bid ?? 0))

function localDateTime(date) {
  return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Moscow', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(date)).replace(' ', 'T')
}

function displayDateTime(date) {
  return formatDateTime(date, { timeZone: 'Europe/Moscow', timeStyle: 'medium' })
}

function moscowIso(localValue) { return `${localValue}:00+03:00` }
function minimumEndsAt() { return localDateTime(new Date(Math.ceil((Date.now() + 10 * 60 * 1000) / 60000) * 60000)) }
function topBid(auction) { return auction.top_bid ?? null }
function currentPrice(auction) { return Number(topBid(auction)?.amount ?? auction.winning_bid ?? auction.starting_bid ?? 0) }
function finalPrice(auction) { return Number(auction.winning_bid ?? currentPrice(auction)) }
function winnerName(auction) { return topBid(auction)?.player?.nickname ?? auction.winner?.nickname ?? '—' }
function statusLabel(status) { return ({ active: 'Активен', draft: 'Черновик', finished: 'Завершён', cancelled: 'Отменён' })[status] ?? status }
function statusDescription(status) { return ({ active: 'Участники могут делать ставки до указанного времени', draft: 'Лот виден только управляющим и ещё не принимает ставки', finished: 'Победитель определён, предмет списан, эквивалент жетонов зачислен в казну', cancelled: 'Лот отменён, резерв предмета освобождён' })[status] ?? '' }

function countdown(endsAt) {
  const distance = Math.max(0, new Date(endsAt).getTime() - clock.value)
  const days = Math.floor(distance / 86400000)
  const hours = Math.floor((distance % 86400000) / 3600000)
  const minutes = Math.floor((distance % 3600000) / 60000)
  const seconds = Math.floor((distance % 60000) / 1000)
  return days ? `${days} д. ${hours} ч.` : `${hours} ч. ${minutes} мин. ${seconds} сек.`
}

function openForm() {
  editingId.value = null
  Object.assign(form, { treasury_item_id: '', quantity: 1, starting_bid: 0, minimum_step: 1, extension_minutes: 3, ends_at: localDateTime(new Date(Date.now() + 60 * 60 * 1000)) })
  error.value = ''
  showForm.value = true
}

function editDraft(lot) {
  editingId.value = lot.id
  Object.assign(form, { treasury_item_id: lot.treasury_item_id, quantity: Number(lot.quantity), starting_bid: Number(lot.starting_bid), minimum_step: Number(lot.minimum_step), extension_minutes:Number(lot.extension_minutes??3), ends_at: localDateTime(new Date(lot.ends_at) > new Date() ? lot.ends_at : new Date(Date.now() + 60 * 60 * 1000)) })
  error.value = ''
  showForm.value = true
}

async function loadAll(page = pagination.value.current_page) {
  loadError.value = ''
  try {
    const [auctionResponse, treasuryResponse, countResponse] = await Promise.all([api.get('/api/auctions', { params: { page, per_page: 12 } }), api.get('/api/treasury/items'), api.get('/api/auctions/active-count')])
    auctions.value = auctionResponse.data.data
    pagination.value = { current_page: auctionResponse.data.current_page, last_page: auctionResponse.data.last_page, total: auctionResponse.data.total }
    activeCount.value = Number(countResponse.data.count)
    items.value = treasuryResponse.data
    window.dispatchEvent(new CustomEvent('auction-count-changed', { detail: activeCount.value }))
  } catch (exception) {
    if (!auctions.value.length) loadError.value = exception.response?.data?.message ?? 'Сервер не ответил. Проверьте соединение и попробуйте снова.'
  } finally { loading.value = false }
}

async function retryLoad() { loading.value = true; await loadAll() }

async function save() {
  busy.value = true
  error.value = ''
  try {
    const payload = { ...form, treasury_item_id: Number(form.treasury_item_id), ends_at: moscowIso(form.ends_at) }
    if (editingId.value) await api.put(`/api/auctions/${editingId.value}`, payload)
    else await api.post('/api/auctions', payload)
    showForm.value = false
    await loadAll()
  } catch (exception) {
    error.value = Object.values(exception.response?.data?.errors ?? {}).flat()[0] ?? exception.response?.data?.message ?? 'Не удалось сохранить лот.'
  } finally { busy.value = false }
}

async function start(id) {
  try {
    await api.post(`/api/auctions/${id}/start`)
    await loadAll()
  } catch (exception) { error.value = exception.response?.data?.message ?? 'Не удалось запустить лот.' }
}

async function openAuctionModal(auction, mode = 'bid') {
  modalMode.value = mode
  modalError.value = ''
  modalBusy.value = true
  selectedAuction.value = auction
  try {
    selectedBidsPage.value = 1
    const response = await api.get(`/api/auctions/${auction.id}`, { params: { bids_page: 1, bids_per_page: 20 } })
    selectedAuction.value = response.data
    bidAmount.value = minimumBid.value
  } catch (exception) {
    modalError.value = exception.response?.data?.message ?? 'Не удалось загрузить аукцион.'
  } finally { modalBusy.value = false }
}

async function loadSelectedBids(page) {
  if (!selectedAuction.value) return
  modalBusy.value = true
  try { selectedAuction.value = (await api.get(`/api/auctions/${selectedAuction.value.id}`, { params: { bids_page: page, bids_per_page: 20 } })).data;selectedBidsPage.value=selectedAuction.value.bids_meta.current_page }
  catch (exception) { modalError.value = exception.response?.data?.message ?? 'Не удалось загрузить историю ставок.' }
  finally { modalBusy.value = false }
}

function closeAuctionModal() {
  selectedAuction.value = null
  modalError.value = ''
}

async function placeBid() {
  modalBusy.value = true
  modalError.value = ''
  try {
    await api.post(`/api/auctions/${selectedAuction.value.id}/bid`, { amount: Number(bidAmount.value) })
    selectedAuction.value=(await api.get(`/api/auctions/${selectedAuction.value.id}`, { params: { bids_page: 1, bids_per_page: 20 } })).data
    selectedBidsPage.value=1
    bidAmount.value=minimumBid.value
    await loadAll()
  } catch (exception) {
    modalError.value = Object.values(exception.response?.data?.errors ?? {}).flat()[0] ?? exception.response?.data?.message ?? 'Не удалось сделать ставку.'
  } finally { modalBusy.value = false }
}

onMounted(async () => {
  await loadAll()
  startPolling()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})
onUnmounted(() => {stopPolling();document.removeEventListener('visibilitychange', handleVisibilityChange)})
async function openArchive(){archive.value=(await api.get('/api/auctions/archive')).data}
</script>

<template>
  <section class="page auctions-page">
    <header class="page-heading split-heading">
      <div><p class="eyebrow">Экономика · аукцион</p><h1>Аукцион гильдии</h1></div>
      <div class="page-actions"><button @click="openArchive">Архив побед</button><button v-if="auth.canCreateAuctions" class="primary" @click="openForm">Добавить лот</button></div>
    </header>

    <p v-if="error" class="error-banner">{{ error }}</p>
    <div class="auction-count-pill">{{ activeCount }} активных лотов</div>

    <AsyncState :loading="loading" :error="loadError" :empty="!auctions.length" loading-text="Загружаем аукционы…" empty-title="Активных лотов нет" empty-text="Новые и недавно завершённые лоты появятся здесь." @retry="retryLoad"><template #action><button v-if="auth.canCreateAuctions" class="primary" type="button" @click="openForm">Добавить первый лот</button></template></AsyncState>
    <div v-if="!loading&&!loadError&&auctions.length" class="auction-grid-v2">
      <article v-for="auction in auctions" :key="auction.id" class="panel auction-lot-v2">
        <div class="auction-card-head">
          <div class="auction-item-compact">
            <img v-if="auction.item?.icon_url" :src="auction.item.icon_url" :alt="auction.item.item_name">
            <span v-else class="auction-item-placeholder">?</span>
              <div><strong>{{ auction.item?.item_name }}</strong><small>{{ auction.quantity }} шт.</small></div>
          </div>
          <span class="status-pill" :class="`status-${auction.status}`" :title="statusDescription(auction.status)">{{ statusLabel(auction.status) }}</span>
        </div>

        <div class="auction-price-grid">
          <div class="auction-price-cell"><small>Стартовая цена</small><TokenAmount :value="auction.starting_bid" /></div>
          <div v-if="auction.status !== 'finished'" class="auction-price-cell"><small>Текущая цена</small><TokenAmount :value="currentPrice(auction)" /></div>
          <div v-else class="auction-price-cell auction-final-price"><small>Цена выкупа</small><TokenAmount :value="finalPrice(auction)" /></div>
        </div>

        <div class="auction-card-meta">
          <span v-if="auction.status === 'active'">До конца: <b>{{ countdown(auction.ends_at) }}</b></span>
          <span v-else>Завершение: {{ displayDateTime(auction.ends_at) }}</span>
          <span>Ставок: {{ auction.bids_count ?? 0 }}</span>
          <span v-if="topBid(auction)" class="auction-leader-badge"><span aria-hidden="true">♛</span><PlayerAvatar :player="topBid(auction).player" size="small"/><small>Лидер</small><b>{{ winnerName(auction) }}</b></span>
        </div>

        <div v-if="auction.status === 'active'" class="auction-card-actions">
          <button class="auction-history-button" title="История ставок" aria-label="История ставок" @click="openAuctionModal(auction, 'history')">↶</button>
          <button class="primary auction-bid-button" @click="openAuctionModal(auction, 'bid')">Сделать ставку</button>
        </div>
        <div v-else-if="auction.status === 'draft' && auth.canCreateAuctions" class="auction-card-actions">
          <button class="secondary" @click="editDraft(auction)">Редактировать</button><button class="primary" @click="start(auction.id)">Запустить</button>
        </div>
        <div v-else class="auction-card-actions auction-card-actions-ended">
          <button class="auction-history-button" title="История ставок" aria-label="История ставок" @click="openAuctionModal(auction, 'history')">↶</button>
        </div>
      </article>
    </div>
    <nav v-if="!loading&&!loadError&&pagination.last_page > 1" class="roster-pagination" aria-label="Страницы аукционов"><button :disabled="pagination.current_page === 1" @click="loadAll(pagination.current_page - 1)">‹</button><button v-for="page in pageNumbers" :key="page" :class="{ active: page === pagination.current_page }" @click="loadAll(page)">{{ page }}</button><button :disabled="pagination.current_page === pagination.last_page" @click="loadAll(pagination.current_page + 1)">›</button></nav>

    <div v-if="selectedAuction" class="modal">
      <form v-if="modalMode === 'bid'" class="form-card auction-modal-card" @submit.prevent="placeBid">
        <header class="auction-modal-head"><h2>Сделать ставку</h2><button type="button" class="modal-close" @click="closeAuctionModal">×</button></header>
        <div class="auction-modal-item">
          <img v-if="selectedAuction.item?.icon_url" :src="selectedAuction.item.icon_url" :alt="selectedAuction.item.item_name">
          <strong>{{ selectedAuction.item?.item_name }}</strong>
        </div>
        <div class="auction-modal-prices">
          <div><span>Текущая ставка</span><TokenAmount :value="selectedTopBid?.amount ?? selectedAuction.starting_bid" /></div>
          <div><span>Минимальная ставка</span><TokenAmount :value="minimumBid" /></div>
        </div>
        <label>Ваша максимальная ставка, жетоны<input v-model.number="bidAmount" type="number" :min="minimumBid" step="1" required></label><p class="muted auction-proxy-note">Система автоматически повышает цену только на необходимый шаг. Ваш максимум другим игрокам не показывается.</p>
        <p v-if="modalError" class="error-banner">{{ modalError }}</p>
        <div class="form-actions"><button type="button" class="secondary" @click="closeAuctionModal">Отмена</button><button class="primary" :disabled="modalBusy">Подтвердить ставку</button></div>
      </form>

      <section v-else class="form-card auction-modal-card auction-history-modal">
        <header class="auction-modal-head"><div><h2>История ставок</h2><small>{{ selectedAuction.item?.item_name }}</small></div><button type="button" class="modal-close" @click="closeAuctionModal">×</button></header>
        <p v-if="modalBusy">Загрузка…</p>
        <div v-else-if="selectedAuction.bids?.length" class="auction-bid-list">
          <div v-for="(bid, index) in selectedAuction.bids" :key="bid.id" :class="{ 'top-bid-row': bid.id === selectedTopBid?.id }"><span class="bid-player"><b>#{{ (selectedBidsPage-1)*20+index+1 }}</b><PlayerAvatar :player="bid.player" size="tiny"/><span>{{ bid.player?.nickname ?? '—' }}</span><em v-if="bid.id === selectedTopBid?.id">♛ лидер</em><small v-if="bid.is_auto">авто</small></span><TokenAmount :value="bid.amount" /></div>
        </div>
        <p v-else class="muted">Ставок пока нет.</p>
        <nav v-if="selectedAuction.bids_meta?.last_page>1" class="roster-pagination" aria-label="Страницы истории ставок"><button :disabled="selectedBidsPage===1" @click="loadSelectedBids(selectedBidsPage-1)">‹</button><span>{{ selectedBidsPage }} / {{ selectedAuction.bids_meta.last_page }}</span><button :disabled="selectedBidsPage===selectedAuction.bids_meta.last_page" @click="loadSelectedBids(selectedBidsPage+1)">›</button></nav>
      </section>
    </div>

    <div v-if="showForm" class="modal" @click.self="showForm = false">
      <form class="form-card auction-create-card" @submit.prevent="save">
        <header class="modal-card-header"><h2>{{ editingId ? 'Редактировать лот' : 'Новый лот' }}</h2><button type="button" class="modal-close" aria-label="Закрыть" @click="showForm=false">×</button></header>
        <label>Предмет<select v-model="form.treasury_item_id" required><option disabled value="">Выберите предмет</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.item_name }} · доступно {{ item.available_quantity }}</option></select></label>
        <label>Количество<input v-model.number="form.quantity" type="number" min="1" required></label>
        <label>Стартовая цена, жетоны<input v-model.number="form.starting_bid" type="number" min="0" required></label>
        <label>Минимальный шаг, жетоны<input v-model.number="form.minimum_step" type="number" min="1" required></label>
        <label>Автопродление<input v-model.number="form.extension_minutes" type="number" min="2" max="5" required><small>На 2–5 минут при ставке перед закрытием</small></label>
        <label>Завершение<input v-model="form.ends_at" type="datetime-local" :min="minimumEndsAt()" required></label>
        <p v-if="error" class="error-banner">{{ error }}</p>
        <div class="form-actions"><button type="button" class="secondary" @click="showForm = false">Отмена</button><button class="primary" :disabled="busy">{{ busy ? 'Сохранение…' : editingId ? 'Сохранить' : 'Создать черновик' }}</button></div>
      </form>
    </div>
    <div v-if="archive" class="modal"><section class="form-card auction-archive"><header class="auction-modal-head"><h2>Архив побед и расходов</h2><button class="modal-close" @click="archive=null">×</button></header><div class="auction-archive-leaders"><article v-for="row in archive.players" :key="row.player_id"><strong>{{ row.nickname }}</strong><span>{{ row.wins }} побед</span><TokenAmount :value="formatInteger(row.spent)"/></article></div><div class="table-wrap flat"><table><thead><tr><th>Лот</th><th>Победитель</th><th>Итог</th><th>Дата</th></tr></thead><tbody><tr v-for="lot in archive.lots" :key="lot.id"><td>{{ lot.item?.item_name }}</td><td>{{ lot.winner?.nickname??'Без ставок' }}</td><td><TokenAmount v-if="lot.winning_bid!==null" :value="formatInteger(lot.winning_bid)"/><span v-else>—</span></td><td>{{ displayDateTime(lot.finished_at) }}</td></tr></tbody></table></div></section></div>
  </section>
</template>
