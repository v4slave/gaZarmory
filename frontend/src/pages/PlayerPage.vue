<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api.js'
import StatCard from '../components/StatCard.vue'
import GoldAmount from '../components/GoldAmount.vue'
import { useAuthStore } from '../stores/auth.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const route = useRoute(); const player = ref(null); const error = ref('')
const auth = useAuthStore(); const renaming = ref(false); const nickname = ref(''); const changingClass = ref(false); const selectedClass = ref('')
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
async function rename() { renaming.value = true; error.value = ''; try { await auth.renamePlayer(nickname.value); player.value.nickname = auth.user.player.nickname; notifications.success('Игровой никнейм изменён.') } catch (e) { error.value = apiErrorMessage(e,'Не удалось сменить никнейм.'); notifications.error(error.value) } finally { renaming.value = false } }
async function changeClass() { changingClass.value = true; error.value = ''; try { await auth.changePlayerClass(selectedClass.value); player.value.class = auth.user.player.class; notifications.success('Класс персонажа изменён.') } catch (e) { error.value = apiErrorMessage(e,'Не удалось сменить класс.'); notifications.error(error.value) } finally { changingClass.value = false } }
</script>

<template><section v-if="player">
  <div class="profile-actions"><div v-if="player.user" class="profile-discord"><img v-if="discordAvatar" :src="discordAvatar" alt="Аватар Discord" referrerpolicy="no-referrer"><span v-else>{{ (player.user.discord_display_name || player.user.discord_username).slice(0,1).toUpperCase() }}</span><div><small>Discord</small><strong>{{ player.user.discord_display_name || player.user.discord_username }}</strong></div></div><div v-if="isOwnProfile" class="profile-editors"><form class="rename-form" @submit.prevent="rename"><input v-model.trim="nickname" maxlength="18" pattern="[A-Za-zА-Яа-яЁё]+" title="Только русские или латинские буквы, без пробелов, цифр и специальных символов" required><button class="secondary" :disabled="renaming||nickname===player.nickname">{{ renaming?'Сохранение…':'Сменить имя' }}</button></form><form class="class-form" @submit.prevent="changeClass"><select v-model="selectedClass" required><option v-for="(label,value) in labels" :key="value" :value="value">{{ label }}</option></select><button class="secondary" :disabled="changingClass||selectedClass===player.class">{{ changingClass?'Сохранение…':'Сменить класс' }}</button></form></div></div>
  <p v-if="error" class="notice error">{{ error }}</p>
  <div class="profile-hero"><div><p class="eyebrow">СОСТАВ · ПРОФИЛЬ ИГРОКА</p><h1>{{ player.nickname }} <span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></h1><p class="muted">{{ player.group?.name ?? 'Сольники' }}</p></div><div class="profile-stats"><StatCard label="Праймы · 30 дней" :value="statistics.primes_count??0"/><StatCard label="Мини-активности · 30 дней" :value="statistics.mini_activities_count??0"/><StatCard label="Посещение праймов · 30 дней" :value="`${Number(statistics.prime_attendance_percentage??0).toLocaleString('ru-RU')}%`"/><StatCard label="Выплачено" :value="Number(statistics.paid_gold??0).toLocaleString('ru-RU')" gold/><StatCard label="Ожидается" :value="Number(statistics.pending_gold??0).toLocaleString('ru-RU')" gold accent/></div></div>
  <div class="split-grid profile"><div class="panel"><h2>История начислений</h2><div v-if="earnings.length" class="earning-list"><div v-for="item in earnings" :key="item.id"><span><strong>{{ item.activity?.definition?.name }}</strong><small>{{ item.activity?.definition?.type==='mini_activity'?'Мини-прайм':'Прайм' }} · {{ new Date(item.activity?.occurred_at).toLocaleDateString('ru-RU') }}</small></span><b><GoldAmount :value="Number(item.player_share).toLocaleString('ru-RU')"/></b><span :class="['import-status',item.status==='paid'?'confirmed':'draft']">{{ earningStatus[item.status] }}</span></div></div><p v-else class="empty">Начислений пока нет.</p></div><div class="panel"><h2>Последние посещения</h2><div v-if="activities.length" class="visit-list"><div v-for="item in activities.slice(0,6)" :key="item.id"><strong>{{ item.definition?.name }}</strong><span>{{ new Date(item.occurred_at).toLocaleDateString('ru-RU') }}</span></div></div><p v-else class="empty">Посещений пока нет.</p></div></div>
</section><section v-else><p :class="error?'notice error':'empty'">{{ error||'Загрузка…' }}</p></section></template>
