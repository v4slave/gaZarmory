<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import AppIcon from '../components/AppIcon.vue'
import AppModal from '../components/AppModal.vue'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'
import { useLocale } from '../i18n.js'
import { preloadManagementPages } from '../router/index.js'
import { canPreloadManagementPages } from '../router/access.js'
import { formatDateTime } from '../utils/format.js'
const auth = useAuthStore()
const route = useRoute()
const { t } = useLocale()
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
let playerLinkTicker
const primaryLinks = [
  { to: '/dashboard', label: 'Главная', icon: 'home' },
  { to: '/roster', label: 'Состав', icon: 'users' },
  { to: '/groups', label: 'Конст-пати', icon: 'groups' },
  { to: '/activities', label: 'Активности', icon: 'sword' },
  { to: '/media', label: 'Контент', icon: 'play' },
]
const economyLinks = [
  { to: '/treasury', label: 'Казна', icon: 'treasury' },
  { to: '/auctions', label: 'Аукционы', icon: 'auction' },
  { to: '/payouts', label: 'Нахрюк', icon: 'payout' },
]
async function loadActiveAuctions(){if(!auth.user?.player)return;try{activeAuctions.value=(await api.get('/api/auctions/active-count')).data.count}catch{activeAuctions.value=0}}
async function loadNotifications(){if(!auth.authenticated)return;try{const data=(await api.get('/api/notifications')).data;notificationItems.value=data.items;unreadNotifications.value=data.unread_count}catch{/* Background refresh retries on the next interval. */}}
async function markNotification(item){if(!item.read_at){await api.post(`/api/notifications/${item.id}/read`);item.read_at=new Date().toISOString();unreadNotifications.value=Math.max(0,unreadNotifications.value-1)}notificationOpen.value=false}
async function markAllNotifications(){await api.post('/api/notifications/read-all');notificationItems.value.forEach(item=>item.read_at??=new Date().toISOString());unreadNotifications.value=0}
function notificationIcon(type){return({link_request:'users',auction_started:'auction',auction_finished:'auction',auction_outbid:'warning',payout_calculated:'payout',insufficient_gold:'treasury',activity_upcoming:'sword'})[type]??'info'}
function updateAuctionCount(event){activeAuctions.value=Number(event.detail)||0}
watch(() => route.fullPath, () => { menuOpen.value = false })
watch(() => auth.authenticated, authenticated => {
  if(authenticated&&document.visibilityState==='visible')loadActiveAuctions()
  else if(!authenticated)activeAuctions.value=0
  updateNotificationPolling()
})
function stopNotificationPolling(){if(notificationTicker){window.clearInterval(notificationTicker);notificationTicker=undefined}}
function updateNotificationPolling(){
  stopNotificationPolling()
  if(auth.authenticated&&document.visibilityState==='visible'){
    loadNotifications()
    notificationTicker=window.setInterval(loadNotifications,30000)
  }
}
function stopPlayerLinkPolling(){if(playerLinkTicker){window.clearInterval(playerLinkTicker);playerLinkTicker=undefined}}
function updatePlayerLinkPolling(){
  stopPlayerLinkPolling()
  if(document.visibilityState==='visible'&&auth.authenticated&&!auth.user?.player&&auth.user?.pending_player_link_request){
    playerLinkTicker=window.setInterval(()=>auth.fetchMe().catch(()=>{}),5000)
  }
}
function handleVisibilityChange(){
  if(document.visibilityState==='visible'){
    auth.syncDiscordProfile()
    loadActiveAuctions()
  }
  updateNotificationPolling()
  updatePlayerLinkPolling()
}
watch(()=>[auth.authenticated,auth.user?.player?.id,auth.user?.pending_player_link_request?.id],updatePlayerLinkPolling,{immediate:true})
let managementPagesPreloaded = false
watch(() => auth.user, user => {
  const allowed = canPreloadManagementPages(user)
  if (!allowed || managementPagesPreloaded) return
  managementPagesPreloaded = true
  const preload = () => preloadManagementPages().catch(() => {})
  if ('requestIdleCallback' in window) window.requestIdleCallback(preload, { timeout: 2000 })
  else window.setTimeout(preload, 500)
}, { immediate: true })
onMounted(()=>{handleVisibilityChange();window.addEventListener('auction-count-changed',updateAuctionCount);document.addEventListener('visibilitychange',handleVisibilityChange)})
onBeforeUnmount(()=>{stopNotificationPolling();stopPlayerLinkPolling();window.removeEventListener('auction-count-changed',updateAuctionCount);document.removeEventListener('visibilitychange',handleVisibilityChange)})
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
  <LanguageSwitcher v-if="!auth.authenticated || !auth.user?.player" floating/>
  <div v-if="auth.loading" class="access-gate"><div class="access-card"><span class="access-loader"></span><p>{{ t('Проверяем авторизацию…') }}</p></div></div>
  <div v-else-if="auth.connectionError" class="access-gate"><div class="access-card connection-error-card"><AppIcon name="warning" :size="34"/><p class="eyebrow">{{ t('СЕРВЕР НЕДОСТУПЕН') }}</p><h1>{{ t('Не удалось подключиться к серверу') }}</h1><p class="muted">{{ t('Проверьте соединение или состояние сервера и попробуйте снова.') }}</p><div class="connection-error-actions"><button class="primary" type="button" @click="auth.loading=true;auth.fetchMe()">{{ t('Повторить') }}</button><a class="secondary" :href="`${api.defaults.baseURL}/up`" target="_blank" rel="noopener">{{ t('Проверить статус') }}</a></div></div></div>
  <div v-else-if="!auth.authenticated" class="access-gate"><div class="access-card guest-card"><img src="/hamster-armory.png" :alt="t('Хомяк GAZ ARMORY')"><p class="eyebrow">ARCHEAGE GUILD MANAGEMENT</p><h1>GAZ ARMORY</h1><button class="primary access-primary" @click="auth.login">{{ t('Войти через Discord') }}</button></div></div>
  <div v-else-if="!auth.user?.player" class="access-gate"><div class="access-card"><img src="/hamster-armory.png" :alt="t('Хомяк GAZ ARMORY')"><p class="eyebrow">{{ t('ПЕРВЫЙ ВХОД') }}</p><template v-if="auth.user?.pending_player_link_request"><h1>{{ t('Заявка отправлена') }}</h1><p class="muted">{{ t('Персонаж') }} «{{ auth.user.pending_player_link_request.player?.nickname }}». {{ t('Дождитесь подтверждения ГЛ или администратора.') }}</p><p class="muted">{{ t('Статус проверяется автоматически — доступ откроется без обновления страницы.') }}</p></template><template v-else><h1>{{ t('Привяжите персонажа') }}</h1><p class="muted">{{ t('Выберите персонажа и отправьте заявку. Разделы гильдии откроются после подтверждения.') }}</p><button class="primary access-primary" @click="openLinker">{{ t('Выбрать персонажа') }}</button></template><button class="access-logout" @click="auth.logout">{{ t('Выйти') }}</button></div></div>
  <div v-else class="shell">
    <aside :class="{ open: menuOpen }">
      <div class="brand">
        <img src="/hamster-armory.png" :alt="t('Хомяк GAZ ARMORY')">
        <div>GAZ ARMORY<small>ArcheAge guild</small></div>
      </div>
      <nav><div class="nav-section"><span class="nav-section-title">{{ t('Основное') }}</span><RouterLink v-for="link in primaryLinks" :key="link.to" :to="link.to"><i class="nav-icon" aria-hidden="true"><AppIcon :name="link.icon"/></i><span>{{ t(link.label) }}</span></RouterLink></div><div class="nav-section"><span class="nav-section-title">{{ t('Экономика') }}</span><RouterLink v-for="link in economyLinks" :key="link.to" :to="link.to"><i class="nav-icon" aria-hidden="true"><AppIcon :name="link.icon"/></i><span>{{ t(link.label) }}</span><b v-if="link.to==='/auctions'&&activeAuctions" class="nav-count">{{ activeAuctions }}</b></RouterLink></div></nav>
      <nav class="management-nav"><div class="nav-section"><span class="nav-section-title">{{ t('Управление') }}</span>
        <RouterLink v-if="auth.canViewReadiness" class="admin" to="/roster-readiness"><i class="nav-icon" aria-hidden="true">◉</i><span>{{ t('Готовность состава') }}</span></RouterLink>
        <RouterLink v-if="auth.canViewReadiness" class="admin" to="/attendance-analytics"><i class="nav-icon" aria-hidden="true">↗</i><span>{{ t('Посещаемость') }}</span></RouterLink>
        <RouterLink v-if="auth.canHandleTreasuryItems" class="admin" to="/financial-reconciliation"><i class="nav-icon" aria-hidden="true">✓</i><span>{{ t('Финансовая сверка') }}</span></RouterLink>
        <RouterLink v-if="auth.canAdmin" class="admin" to="/admin"><i class="nav-icon" aria-hidden="true">⚙</i><span>{{ t('Админка') }}</span></RouterLink>
      </div></nav>
      <RouterLink v-if="auth.user?.player" class="aside-profile" :to="`/players/${auth.user.player.id}`">
        <PlayerAvatar :player="{ nickname: auth.user.player.nickname, user: auth.user }"/>
        <span><strong>{{ auth.user.discord_display_name || auth.user.discord_username }}</strong></span>
        <b aria-hidden="true">›</b>
      </RouterLink>
    </aside>
    <button v-if="menuOpen" class="mobile-nav-backdrop" type="button" :aria-label="t('Закрыть меню')" @click="menuOpen=false"></button>
    <main>
      <header>
        <button class="mobile-menu-button" type="button" :aria-expanded="menuOpen" :aria-label="t('Открыть меню')" @click="menuOpen=!menuOpen"><span></span><span></span><span></span></button>
        <RouterLink class="mobile-brand" to="/dashboard">GAZ ARMORY</RouterLink>
        <div class="header-spacer"></div>
        <LanguageSwitcher/>
        <div v-if="auth.authenticated" class="notification-center"><button class="notification-bell" type="button" :aria-label="t('Уведомления')" @click="notificationOpen=!notificationOpen;loadNotifications()"><AppIcon name="bell"/><b v-if="unreadNotifications">{{ unreadNotifications>99?'99+':unreadNotifications }}</b></button><div v-if="notificationOpen" class="notification-popover"><header><div><h2>{{ t('Уведомления') }}</h2><small>{{ unreadNotifications }} {{ t('непрочитанных') }}</small></div><button v-if="unreadNotifications" @click="markAllNotifications">{{ t('Прочитать все') }}</button></header><div v-if="notificationItems.length" class="notification-list"><component :is="item.data.url?'RouterLink':'div'" v-for="item in notificationItems" :key="item.id" :to="item.data.url||undefined" :class="{unread:!item.read_at}" @click="markNotification(item)"><span><AppIcon :name="notificationIcon(item.type)"/></span><div><strong>{{ t(item.data.title) }}</strong><p>{{ t(item.data.message) }}</p><small>{{ formatDateTime(item.created_at) }}</small></div></component></div><p v-else class="empty">{{ t('Уведомлений пока нет.') }}</p></div></div>
        <RouterLink v-if="auth.user?.player" class="user-link header-user-profile" :to="`/players/${auth.user.player.id}`">{{ auth.user.discord_display_name || auth.user.discord_username }}</RouterLink>
        <button v-else-if="auth.user" class="user-unlinked" :title="t('Привязать игровой профиль')" @click="openLinker">{{ auth.user.discord_display_name || auth.user.discord_username }} · {{ t('привязать профиль') }}</button>
        <button v-if="auth.authenticated" @click="auth.logout">{{ t('Выйти') }}</button>
        <button v-else-if="!auth.loading" class="primary" @click="auth.login">{{ t('Войти через Discord') }}</button>
      </header>
      <RouterView :key="route.fullPath" />
    </main>
  </div>
  <AppModal :open="showLinker" title="Привязать игровой профиль" @close="showLinker=false">
    <form class="form-card" @submit.prevent="linkProfile">
      <h2>{{ t('Привязать игровой профиль') }}</h2>
      <p class="muted">{{ t('Выберите своего персонажа. Заявку проверит ГЛ или администратор.') }}</p>
      <label>{{ t('Игровой никнейм') }}
        <select v-model="selectedPlayerId" required>
          <option value="" disabled>{{ t('Выберите персонажа') }}</option>
          <option v-for="player in freePlayers" :key="player.id" :value="player.id">{{ player.nickname }}</option>
        </select>
      </label>
      <p v-if="!playerOptionsLoading&&!freePlayers.length" class="empty">{{ t('Свободных активных профилей не найдено.') }}</p>
      <p v-if="linkError" class="notice error">{{ t(linkError) }}</p>
      <div class="form-actions"><button type="button" @click="showLinker=false">{{ t('Отмена') }}</button><button class="primary" :disabled="linking||!selectedPlayerId">{{ t(linking?'Отправка…':'Отправить заявку') }}</button></div>
    </form>
  </AppModal>
</template>
