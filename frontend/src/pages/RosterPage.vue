<script setup>
import { onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useGuildStore } from '../stores/guild.js'
import PlayerForm from '../components/PlayerForm.vue'

const auth = useAuthStore(); const guild = useGuildStore(); const showForm = ref(false)
const moving = ref(null)
const classes = [['', 'Все классы'], ['melee', 'Милик'], ['archer', 'Лук'], ['mage', 'Маг'], ['healer', 'Хил'], ['bard', 'Бард'], ['tank', 'Танк']]
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
</script>

<template>
  <section>
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ГИЛЬДИЯ</p><h1>Состав</h1><p class="muted">Игроки, классы и распределение по конст-пати</p></div><button v-if="auth.canManage" class="primary" @click="showForm = true">Добавить игрока</button></div>
    <div class="toolbar"><input v-model="guild.filters.search" placeholder="Поиск по никнейму"><select v-model="guild.filters.class"><option v-for="item in classes" :key="item[0]" :value="item[0]">{{ item[1] }}</option></select></div>
    <p v-if="guild.error" class="notice error">{{ guild.error }}</p>
    <div class="table-wrap"><table><thead><tr><th>Никнейм</th><th>Класс</th><th>Конст-пати</th><th>Посещено праймов</th><th>Посещено мини-праймов</th></tr></thead><tbody>
      <tr v-if="guild.loading"><td colspan="5" class="empty">Загрузка…</td></tr>
      <tr v-else-if="!guild.players.length"><td colspan="5" class="empty">Игроки не найдены</td></tr>
      <tr v-for="player in guild.players" :key="player.id"><td><RouterLink :to="`/players/${player.id}`">{{ player.nickname }}</RouterLink></td><td><span class="class-tag">{{ labels[player.class] }}</span></td><td><select v-if="canMovePlayer(player)" class="group-select" :value="player.group_id ?? ''" :disabled="moving===player.id" @change="movePlayer(player,$event.target.value)"><option value="">Одиночки</option><option v-for="group in availableGroups()" :key="group.id" :value="group.id">{{ group.name }}</option></select><span v-else>{{ player.group?.name ?? 'Одиночки' }}</span></td><td><strong class="attendance-count">{{ player.primes_count ?? 0 }}</strong></td><td><strong class="attendance-count">{{ player.mini_activities_count ?? 0 }}</strong></td></tr>
    </tbody></table></div>
    <div v-if="showForm" class="modal" @click.self="showForm = false"><PlayerForm @saved="showForm = false" @cancel="showForm = false" /></div>
  </section>
</template>
