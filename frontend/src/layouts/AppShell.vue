<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import { useLocale } from '../i18n.js'
const auth = useAuthStore()
const route = useRoute()
const { locale, setLocale } = useLocale()
const menuOpen = ref(false)
const showLinker = ref(false)
const selectedPlayerId = ref('')
const linking = ref(false)
const linkError = ref('')
const activeAuctions = ref(0)
const freePlayers = ref([])
const playerOptionsLoading = ref(false)
const notificationOpen=ref(false),notificationItems=ref([]),unreadNotifications=ref(0)
let notificationTicker
const primaryLinks = [
  { to: '/dashboard', label: 'Дашборд', icon: '⌂' },
  { to: '/roster', label: 'Состав', icon: '♙' },
  { to: '/groups', label: 'Конст-пати', icon: '♜' },
  { to: '/activities', label: 'Активности', icon: '⚔' },
]
const economyLinks = [
  { to: '/treasury', label: 'Казна', icon: '▣' },
  { to: '/auctions', label: 'Аукционы', icon: '♢' },
  { to: '/payouts', label: 'Нахрюк', icon: '◫' },
]
async function loadActiveAuctions(){if(!auth.user?.player)return;try{activeAuctions.value=(await api.get('/api/auctions/active-count')).data.count}catch{activeAuctions.value=0}}
async function loadNotifications(){if(!auth.authenticated)return;try{const data=(await api.get('/api/notifications')).data;notificationItems.value=data.items;unreadNotifications.value=data.unread_count}catch{}}
async function markNotification(item){if(!item.read_at){await api.post(`/api/notifications/${item.id}/read`);item.read_at=new Date().toISOString();unreadNotifications.value=Math.max(0,unreadNotifications.value-1)}notificationOpen.value=false}
async function markAllNotifications(){await api.post('/api/notifications/read-all');notificationItems.value.forEach(item=>item.read_at??=new Date().toISOString());unreadNotifications.value=0}
function notificationIcon(type){return({link_request:'♙',auction_started:'♢',auction_finished:'◆',auction_outbid:'!',payout_calculated:'◫',insufficient_gold:'▣',activity_upcoming:'⚔'})[type]??'•'}
function updateAuctionCount(event){activeAuctions.value=Number(event.detail)||0}
watch(() => route.fullPath, () => { menuOpen.value = false;loadActiveAuctions() })
watch(() => auth.authenticated, authenticated => { if(authenticated)loadActiveAuctions();else activeAuctions.value=0 })
onMounted(()=>{loadActiveAuctions();loadNotifications();notificationTicker=window.setInterval(loadNotifications,30000);window.addEventListener('auction-count-changed',updateAuctionCount)})
onBeforeUnmount(()=>{window.clearInterval(notificationTicker);window.removeEventListener('auction-count-changed',updateAuctionCount)})
async function openLinker() {
  showLinker.value = true
  linkError.value = ''
  playerOptionsLoading.value = true
  try { freePlayers.value = (await api.get('/api/me/player-options')).data }
  catch (error) { linkError.value = error.response?.data?.message ?? 'Не удалось загрузить список персонажей.' }
  finally { playerOptionsLoading.value = false }
}
async function linkProfile() {
  if (!selectedPlayerId.value) return
  linking.value = true; linkError.value = ''
  try { await auth.requestPlayerLink(selectedPlayerId.value); showLinker.value = false }
  catch (error) { linkError.value = error.response?.data?.message ?? 'Не удалось привязать игровой профиль.' }
  finally { linking.value = false }
}
</script>

<template>
  <div v-if="!auth.authenticated || !auth.user?.player" class="language-switcher language-switcher-floating" role="group" aria-label="Язык интерфейса">
    <button type="button" :class="{ active: locale === 'ru' }" :aria-pressed="locale === 'ru'" @click="setLocale('ru')">RU</button>
    <button type="button" :class="{ active: locale === 'en' }" :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
  </div>
  <div v-if="auth.loading" class="access-gate"><div class="access-card"><span class="access-loader"></span><p>Проверяем авторизацию…</p></div></div>
  <div v-else-if="!auth.authenticated" class="access-gate"><div class="access-card guest-card"><img src="/hamster-armory.png" alt="Хомяк GAZ ARMORY"><p class="eyebrow">ARCHEAGE GUILD MANAGEMENT</p><h1>GAZ ARMORY</h1><button class="primary access-primary" @click="auth.login">Войти через Discord</button></div></div>
  <div v-else-if="!auth.user?.player" class="access-gate"><div class="access-card"><img src="/hamster-armory.png" alt="Хомяк GAZ ARMORY"><p class="eyebrow">ПЕРВЫЙ ВХОД</p><template v-if="auth.user?.pending_player_link_request"><h1>Заявка отправлена</h1><p class="muted">Персонаж «{{ auth.user.pending_player_link_request.player?.nickname }}». Дождитесь подтверждения ГЛ или администратора.</p></template><template v-else><h1>Привяжите персонажа</h1><p class="muted">Выберите персонажа и отправьте заявку. Разделы гильдии откроются после подтверждения.</p><button class="primary access-primary" @click="openLinker">Выбрать персонажа</button></template><button class="access-logout" @click="auth.logout">Выйти</button></div></div>
  <div v-else class="shell">
    <aside :class="{ open: menuOpen }">
      <div class="brand">
        <img src="/hamster-armory.png" alt="Хомяк GAZ ARMORY">
        <div>GAZ ARMORY<small>ArcheAge guild</small></div>
      </div>
      <nav><div class="nav-section"><span class="nav-section-title">Основное</span><RouterLink v-for="link in primaryLinks" :key="link.to" :to="link.to"><i class="nav-icon" aria-hidden="true">{{ link.icon }}</i><span>{{ link.label }}</span></RouterLink></div><div class="nav-section"><span class="nav-section-title">Экономика</span><RouterLink v-for="link in economyLinks" :key="link.to" :to="link.to"><i class="nav-icon" aria-hidden="true">{{ link.icon }}</i><span>{{ link.label }}</span><b v-if="link.to==='/auctions'&&activeAuctions" class="nav-count">{{ activeAuctions }}</b></RouterLink></div></nav>
      <RouterLink v-if="auth.canViewReadiness" class="admin readiness-nav" to="/roster-readiness"><i class="nav-icon" aria-hidden="true">◉</i><span>Готовность состава</span></RouterLink>
      <RouterLink v-if="auth.canViewReadiness" class="admin" to="/attendance-analytics"><i class="nav-icon" aria-hidden="true">↗</i><span>Посещаемость</span></RouterLink>
      <RouterLink v-if="auth.canHandleTreasuryItems" class="admin" to="/financial-reconciliation"><i class="nav-icon" aria-hidden="true">✓</i><span>Финансовая сверка</span></RouterLink>
      <RouterLink v-if="auth.canAdmin" class="admin" to="/admin"><i class="nav-icon" aria-hidden="true">⚙</i><span>Админка</span></RouterLink>
      <RouterLink v-if="auth.user?.player" class="aside-profile" :to="`/players/${auth.user.player.id}`">
        <PlayerAvatar :player="{ nickname: auth.user.player.nickname, user: auth.user }"/>
        <span><strong>{{ auth.user.discord_display_name || auth.user.discord_username }}</strong><small>{{ auth.canAdmin ? 'Управление гильдией' : 'Участник гильдии' }}</small></span>
        <b aria-hidden="true">›</b>
      </RouterLink>
    </aside>
    <button v-if="menuOpen" class="mobile-nav-backdrop" type="button" aria-label="Закрыть меню" @click="menuOpen=false"></button>
    <main>
      <header>
        <button class="mobile-menu-button" type="button" :aria-expanded="menuOpen" aria-label="Открыть меню" @click="menuOpen=!menuOpen"><span></span><span></span><span></span></button>
        <RouterLink class="mobile-brand" to="/dashboard">GAZ ARMORY</RouterLink>
        <div class="header-spacer"></div>
        <div class="language-switcher" role="group" aria-label="Язык интерфейса">
          <button type="button" :class="{ active: locale === 'ru' }" :aria-pressed="locale === 'ru'" @click="setLocale('ru')">RU</button>
          <button type="button" :class="{ active: locale === 'en' }" :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
        </div>
        <div v-if="auth.authenticated" class="notification-center"><button class="notification-bell" type="button" aria-label="Уведомления" @click="notificationOpen=!notificationOpen;loadNotifications()">🔔<b v-if="unreadNotifications">{{ unreadNotifications>99?'99+':unreadNotifications }}</b></button><div v-if="notificationOpen" class="notification-popover"><header><div><h2>Уведомления</h2><small>{{ unreadNotifications }} непрочитанных</small></div><button v-if="unreadNotifications" @click="markAllNotifications">Прочитать все</button></header><div v-if="notificationItems.length" class="notification-list"><component :is="item.data.url?'RouterLink':'div'" v-for="item in notificationItems" :key="item.id" :to="item.data.url||undefined" :class="{unread:!item.read_at}" @click="markNotification(item)"><span>{{ notificationIcon(item.type) }}</span><div><strong>{{ item.data.title }}</strong><p>{{ item.data.message }}</p><small>{{ new Date(item.created_at).toLocaleString('ru-RU') }}</small></div></component></div><p v-else class="empty">Уведомлений пока нет.</p></div></div>
        <RouterLink v-if="auth.user?.player" class="user-link header-user-profile" :to="`/players/${auth.user.player.id}`">{{ auth.user.discord_display_name || auth.user.discord_username }}</RouterLink>
        <button v-else-if="auth.user" class="user-unlinked" title="Привязать игровой профиль" @click="openLinker">{{ auth.user.discord_display_name || auth.user.discord_username }} · привязать профиль</button>
        <button v-if="auth.authenticated" @click="auth.logout">Выйти</button>
        <button v-else-if="!auth.loading" class="primary" @click="auth.login">Войти через Discord</button>
      </header>
      <RouterView :key="route.fullPath" />
    </main>
  </div>
  <div v-if="showLinker" class="modal" @click.self="showLinker=false">
    <form class="form-card" @submit.prevent="linkProfile">
      <h2>Привязать игровой профиль</h2>
      <p class="muted">Выберите своего персонажа. Заявку проверит ГЛ или администратор.</p>
      <label>Игровой никнейм
        <select v-model="selectedPlayerId" required>
          <option value="" disabled>Выберите персонажа</option>
          <option v-for="player in freePlayers" :key="player.id" :value="player.id">{{ player.nickname }}</option>
        </select>
      </label>
      <p v-if="!playerOptionsLoading&&!freePlayers.length" class="empty">Свободных активных профилей не найдено.</p>
      <p v-if="linkError" class="notice error">{{ linkError }}</p>
      <div class="form-actions"><button type="button" @click="showLinker=false">Отмена</button><button class="primary" :disabled="linking||!selectedPlayerId">{{ linking?'Отправка…':'Отправить заявку' }}</button></div>
    </form>
  </div>
</template>
