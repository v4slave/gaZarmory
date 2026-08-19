<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const route = useRoute(); const player = ref(null); const error = ref('')
const auth = useAuthStore(); const showEditor = ref(false); const savingProfile = ref(false); const nickname = ref(''); const selectedClass = ref(''); const gearScore = ref(0)
const notifications = useNotificationsStore()
const labels = { melee:'Милик', archer:'Лучник', mage:'Маг', healer:'Хил', bard:'Бард', tank:'Танк' }
const assetLabels = { has_ship:'Корабль', has_tank:'Танк', has_fuchsias:'Фуксория', has_clouds:'Облачко', has_machaon:'Махаон', has_tare:'Таре', has_deer:'Олень', has_invulnerable_pet:'Пет на неуяз', has_shield_swap:'Свап на щит', has_flippers:'Ласты' }
const assets = reactive(Object.fromEntries(Object.keys(assetLabels).map(key => [key, false])))
const activities = computed(() => (player.value?.activities ?? []).filter(item => item.definition?.type !== 'mini_activity'))
const statistics = computed(() => player.value?.statistics ?? {})
const earnings = computed(() => (player.value?.earnings_history ?? []).filter(item => item.activity?.definition?.type !== 'mini_activity'))
const earningStatus = { pending:'Ожидается', paid:'Выплачено', cancelled:'Отменено' }
const isOwnProfile = computed(() => Number(auth.user?.player?.id) === Number(player.value?.id))

function syncEditor() { nickname.value = player.value.nickname; selectedClass.value = player.value.class; gearScore.value = Number(player.value.gear_score ?? 0); Object.keys(assetLabels).forEach(key => { assets[key] = Boolean(player.value[key]) }) }
async function loadPlayer(id) { player.value = null; error.value = ''; try { player.value = (await api.get(`/api/players/${id}`)).data; syncEditor() } catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить профиль.' } }
watch(() => route.params.id, id => loadPlayer(id), { immediate:true })
function openEditor() { syncEditor(); error.value = ''; showEditor.value = true }
async function saveProfile() {
  savingProfile.value = true; error.value = ''
  try {
    const requests = []
    if (nickname.value !== player.value.nickname) requests.push(api.patch('/api/me/player/nickname', { nickname: nickname.value }))
    if (selectedClass.value !== player.value.class) requests.push(api.patch('/api/me/player/class', { class: selectedClass.value }))
    requests.push(api.patch('/api/me/player/profile', { gear_score: Number(gearScore.value), ...assets }))
    await Promise.all(requests)
    await auth.fetchMe()
    player.value.nickname = auth.user.player.nickname
    player.value = (await api.get(`/api/players/${route.params.id}`)).data
    showEditor.value = false
    notifications.success('Профиль персонажа сохранён.')
  } catch (e) { error.value = apiErrorMessage(e,'Не удалось сохранить профиль.'); notifications.error(error.value) }
  finally { savingProfile.value = false }
}
</script>

<template><section v-if="player" class="player-profile-page">
  <p v-if="error" class="notice error">{{ error }}</p>
  <div class="profile-showcase">
    <div class="profile-showcase-identity"><PlayerAvatar :player="player" size="hero"/><div><p class="eyebrow">ПРОФИЛЬ ИГРОКА</p><h1>{{ player.nickname }} <span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></h1><p>{{ player.group?.name ?? 'Сольники' }}</p><span v-if="player.user" class="profile-discord-line"><b>◉</b> Discord · {{ player.user.discord_display_name || player.user.discord_username }}</span></div></div>
    <button v-if="isOwnProfile" class="secondary profile-edit-button" @click="openEditor">Редактировать профиль</button>
    <div class="profile-stats"><StatCard label="ГС" :value="Number(player.gear_score??0).toLocaleString('ru-RU')" accent/><StatCard label="Праймы · 30 дней" :value="statistics.primes_count??0"/><StatCard label="Посещение праймов · 30 дней" :value="`${Number(statistics.prime_attendance_percentage??0).toLocaleString('ru-RU')}%`"/><StatCard label="Выплачено" :value="Number(statistics.paid_gold??0).toLocaleString('ru-RU')" gold/><StatCard label="Ожидается" :value="Number(statistics.pending_gold??0).toLocaleString('ru-RU')" gold/></div>
  </div>
  <div class="panel player-assets-panel"><div class="panel-title"><h2>Оснащение и транспорт</h2><span class="muted">{{ Object.keys(assetLabels).filter(key => player[key]).length }} / {{ Object.keys(assetLabels).length }}</span></div><div class="player-assets"><span v-for="(label,key) in assetLabels" :key="key" :class="{ owned: player[key] }"><i aria-hidden="true">{{ player[key] ? '✓' : '×' }}</i><b>{{ label }}</b><small>{{ player[key] ? 'В наличии' : 'Нет' }}</small></span></div></div>
  <div class="split-grid profile profile-history-grid"><div class="panel"><h2>История начислений</h2><div v-if="earnings.length" class="earning-list"><div v-for="item in earnings" :key="item.id"><span class="profile-activity-icon"><img v-if="item.activity?.definition?.icon_url" :src="item.activity.definition.icon_url" :alt="item.activity.definition.name"><i v-else>◆</i></span><span class="profile-activity-details"><strong>{{ item.activity?.definition?.name }}</strong><small>Прайм · {{ new Date(item.activity?.occurred_at).toLocaleDateString('ru-RU') }}</small></span><b><GoldAmount :value="Number(item.player_share).toLocaleString('ru-RU')"/></b><span :class="['import-status',item.status==='paid'?'confirmed':'draft']">{{ earningStatus[item.status] }}</span></div></div><p v-else class="empty">Начислений за праймы пока нет.</p></div><div class="panel"><h2>Последние посещения</h2><div v-if="activities.length" class="visit-list"><div v-for="item in activities.slice(0,6)" :key="item.id"><span class="profile-activity-icon"><img v-if="item.definition?.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><i v-else>◆</i></span><strong>{{ item.definition?.name }}</strong><span>{{ new Date(item.occurred_at).toLocaleDateString('ru-RU') }}</span></div></div><p v-else class="empty">Посещений праймов пока нет.</p></div></div>
  <div v-if="showEditor" class="modal" @click.self="showEditor=false"><form class="form-card profile-edit-modal" @submit.prevent="saveProfile"><header class="profile-edit-modal-head"><div><span>✎</span><h2>Редактировать профиль</h2></div><button type="button" aria-label="Закрыть" @click="showEditor=false">×</button></header><div class="profile-edit-section"><div class="profile-edit-section-title"><span>▣</span><strong>Персонаж</strong></div><label>Никнейм<input v-model.trim="nickname" maxlength="18" pattern="[A-Za-zА-Яа-яЁё]+" title="Только русские или латинские буквы, без пробелов, цифр и специальных символов" required></label><label>Класс<select v-model="selectedClass" required><option v-for="(label,value) in labels" :key="value" :value="value">{{ label }}</option></select></label><label>ГС<input v-model.number="gearScore" type="number" min="0" max="100000" required></label></div><div class="profile-edit-section"><div class="profile-edit-section-title"><span>✓</span><strong>Оснащение и транспорт</strong></div><div class="profile-asset-editor"><label v-for="(label,key) in assetLabels" :key="key"><input v-model="assets[key]" type="checkbox"><span>{{ label }}</span></label></div></div><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" class="secondary" @click="showEditor=false">Отмена</button><button class="primary" :disabled="savingProfile||!nickname">{{ savingProfile?'Сохранение…':'Сохранить' }}</button></div></form></div>
</section><section v-else><p :class="error?'notice error':'empty'">{{ error||'Загрузка…' }}</p></section></template>
