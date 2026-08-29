<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useGuildStore } from '../stores/guild.js'
import PlayerForm from '../components/PlayerForm.vue'
import GoldAmount from '../components/GoldAmount.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import AsyncState from '../components/AsyncState.vue'
import { useConfirmationStore } from '../stores/confirmation.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'
import { formatInteger } from '../utils/format.js'

const auth = useAuthStore(); const guild = useGuildStore(); const showForm = ref(false)
const confirmation = useConfirmationStore(); const notifications = useNotificationsStore()
const moving = ref(null)
const deleting = ref(null)
const classes = [['', 'Все классы'], ['melee', 'Милик'], ['archer', 'Лучник'], ['mage', 'Маг'], ['healer', 'Хил'], ['bard', 'Бард'], ['tank', 'Танк']]
const labels = Object.fromEntries(classes)
let timer
const pages = computed(() => Array.from({ length: guild.pagination?.last_page ?? 1 }, (_, index) => index + 1))
watch(() => [guild.filters.search, guild.filters.class], () => { clearTimeout(timer);guild.filters.page=1;timer=setTimeout(()=>guild.fetchPlayers(),250) })
onMounted(() => Promise.all([guild.fetchGroups(), guild.fetchPlayers()]))
function goToPage(page) { if(page===guild.pagination?.current_page||guild.loading)return;guild.filters.page=page;guild.fetchPlayers() }
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
  return `${formatInteger(count)} ${word}`
}
async function deletePlayer(player) {
  const confirmed = await confirmation.ask({
    title: 'Безвозвратно удалить персонажа?',
    message: `Персонаж «${player.nickname}» будет удалён без возможности восстановления. Исторические записи блокируют удаление автоматически.`,
    confirmLabel: 'Удалить навсегда',
    danger: true,
    expectedText: player.nickname,
  })
  if (!confirmed) return
  deleting.value = player.id
  guild.error = ''
  try {
    await guild.deletePlayer(player.id)
    notifications.success(`Персонаж «${player.nickname}» удалён.`)
  } catch (error) {
    guild.error = apiErrorMessage(error, 'Не удалось удалить персонажа.')
    notifications.error(guild.error)
  } finally {
    deleting.value = null
  }
}
</script>

<template>
  <section class="roster-page">
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ГИЛЬДИЯ</p><h1>Состав</h1></div><button v-if="auth.canManage" class="primary" @click="showForm = true">Добавить игрока</button></div>
    <div class="toolbar"><input v-model="guild.filters.search" placeholder="Поиск по никнейму"><select v-model="guild.filters.class"><option v-for="item in classes" :key="item[0]" :value="item[0]">{{ item[1] }}</option></select></div>
    <AsyncState :loading="guild.loading" :error="guild.error" :empty="!guild.players.length" loading-text="Загружаем состав…" empty-title="Игроки не найдены" empty-text="Измените фильтры или добавьте нового персонажа." @retry="guild.fetchPlayers" />
    <div v-if="!guild.loading&&!guild.error&&guild.players.length" class="table-wrap roster-table"><div class="roster-table-summary"><span>Игроки гильдии</span><strong>{{ playerCountLabel(guild.pagination?.total ?? guild.players.length) }}</strong></div><table><thead><tr><th>Никнейм</th><th>Класс</th><th>Конст-пати</th><th>Посещено праймов</th><th class="right">Выплачено всего</th><th v-if="auth.canManage" class="right">Действия</th></tr></thead><tbody>
      <tr v-for="player in guild.players" :key="player.id" class="roster-player-row"><td><RouterLink class="player-identity" :to="`/players/${player.id}`" :title="`Открыть профиль ${player.nickname}`"><PlayerAvatar :player="player" size="small"/><span>{{ player.nickname }}</span><b class="profile-arrow" aria-hidden="true">›</b></RouterLink></td><td><span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></td><td><select v-if="canMovePlayer(player)" class="group-select" :value="player.group_id ?? ''" :disabled="moving===player.id" @change="movePlayer(player,$event.target.value)"><option value="">Сольники</option><option v-for="group in availableGroups()" :key="group.id" :value="group.id">{{ group.name }}</option></select><span v-else>{{ player.group?.name ?? 'Сольники' }}</span></td><td><strong class="attendance-count">{{ player.primes_count ?? 0 }}</strong></td><td class="right"><GoldAmount :value="formatInteger(player.paid_total ?? 0)" /></td><td v-if="auth.canManage" class="right"><button v-if="!player.user" class="roster-delete-button" :disabled="deleting === player.id" title="Навсегда удалить непривязанного персонажа" @click="deletePlayer(player)">{{ deleting === player.id ? 'Удаление…' : 'Удалить' }}</button><span v-else class="muted">—</span></td></tr>
    </tbody></table></div>
    <nav v-if="!guild.loading&&!guild.error&&(guild.pagination?.last_page ?? 1)>1" class="roster-pagination" aria-label="Страницы состава">
      <button type="button" :disabled="guild.loading||guild.pagination.current_page<=1" @click="goToPage(guild.pagination.current_page-1)">‹</button>
      <button v-for="page in pages" :key="page" type="button" :class="{active:page===guild.pagination.current_page}" :aria-current="page===guild.pagination.current_page?'page':undefined" :disabled="guild.loading" @click="goToPage(page)">{{ page }}</button>
      <button type="button" :disabled="guild.loading||guild.pagination.current_page>=guild.pagination.last_page" @click="goToPage(guild.pagination.current_page+1)">›</button>
    </nav>
    <div v-if="showForm" class="modal" @click.self="showForm = false"><PlayerForm @saved="showForm = false" @cancel="showForm = false" /></div>
  </section>
</template>
