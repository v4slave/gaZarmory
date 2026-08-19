<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const route = useRoute(); const player = ref(null); const error = ref('')
const auth = useAuthStore(); const showEditor = ref(false); const savingProfile = ref(false); const nickname = ref(''); const selectedClass = ref('')
const notifications = useNotificationsStore()
const labels = { melee:'Милик', archer:'Лучник', mage:'Маг', healer:'Хил', bard:'Бард', tank:'Танк' }
const activities = computed(() => player.value?.activities ?? [])
const statistics = computed(() => player.value?.statistics ?? {})
const earnings = computed(() => player.value?.earnings_history ?? [])
const earningStatus = { pending:'Ожидается', paid:'Выплачено', cancelled:'Отменено' }
const isOwnProfile = computed(() => Number(auth.user?.player?.id) === Number(player.value?.id))
const discordAvatar = computed(() => { const user = player.value?.user; if (!user?.discord_avatar) return null; if (/^https?:\/\//i.test(user.discord_avatar)) return user.discord_avatar; const extension = user.discord_avatar.startsWith('a_') ? 'gif' : 'png'; return `https://cdn.discordapp.com/avatars/${user.discord_id}/${user.discord_avatar}.${extension}?size=128` })

async function loadPlayer(id) { player.value = null; error.value = ''; try { player.value = (await api.get(`/api/players/${id}`)).data; nickname.value = player.value.nickname; selectedClass.value = player.value.class } catch (e) { error.value = e.response?.data?.message ?? 'Не удалось загрузить профиль.' } }
watch(() => route.params.id, id => loadPlayer(id), { immediate:true })
function openEditor() { nickname.value = player.value.nickname; selectedClass.value = player.value.class; error.value = ''; showEditor.value = true }
async function saveProfile() {
  savingProfile.value = true; error.value = ''
  try {
    const requests = []
    if (nickname.value !== player.value.nickname) requests.push(api.patch('/api/me/player/nickname', { nickname: nickname.value }))
    if (selectedClass.value !== player.value.class) requests.push(api.patch('/api/me/player/class', { class: selectedClass.value }))
    await Promise.all(requests)
    await auth.fetchMe()
    player.value.nickname = auth.user.player.nickname
    player.value.class = auth.user.player.class
    showEditor.value = false
    notifications.success('Профиль персонажа сохранён.')
  } catch (e) { error.value = apiErrorMessage(e,'Не удалось сохранить профиль.'); notifications.error(error.value) }
  finally { savingProfile.value = false }
}
</script>

<template><section v-if="player">
  <div class="profile-actions"><div v-if="player.user" class="profile-discord"><img v-if="discordAvatar" :src="discordAvatar" alt="Аватар Discord" referrerpolicy="no-referrer"><span v-else>{{ (player.user.discord_display_name || player.user.discord_username).slice(0,1).toUpperCase() }}</span><div><small>Discord</small><strong>{{ player.user.discord_display_name || player.user.discord_username }}</strong></div></div><button v-if="isOwnProfile" class="secondary profile-edit-button" @click="openEditor">Редактировать профиль</button></div>
  <p v-if="error" class="notice error">{{ error }}</p>
  <div class="profile-hero"><div><p class="eyebrow">СОСТАВ · ПРОФИЛЬ ИГРОКА</p><h1>{{ player.nickname }} <span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></h1><p class="muted">{{ player.group?.name ?? 'Сольники' }}</p></div><div class="profile-stats"><StatCard label="Праймы · 30 дней" :value="statistics.primes_count??0"/><StatCard label="Мини-активности · 30 дней" :value="statistics.mini_activities_count??0"/><StatCard label="Посещение праймов · 30 дней" :value="`${Number(statistics.prime_attendance_percentage??0).toLocaleString('ru-RU')}%`"/><StatCard label="Выплачено" :value="Number(statistics.paid_gold??0).toLocaleString('ru-RU')" gold/><StatCard label="Ожидается" :value="Number(statistics.pending_gold??0).toLocaleString('ru-RU')" gold accent/></div></div>
  <div class="split-grid profile"><div class="panel"><h2>История начислений</h2><div v-if="earnings.length" class="earning-list"><div v-for="item in earnings" :key="item.id"><span class="profile-activity-icon"><img v-if="item.activity?.definition?.icon_url" :src="item.activity.definition.icon_url" :alt="item.activity.definition.name"><i v-else>◆</i></span><span class="profile-activity-details"><strong>{{ item.activity?.definition?.name }}</strong><small>{{ item.activity?.definition?.type==='mini_activity'?'Мини-прайм':'Прайм' }} · {{ new Date(item.activity?.occurred_at).toLocaleDateString('ru-RU') }}</small></span><b><GoldAmount :value="Number(item.player_share).toLocaleString('ru-RU')"/></b><span :class="['import-status',item.status==='paid'?'confirmed':'draft']">{{ earningStatus[item.status] }}</span></div></div><p v-else class="empty">Начислений пока нет.</p></div><div class="panel"><h2>Последние посещения</h2><div v-if="activities.length" class="visit-list"><div v-for="item in activities.slice(0,6)" :key="item.id"><span class="profile-activity-icon"><img v-if="item.definition?.icon_url" :src="item.definition.icon_url" :alt="item.definition.name"><i v-else>◆</i></span><strong>{{ item.definition?.name }}</strong><span>{{ new Date(item.occurred_at).toLocaleDateString('ru-RU') }}</span></div></div><p v-else class="empty">Посещений пока нет.</p></div></div>
  <div v-if="showEditor" class="modal" @click.self="showEditor=false"><form class="form-card profile-edit-modal" @submit.prevent="saveProfile"><header class="profile-edit-modal-head"><div><span>✎</span><h2>Редактировать профиль</h2></div><button type="button" aria-label="Закрыть" @click="showEditor=false">×</button></header><div class="profile-edit-section"><div class="profile-edit-section-title"><span>▣</span><strong>Персонаж</strong></div><label>Никнейм<input v-model.trim="nickname" maxlength="18" pattern="[A-Za-zА-Яа-яЁё]+" title="Только русские или латинские буквы, без пробелов, цифр и специальных символов" required></label><label>Класс<select v-model="selectedClass" required><option v-for="(label,value) in labels" :key="value" :value="value">{{ label }}</option></select></label></div><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" class="secondary" @click="showEditor=false">Отмена</button><button class="primary" :disabled="savingProfile||(!nickname)||((nickname===player.nickname)&&(selectedClass===player.class))">{{ savingProfile?'Сохранение…':'Сохранить' }}</button></div></form></div>
</section><section v-else><p :class="error?'notice error':'empty'">{{ error||'Загрузка…' }}</p></section></template>
