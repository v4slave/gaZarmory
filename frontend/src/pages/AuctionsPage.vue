<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'
import GoldAmount from '../components/GoldAmount.vue'

const auth = useAuthStore()
const auctions = ref([])
const items = ref([])
const showForm = ref(false)
const editingId = ref(null)
const error = ref('')
const busy = ref(false)
const clock = ref(Date.now())
const selectedAuction = ref(null)
const modalMode = ref('bid')
const bidAmount = ref(0)
const modalError = ref('')
const modalBusy = ref(false)
let ticker

const form = reactive({ treasury_item_id: '', quantity: 1, starting_bid: 0, minimum_step: 1, ends_at: '' })
const activeCount = computed(() => auctions.value.filter((auction) => auction.status === 'active').length)
const selectedTopBid = computed(() => selectedAuction.value?.bids?.[0] ?? null)
const minimumBid = computed(() => selectedTopBid.value
  ? Number(selectedTopBid.value.amount) + Number(selectedAuction.value.minimum_step)
  : Number(selectedAuction.value?.starting_bid ?? 0))

function localDateTime(date) {
  return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Moscow', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(date)).replace(' ', 'T')
}

function displayDateTime(date) {
  return new Intl.DateTimeFormat('ru-RU', { timeZone: 'Europe/Moscow', dateStyle: 'short', timeStyle: 'medium' }).format(new Date(date))
}

function moscowIso(localValue) { return `${localValue}:00+03:00` }
function minimumEndsAt() { return localDateTime(new Date(Math.ceil((Date.now() + 10 * 60 * 1000) / 60000) * 60000)) }
function topBid(auction) { return auction.top_bid ?? null }
function currentPrice(auction) { return Number(topBid(auction)?.amount ?? auction.winning_bid ?? auction.starting_bid ?? 0) }
function finalPrice(auction) { return Number(auction.winning_bid ?? currentPrice(auction)) }
function winnerName(auction) { return topBid(auction)?.player?.nickname ?? auction.winner?.nickname ?? '—' }
function statusLabel(status) { return ({ active: 'Активен', draft: 'Черновик', finished: 'Завершён', cancelled: 'Отменён' })[status] ?? status }
function statusDescription(status) { return ({ active: 'Участники могут делать ставки до указанного времени', draft: 'Лот виден только управляющим и ещё не принимает ставки', finished: 'Победитель определён, предмет и золото проведены по казне', cancelled: 'Лот отменён, резерв предмета освобождён' })[status] ?? '' }

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
  Object.assign(form, { treasury_item_id: '', quantity: 1, starting_bid: 0, minimum_step: 1, ends_at: localDateTime(new Date(Date.now() + 60 * 60 * 1000)) })
  error.value = ''
  showForm.value = true
}

function editDraft(lot) {
  editingId.value = lot.id
  Object.assign(form, { treasury_item_id: lot.treasury_item_id, quantity: Number(lot.quantity), starting_bid: Number(lot.starting_bid), minimum_step: Number(lot.minimum_step), ends_at: localDateTime(new Date(lot.ends_at) > new Date() ? lot.ends_at : new Date(Date.now() + 60 * 60 * 1000)) })
  error.value = ''
  showForm.value = true
}

async function loadAll() {
  const [auctionResponse, treasuryResponse] = await Promise.all([api.get('/api/auctions'), api.get('/api/treasury')])
  auctions.value = auctionResponse.data
  items.value = treasuryResponse.data.items
  window.dispatchEvent(new CustomEvent('auction-count-changed', { detail: activeCount.value }))
}

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
    const response = await api.get(`/api/auctions/${auction.id}`)
    selectedAuction.value = response.data
    bidAmount.value = minimumBid.value
  } catch (exception) {
    modalError.value = exception.response?.data?.message ?? 'Не удалось загрузить аукцион.'
  } finally { modalBusy.value = false }
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
    closeAuctionModal()
    await loadAll()
  } catch (exception) {
    modalError.value = Object.values(exception.response?.data?.errors ?? {}).flat()[0] ?? exception.response?.data?.message ?? 'Не удалось сделать ставку.'
  } finally { modalBusy.value = false }
}

onMounted(async () => {
  await loadAll()
  ticker = window.setInterval(() => { clock.value = Date.now() }, 1000)
})
onUnmounted(() => window.clearInterval(ticker))
</script>

<template>
  <section class="page auctions-page">
    <header class="page-heading split-heading">
      <div><p class="eyebrow">Экономика · аукцион</p><h1>Аукцион гильдии</h1></div>
      <button v-if="auth.canCreateAuctions" class="primary" @click="openForm">Добавить лот</button>
    </header>

    <p v-if="error" class="error-banner">{{ error }}</p>
    <div class="auction-count-pill">{{ activeCount }} активных лотов</div>

    <div class="auction-grid-v2">
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
          <div class="auction-price-cell"><small>Стартовая цена</small><GoldAmount :value="auction.starting_bid" /></div>
          <div v-if="auction.status !== 'finished'" class="auction-price-cell"><small>Текущая цена</small><GoldAmount :value="currentPrice(auction)" /></div>
          <div v-else class="auction-price-cell auction-final-price"><small>Цена выкупа</small><GoldAmount :value="finalPrice(auction)" /></div>
        </div>

        <div class="auction-card-meta">
          <span v-if="auction.status === 'active'">До конца: <b>{{ countdown(auction.ends_at) }}</b></span>
          <span v-else>Завершение: {{ displayDateTime(auction.ends_at) }}</span>
          <span>Ставок: {{ auction.bids_count ?? 0 }}</span>
          <span v-if="topBid(auction)">Лидер: {{ winnerName(auction) }}</span>
        </div>

        <div v-if="auction.status === 'active'" class="auction-card-actions">
          <button class="auction-history-button" title="История ставок" aria-label="История ставок" @click="openAuctionModal(auction, 'history')">↶</button>
          <button class="primary auction-bid-button" @click="openAuctionModal(auction, 'bid')">Сделать ставку</button>
        </div>
        <div v-else-if="auction.status === 'draft' && auth.canManage" class="auction-card-actions">
          <button class="secondary" @click="editDraft(auction)">Редактировать</button><button class="primary" @click="start(auction.id)">Запустить</button>
        </div>
        <div v-else class="auction-card-actions auction-card-actions-ended">
          <button class="auction-history-button" title="История ставок" aria-label="История ставок" @click="openAuctionModal(auction, 'history')">↶</button>
        </div>
      </article>
      <div v-if="!auctions.length" class="panel empty-state">Активных и недавно завершённых лотов нет.</div>
    </div>

    <div v-if="selectedAuction" class="modal" @click.self="closeAuctionModal">
      <form v-if="modalMode === 'bid'" class="form-card auction-modal-card" @submit.prevent="placeBid">
        <header class="auction-modal-head"><h2>Сделать ставку</h2><button type="button" class="modal-close" @click="closeAuctionModal">×</button></header>
        <div class="auction-modal-item">
          <img v-if="selectedAuction.item?.icon_url" :src="selectedAuction.item.icon_url" :alt="selectedAuction.item.item_name">
          <strong>{{ selectedAuction.item?.item_name }}</strong>
        </div>
        <div class="auction-modal-prices">
          <div><span>Текущая ставка</span><GoldAmount :value="selectedTopBid?.amount ?? selectedAuction.starting_bid" /></div>
          <div><span>Минимальная ставка</span><GoldAmount :value="minimumBid" /></div>
        </div>
        <label>Ваша ставка, золото<input v-model.number="bidAmount" type="number" :min="minimumBid" step="1" required></label>
        <p v-if="modalError" class="error-banner">{{ modalError }}</p>
        <div class="form-actions"><button type="button" class="secondary" @click="closeAuctionModal">Отмена</button><button class="primary" :disabled="modalBusy">Подтвердить ставку</button></div>
      </form>

      <section v-else class="form-card auction-modal-card auction-history-modal">
        <header class="auction-modal-head"><div><h2>История ставок</h2><small>{{ selectedAuction.item?.item_name }}</small></div><button type="button" class="modal-close" @click="closeAuctionModal">×</button></header>
        <p v-if="modalBusy">Загрузка…</p>
        <div v-else-if="selectedAuction.bids?.length" class="auction-bid-list">
          <div v-for="(bid, index) in selectedAuction.bids" :key="bid.id"><span><b>#{{ index + 1 }}</b> {{ bid.player?.nickname ?? '—' }}</span><GoldAmount :value="bid.amount" /></div>
        </div>
        <p v-else class="muted">Ставок пока нет.</p>
      </section>
    </div>

    <div v-if="showForm" class="modal" @click.self="showForm = false">
      <form class="form-card auction-create-card" @submit.prevent="save">
        <h2>{{ editingId ? 'Редактировать лот' : 'Новый лот' }}</h2>
        <label>Предмет<select v-model="form.treasury_item_id" required><option disabled value="">Выберите предмет</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.item_name }} · доступно {{ item.available_quantity }}</option></select></label>
        <label>Количество<input v-model.number="form.quantity" type="number" min="1" required></label>
        <label>Стартовая цена<input v-model.number="form.starting_bid" type="number" min="0" required></label>
        <label>Минимальный шаг<input v-model.number="form.minimum_step" type="number" min="1" required></label>
        <label>Завершение<input v-model="form.ends_at" type="datetime-local" :min="minimumEndsAt()" required></label>
        <p v-if="error" class="error-banner">{{ error }}</p>
        <div class="form-actions"><button type="button" class="secondary" @click="showForm = false">Отмена</button><button class="primary" :disabled="busy">{{ busy ? 'Сохранение…' : editingId ? 'Сохранить' : 'Создать черновик' }}</button></div>
      </form>
    </div>
  </section>
</template>
