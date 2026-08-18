<script setup>
import { onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useGuildStore } from '../stores/guild.js'
import PlayerForm from '../components/PlayerForm.vue'
import GoldAmount from '../components/GoldAmount.vue'

const auth = useAuthStore(); const guild = useGuildStore(); const showForm = ref(false)
const moving = ref(null)
const deleting = ref(null)
const classes = [['', 'Все классы'], ['melee', 'Милик'], ['archer', 'Лучник'], ['mage', 'Маг'], ['healer', 'Хил'], ['bard', 'Бард'], ['tank', 'Танк']]
const labels = Object.fromEntries(classes)
let timer
watch(() => [guild.filters.search, guild.filters.class], () => { clearTimeout(timer); timer = setTimeout(guild.fetchPlayers, 250) })
onMounted(() => Promise.all([guild.fetchGroups(), guild.fetchPlayers()]))
async function movePlayer(player, value) {
  moving.value = player.id
  try { await guild.movePlayer(player.id, value === '' ? null : Number(value)); await guild.fetchGroups() }
  finally { moving.value = null }
}
function canMovePlayer(player) { return auth.canManage || (auth.isPartyLeader && auth.partyGroupId !== null && (player.group_id === null || player.group_id === auth.partyGroupId)) }
function availableGroups() { return auth.canManage ? guild.groups : guild.groups.filter(group => group.id === auth.partyGroupId) }
function playerCountLabel(value) {
  const count = Number(value ?? 0)
  const lastTwo = count % 100
  const last = count % 10
  const word = lastTwo >= 11 && lastTwo <= 14 ? 'игроков' : last === 1 ? 'игрок' : last >= 2 && last <= 4 ? 'игрока' : 'игроков'
  return `${count.toLocaleString('ru-RU')} ${word}`
}
async function deletePlayer(player) {
  if (!window.confirm(`Удалить персонажа «${player.nickname}» без возможности восстановления?`)) return
  deleting.value = player.id
  guild.error = ''
  try {
    await guild.deletePlayer(player.id)
  } catch (error) {
    const validationError = Object.values(error.response?.data?.errors ?? {}).flat()[0]
    guild.error = validationError ?? error.response?.data?.message ?? 'Не удалось удалить персонажа.'
  } finally {
    deleting.value = null
  }
}
</script>

<template>
  <section class="roster-page">
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ГИЛЬДИЯ</p><h1>Состав</h1><p class="muted">Игроки, классы и распределение по конст-пати</p></div><button v-if="auth.canManage" class="primary" @click="showForm = true">Добавить игрока</button></div>
    <div class="toolbar"><input v-model="guild.filters.search" placeholder="Поиск по никнейму"><select v-model="guild.filters.class"><option v-for="item in classes" :key="item[0]" :value="item[0]">{{ item[1] }}</option></select></div>
    <p v-if="guild.error" class="notice error">{{ guild.error }}</p>
    <div class="table-wrap roster-table"><div class="roster-table-summary"><span>Игроки гильдии</span><strong>{{ playerCountLabel(guild.pagination?.total ?? guild.players.length) }}</strong></div><table><thead><tr><th>Никнейм</th><th>Класс</th><th>Конст-пати</th><th>Посещено праймов</th><th>Посещено мини-праймов</th><th class="right">Выплачено всего</th><th v-if="auth.canManage" class="right">Действия</th></tr></thead><tbody>
      <tr v-if="guild.loading"><td :colspan="auth.canManage ? 7 : 6" class="empty">Загрузка…</td></tr>
      <tr v-else-if="!guild.players.length"><td :colspan="auth.canManage ? 7 : 6" class="empty">Игроки не найдены</td></tr>
      <tr v-for="player in guild.players" :key="player.id"><td><RouterLink :to="`/players/${player.id}`">{{ player.nickname }}</RouterLink></td><td><span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></td><td><select v-if="canMovePlayer(player)" class="group-select" :value="player.group_id ?? ''" :disabled="moving===player.id" @change="movePlayer(player,$event.target.value)"><option value="">Сольники</option><option v-for="group in availableGroups()" :key="group.id" :value="group.id">{{ group.name }}</option></select><span v-else>{{ player.group?.name ?? 'Сольники' }}</span></td><td><strong class="attendance-count">{{ player.primes_count ?? 0 }}</strong></td><td><strong class="attendance-count">{{ player.mini_activities_count ?? 0 }}</strong></td><td class="right"><GoldAmount :value="Number(player.paid_total ?? 0).toLocaleString('ru-RU')" /></td><td v-if="auth.canManage" class="right"><button v-if="!player.user" class="danger compact" :disabled="deleting === player.id" title="Навсегда удалить непривязанного персонажа" @click="deletePlayer(player)">{{ deleting === player.id ? 'Удаление…' : 'Удалить' }}</button><span v-else class="muted">—</span></td></tr>
    </tbody></table></div>
    <div v-if="showForm" class="modal" @click.self="showForm = false"><PlayerForm @saved="showForm = false" @cancel="showForm = false" /></div>
  </section>
</template>
