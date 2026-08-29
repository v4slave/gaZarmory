<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useGuildStore } from '../stores/guild.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import AppModal from '../components/AppModal.vue'
import { useConfirmationStore } from '../stores/confirmation.js'
import { formatDecimal, formatInteger } from '../utils/format.js'

const auth = useAuthStore(); const guild = useGuildStore(); const name = ref(''); const busy = ref(false); const dataLoading = ref(true); const layoutReady = ref(false); const renameTarget = ref(null); const renameName = ref('')
const router = useRouter()
const confirmation = useConfirmationStore()
let masonryFrame
const classLabels = { melee: 'Милик', archer: 'Лучник', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
async function create() { if (!name.value.trim()) return; busy.value = true; try { await guild.createGroup(name.value.trim()); name.value = '' } finally { busy.value = false } }
function rename(group) { renameTarget.value = group; renameName.value = group.name }
async function saveRename() { const value=renameName.value.trim();if(!renameTarget.value||!value||value===renameTarget.value.name)return;busy.value=true;try{await guild.renameGroup(renameTarget.value.id,value);renameTarget.value=null;renameName.value=''}finally{busy.value=false} }
async function remove(group) { if (await confirmation.ask({title:'Удалить конст-пати?',message:`Игроки из «${group.name}» станут одиночками.`,confirmLabel:'Удалить',danger:true})) await guild.deleteGroup(group.id) }
function canManageGroup() { return auth.canManage }
function openSquadsFromTitle(event) {
  const title = event.target.closest('.group-card:not(.solo) h2')
  if (!title || (event.type === 'keydown' && !['Enter', ' '].includes(event.key))) return
  const cards = [...document.querySelectorAll('.groups-page .group-card:not(.solo)')]
  const group = guild.groups[cards.indexOf(title.closest('.group-card'))]
  if (group) router.push(`/groups/${group.id}/squads`)
}
function isPartyLeader(player) { return (player.user?.roles ?? [player.user?.role]).includes('party_leader') }
function comparePlayers(left, right, keepLeaderFirst = false) {
  return (keepLeaderFirst ? Number(isPartyLeader(right)) - Number(isPartyLeader(left)) : 0)
    || classLabels[left.class].localeCompare(classLabels[right.class], 'ru')
    || left.nickname.localeCompare(right.nickname, 'ru')
}
function sortedPlayers(group) { return [...(group.players ?? [])].sort((left, right) => comparePlayers(left, right, true)) }
const soloPlayers = computed(() => guild.players
  .filter(player => player.is_active && player.group_id === null)
  .sort((left, right) => comparePlayers(left, right)))
function layoutMasonry() {
  masonryFrame = undefined
  const grid = document.querySelector('.groups-page .group-grid')
  if (!grid) return
  const styles = window.getComputedStyle(grid)
  const rowHeight = Number.parseFloat(styles.gridAutoRows) || 8
  const rowGap = Number.parseFloat(styles.rowGap) || 16
  const cards = [...grid.children]
  cards.forEach(card => { card.style.gridRowEnd = 'auto' })
  cards.forEach(card => {
    const span = Math.ceil((card.getBoundingClientRect().height + rowGap) / (rowHeight + rowGap))
    card.style.gridRowEnd = `span ${span}`
  })
  grid.querySelectorAll('.group-card:not(.solo) h2').forEach(title => { title.tabIndex = 0; title.classList.add('group-title-link') })
  layoutReady.value = true
}
function scheduleMasonry() {
  if (dataLoading.value) return
  if (masonryFrame) window.cancelAnimationFrame(masonryFrame)
  masonryFrame = window.requestAnimationFrame(layoutMasonry)
}
watch(() => [guild.groups.map(group => `${group.id}:${group.players?.length ?? 0}`).join(','), soloPlayers.value.length], async () => {
  if (dataLoading.value) return
  layoutReady.value = false
  await nextTick()
  scheduleMasonry()
}, { flush: 'post' })
onMounted(async () => {
  dataLoading.value = true
  layoutReady.value = false
  await Promise.all([guild.fetchGroups(), guild.fetchPlayers({ active: true, solo: true, per_page: 100 })])
  dataLoading.value = false
  await nextTick()
  await document.fonts?.ready
  scheduleMasonry()
  window.addEventListener('resize', scheduleMasonry, { passive: true })
  const grid = document.querySelector('.groups-page .group-grid')
  grid?.addEventListener('click', openSquadsFromTitle)
  grid?.addEventListener('keydown', openSquadsFromTitle)
})
onBeforeUnmount(() => {
  if (masonryFrame) window.cancelAnimationFrame(masonryFrame)
  window.removeEventListener('resize', scheduleMasonry)
  const grid = document.querySelector('.groups-page .group-grid')
  grid?.removeEventListener('click', openSquadsFromTitle)
  grid?.removeEventListener('keydown', openSquadsFromTitle)
})
</script>

<template><section class="groups-page"><div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ГИЛЬДИЯ</p><h1>Конст-пати</h1></div></div>
  <form v-if="auth.canManage" class="inline-form" @submit.prevent="create"><input v-model="name" required maxlength="120" placeholder="Название новой конст-пати"><button class="primary" :disabled="busy">Создать</button></form>
  <p v-if="guild.error" class="notice error">{{ guild.error }}</p>
  <div v-if="dataLoading || (!guild.error && !layoutReady)" class="panel groups-loading" role="status">Загружаем конст-пати…</div>
  <div v-if="!dataLoading&&!guild.error" :class="['group-grid', { 'is-layout-pending': !layoutReady }]"><article v-for="group in guild.groups" :key="group.id" class="group-card"><div class="group-card-heading"><div><h2>{{ group.name }}</h2><p>{{ group.players_count }} игроков</p></div><div class="group-averages"><span><small>Средний ГС</small><b>{{ formatInteger(group.average_gear_score??0) }}</b></span><span><small>Посещаемость</small><b>{{ formatDecimal(group.average_prime_attendance??0) }}%</b></span></div></div><div v-if="group.players?.length" class="group-members"><RouterLink v-for="player in sortedPlayers(group)" :key="player.id" :to="`/players/${player.id}`"><PlayerAvatar :player="player" size="small"/><strong><span v-if="isPartyLeader(player)" class="party-crown" title="PL · лидер конст-пати">♛</span>{{ player.nickname }}</strong><span class="group-member-meta"><span :class="['class-tag',`class-${player.class}`]">{{ classLabels[player.class] }}</span><small title="Посещённые праймы и мини-праймы">{{ player.primes_count ?? 0 }} / {{ player.mini_activities_count ?? 0 }}</small></span></RouterLink></div><p v-else class="group-empty">В конст-пати пока никого нет</p><div v-if="canManageGroup(group)" class="card-actions"><button @click="rename(group)">Переименовать</button><button class="danger" @click="remove(group)">Удалить</button></div></article>
    <article class="group-card solo"><div class="group-card-heading"><h2>Сольники</h2><p>{{ soloPlayers.length }} игроков</p></div><div v-if="soloPlayers.length" class="group-members"><RouterLink v-for="player in soloPlayers" :key="player.id" :to="`/players/${player.id}`"><PlayerAvatar :player="player" size="small"/><strong>{{ player.nickname }}</strong><span class="group-member-meta"><span :class="['class-tag',`class-${player.class}`]">{{ classLabels[player.class] }}</span><small title="Посещённые праймы и мини-праймы">{{ player.primes_count ?? 0 }} / {{ player.mini_activities_count ?? 0 }}</small></span></RouterLink></div><p v-else class="group-empty">Активных сольников нет</p></article>
  </div>
  <AppModal :open="Boolean(renameTarget)" title="Переименовать группу" :dirty="Boolean(renameTarget&&renameName.trim()&&renameName.trim()!==renameTarget.name)" @close="renameTarget=null"><form v-if="renameTarget" class="form-card group-rename-card" @submit.prevent="saveRename"><p class="eyebrow">КОНСТ-ПАТИ</p><h2>Переименовать группу</h2><p class="muted">Введите новое название для «{{ renameTarget.name }}».</p><label>Новое название<input v-model.trim="renameName" maxlength="120" required autofocus></label><div class="form-actions"><button type="button" @click="renameTarget=null">Отмена</button><button class="primary" :disabled="busy||!renameName.trim()||renameName.trim()===renameTarget.name">{{ busy?'Сохранение…':'Сохранить название' }}</button></div></form></AppModal>
</section></template>
