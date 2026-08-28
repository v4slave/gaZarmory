<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'
import { formatDate, formatDecimal, formatInteger } from '../utils/format.js'

const route = useRoute(); const player = ref(null); const error = ref('')
const auth = useAuthStore(); const showEditor = ref(false); const savingProfile = ref(false); const nickname = ref(''); const selectedClass = ref(''); const gearScore = ref(0)
const bannerFile = ref(null); const bannerPreviewUrl = ref(''); const removeBanner = ref(false)
const notifications = useNotificationsStore()
const labels = { melee:'Милик', archer:'Лучник', mage:'Маг', healer:'Хил', bard:'Бард', tank:'Танк' }
const assetLabels = { has_ship:'Корабль', has_tank:'Танк', has_fuchsias:'Фуксория', has_clouds:'Облачко', has_machaon:'Махаон', has_tare:'Таре', has_deer:'Олень', has_invulnerable_pet:'Пет на неуяз', has_shield_swap:'Щит на свап', has_flippers:'Ласты' }
const assetImages = { has_ship:'/images/profile-assets/ship.png', has_tank:'/images/profile-assets/tank.png', has_fuchsias:'/images/profile-assets/fuchsoria.png', has_clouds:'/images/profile-assets/cloud.png', has_machaon:'/images/profile-assets/machaon.png', has_tare:'/images/profile-assets/tare.png', has_deer:'/images/profile-assets/deer.png', has_invulnerable_pet:'/images/profile-assets/invulnerable-pet.png', has_shield_swap:'/images/profile-assets/shield-swap.png', has_flippers:'/images/profile-assets/flippers.png' }
const assetImagePositions = { has_ship:'30%', has_tank:'28%', has_fuchsias:'28%', has_clouds:'30%', has_machaon:'30%', has_tare:'27%', has_deer:'25%', has_invulnerable_pet:'32%', has_shield_swap:'38%', has_flippers:'42%' }
const assets = reactive(Object.fromEntries(Object.keys(assetLabels).map(key => [key, false])))
const activities = computed(() => (player.value?.activities ?? []).filter(item => item.definition?.type !== 'mini_activity'))
const statistics = computed(() => player.value?.statistics ?? {})
const earnings = computed(() => (player.value?.earnings_history ?? []).filter(item => item.activity?.definition?.type !== 'mini_activity'))
const earningStatus = { pending:'Ожидается', paid:'Выплачено', cancelled:'Отменено' }
const isOwnProfile = computed(() => Number(auth.user?.player?.id) === Number(player.value?.id))
const isDeveloper = computed(() => (auth.user?.roles ?? [auth.user?.role]).includes('developer'))
const canEditProfile = computed(() => isOwnProfile.value || isDeveloper.value)
const gearDelta = computed(() => player.value?.previous_gear_score === null || player.value?.previous_gear_score === undefined ? null : Number(player.value.gear_score) - Number(player.value.previous_gear_score))
const gearDeltaText = computed(() => gearDelta.value === null ? 'Изменений пока нет' : `${gearDelta.value >= 0 ? '+' : '−'}${formatInteger(Math.abs(gearDelta.value))} с прошлого обновления`)
const activeBannerUrl = computed(() => bannerPreviewUrl.value || (!removeBanner.value && player.value?.banner_url) || '/images/gaz-armory-background.png')
const profileBannerStyle = computed(() => ({ backgroundImage: `linear-gradient(100deg,rgba(16,10,5,.96),rgba(30,19,9,.78)),url("${activeBannerUrl.value}")` }))

function syncEditor() { nickname.value = player.value.nickname; selectedClass.value = player.value.class; gearScore.value = Number(player.value.gear_score ?? 0); Object.keys(assetLabels).forEach(key => { assets[key] = Boolean(player.value[key]) }) }
async function loadPlayer(id) { player.value = null; error.value = ''; try { player.value = (await api.get(`/api/players/${id}`)).data; syncEditor() } catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить профиль.' } }
watch(() => route.params.id, id => loadPlayer(id), { immediate:true })
function clearBannerSelection() { if (bannerPreviewUrl.value) URL.revokeObjectURL(bannerPreviewUrl.value); bannerPreviewUrl.value = ''; bannerFile.value = null }
function openEditor() { syncEditor(); clearBannerSelection(); removeBanner.value = false; error.value = ''; showEditor.value = true }
function selectBanner(event) {
  clearBannerSelection()
  const file = event.target.files?.[0]
  if (!file) return
  if (!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type) || file.size > 10 * 1024 * 1024) {
    error.value = 'Выберите JPG, PNG, WebP или GIF размером до 10 МБ.'; event.target.value = ''; return
  }
  bannerFile.value = file; bannerPreviewUrl.value = URL.createObjectURL(file); removeBanner.value = false; error.value = ''
}
function markBannerForRemoval() { clearBannerSelection(); removeBanner.value = true }
onBeforeUnmount(clearBannerSelection)
async function saveProfile() {
  savingProfile.value = true; error.value = ''
  try {
    if (!isOwnProfile.value && isDeveloper.value) {
      await api.patch(`/api/players/${player.value.id}/profile`, { nickname: nickname.value, class: selectedClass.value, gear_score: Number(gearScore.value), ...assets })
    } else {
      const requests = []
      if (nickname.value !== player.value.nickname) requests.push(api.patch('/api/me/player/nickname', { nickname: nickname.value }))
      if (selectedClass.value !== player.value.class) requests.push(api.patch('/api/me/player/class', { class: selectedClass.value }))
      requests.push(api.patch('/api/me/player/profile', { gear_score: Number(gearScore.value), ...assets }))
      await Promise.all(requests)
      await auth.fetchMe()
    }
    if (bannerFile.value) {
      const form = new FormData(); form.append('banner', bannerFile.value)
      await api.post(`/api/players/${player.value.id}/banner`, form)
    } else if (removeBanner.value && player.value.banner_url) {
      await api.delete(`/api/players/${player.value.id}/banner`)
    }
    player.value = (await api.get(`/api/players/${route.params.id}`)).data
    clearBannerSelection(); removeBanner.value = false
    showEditor.value = false
    notifications.success('Профиль персонажа сохранён.')
  } catch (e) { error.value = apiErrorMessage(e,'Не удалось сохранить профиль.'); notifications.error(error.value) }
  finally { savingProfile.value = false }
}
</script>

<template><section v-if="player" class="player-profile-page">
  <p v-if="error" class="notice error">{{ error }}</p>
  <div class="profile-showcase" :style="profileBannerStyle">
    <div class="profile-showcase-identity"><PlayerAvatar :player="player" size="hero"/><div><p class="eyebrow">ПРОФИЛЬ ИГРОКА</p><h1>{{ player.nickname }} <span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></h1><p>{{ player.group?.name ?? 'Сольники' }}</p><a v-if="player.user?.discord_id" class="profile-discord-card" :href="`https://discord.com/users/${player.user.discord_id}`" target="_blank" rel="noopener noreferrer" :title="`Открыть личные сообщения с ${player.nickname} в Discord`"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M19.5 5.3A16.3 16.3 0 0 0 15.4 4l-.5 1a15 15 0 0 0-5.8 0l-.5-1a16.3 16.3 0 0 0-4.1 1.3C1.9 9.1 1.2 12.8 1.5 16.4a16.8 16.8 0 0 0 5 2.5l1.2-1.7-1.8-.9.4-.3c3.5 1.6 7.9 1.6 11.4 0l.4.3-1.8.9 1.2 1.7a16.8 16.8 0 0 0 5-2.5c.4-4.2-.8-7.8-3-11.1ZM8.7 14.6c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Zm6.6 0c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Z"/></svg></i><span><small>Discord</small><b>{{ player.user.discord_display_name || player.user.discord_username }}</b></span></a><span v-else-if="player.user" class="profile-discord-card"><i aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M19.5 5.3A16.3 16.3 0 0 0 15.4 4l-.5 1a15 15 0 0 0-5.8 0l-.5-1a16.3 16.3 0 0 0-4.1 1.3C1.9 9.1 1.2 12.8 1.5 16.4a16.8 16.8 0 0 0 5 2.5l1.2-1.7-1.8-.9.4-.3c3.5 1.6 7.9 1.6 11.4 0l.4.3-1.8.9 1.2 1.7a16.8 16.8 0 0 0 5-2.5c.4-4.2-.8-7.8-3-11.1ZM8.7 14.6c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Zm6.6 0c-1.1 0-2-1-2-2.2s.9-2.2 2-2.2 2 1 2 2.2-.9 2.2-2 2.2Z"/></svg></i><span><small>Discord</small><b>{{ player.user.discord_display_name || player.user.discord_username }}</b></span></span></div></div>
    <button v-if="canEditProfile" class="secondary profile-edit-button" @click="openEditor">Редактировать профиль</button>
    <div class="profile-stats"><article class="stat-card profile-gear-stat"><span>ГС</span><strong>{{ formatInteger(player.gear_score??0) }}</strong><small :class="gearDelta === null ? '' : gearDelta >= 0 ? 'positive' : 'negative'">{{ gearDeltaText }}</small></article><StatCard label="Праймы · 30 дней" :value="statistics.primes_count??0"/><StatCard label="Посещение праймов · 30 дней" :value="`${formatDecimal(statistics.prime_attendance_percentage??0)}%`"/><StatCard label="Выплачено" :value="formatInteger(statistics.paid_gold??0)" gold/><StatCard label="Ожидается" :value="formatInteger(statistics.pending_gold??0)" gold/></div>
  </div>
  <div class="panel player-assets-panel"><div class="panel-title"><h2>Оснащение и транспорт</h2><span class="muted">{{ Object.keys(assetLabels).filter(key => player[key]).length }} / {{ Object.keys(assetLabels).length }}</span></div><div class="player-assets"><span v-for="(label,key) in assetLabels" :key="key" :class="{ owned: player[key] }"><img class="profile-asset-image" :src="assetImages[key]" :alt="label" loading="lazy" :style="{ objectPosition: `${assetImagePositions[key]} center` }"><span class="profile-asset-copy"><i aria-hidden="true">{{ player[key] ? '✓' : '×' }}</i><span><b>{{ label }}</b><small>{{ player[key] ? 'В наличии' : 'Нет' }}</small></span></span></span></div></div>
  <div class="split-grid profile profile-history-grid"><div class="panel"><h2>История начислений</h2><div v-if="earnings.length" class="earning-list"><div v-for="item in earnings" :key="item.id"><span class="profile-activity-icon"><img v-if="item.activity?.definition?.icon_url" :src="item.activity.definition.icon_url" :alt="item.activity.definition.name"><i v-else>◆</i></span><span class="profile-activity-details"><strong>{{ item.activity?.definition?.name }}</strong><small>Прайм · {{ formatDate(item.activity?.occurred_at) }}</small></span><b><GoldAmount :value="formatInteger(item.player_share)"/></b><span :class="['import-status',item.status==='paid'?'confirmed':'draft']">{{ earningStatus[item.status] }}</span></div></div><p v-else class="empty">Начислений за праймы пока нет.</p></div><div class="panel"><h2>Последние посещения</h2><div v-if="activities.length" class="visit-list"><div v-for="item in activities.slice(0,6)" :key="item.id"><span class="profile-activity-icon"><img v-if="item.definition?.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><i v-else>◆</i></span><strong>{{ item.definition?.name }}</strong><span>{{ formatDate(item.occurred_at) }}</span></div></div><p v-else class="empty">Посещений праймов пока нет.</p></div></div>
  <div v-if="showEditor" class="modal"><form class="form-card profile-edit-modal" @submit.prevent="saveProfile"><header class="profile-edit-modal-head"><div><span>✎</span><h2>Редактировать профиль</h2></div><button type="button" aria-label="Закрыть" @click="showEditor=false">×</button></header><div class="profile-edit-section"><div class="profile-edit-section-title"><span>▣</span><strong>Персонаж</strong></div><label>Никнейм<input v-model.trim="nickname" maxlength="18" pattern="[A-Za-zА-Яа-яЁё]+" title="Только русские или латинские буквы, без пробелов, цифр и специальных символов" required></label><label>Класс<select v-model="selectedClass" required><option v-for="(label,value) in labels" :key="value" :value="value">{{ label }}</option></select></label><label>ГС<input v-model.number="gearScore" type="number" min="0" max="100000" required></label></div><div class="profile-edit-section"><div class="profile-edit-section-title"><span>▧</span><strong>Фон баннера</strong></div><div class="profile-banner-preview" :style="{ backgroundImage: `url('${activeBannerUrl}')` }"></div><label class="profile-banner-upload">Изображение или GIF<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="selectBanner"><small>JPG, PNG, WebP или GIF, до 10 МБ</small></label><button v-if="player.banner_url || bannerFile" type="button" class="secondary profile-banner-remove" @click="markBannerForRemoval">Использовать стандартный фон</button></div><div class="profile-edit-section"><div class="profile-edit-section-title"><span>✓</span><strong>Оснащение и транспорт</strong></div><div class="profile-asset-editor"><label v-for="(label,key) in assetLabels" :key="key"><input v-model="assets[key]" type="checkbox"><span>{{ label }}</span></label></div></div><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" class="secondary" @click="showEditor=false">Отмена</button><button class="primary" :disabled="savingProfile||!nickname">{{ savingProfile?'Сохранение…':'Сохранить' }}</button></div></form></div>
</section><section v-else><p :class="error?'notice error':'empty'">{{ error||'Загрузка…' }}</p></section></template>
