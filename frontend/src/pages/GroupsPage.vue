<script setup>
import { onMounted, ref } from 'vue'
import { useAuthStore } from '../stores/auth.js'
import { useGuildStore } from '../stores/guild.js'

const auth = useAuthStore(); const guild = useGuildStore(); const name = ref(''); const busy = ref(false)
const classLabels = { melee: 'Милик', archer: 'Лук', mage: 'Маг', healer: 'Хил', bard: 'Бард', tank: 'Танк' }
async function create() { if (!name.value.trim()) return; busy.value = true; try { await guild.createGroup(name.value.trim()); name.value = '' } finally { busy.value = false } }
async function rename(group) { const value = window.prompt('Новое название конст-пати', group.name); if (value?.trim() && value.trim() !== group.name) await guild.renameGroup(group.id, value.trim()) }
async function remove(group) { if (window.confirm(`Удалить «${group.name}»? Игроки станут одиночками.`)) await guild.deleteGroup(group.id) }
function canManageGroup(group) { return auth.canManage || (auth.isPartyLeader && auth.partyGroupId === group.id) }
function isPartyLeader(player) { return (player.user?.roles ?? [player.user?.role]).includes('party_leader') }
function sortedPlayers(group) { return [...(group.players ?? [])].sort((left, right) => Number(isPartyLeader(right)) - Number(isPartyLeader(left)) || left.nickname.localeCompare(right.nickname, 'ru')) }
onMounted(guild.fetchGroups)
</script>

<template><section><div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · ГИЛЬДИЯ</p><h1>Конст-пати</h1><p class="muted">Каждый игрок может состоять максимум в одной конст-пати</p></div></div>
  <form v-if="auth.canManage" class="inline-form" @submit.prevent="create"><input v-model="name" required maxlength="120" placeholder="Название новой конст-пати"><button class="primary" :disabled="busy">Создать</button></form>
  <p v-if="guild.error" class="notice error">{{ guild.error }}</p>
  <div class="group-grid"><article v-for="group in guild.groups" :key="group.id" class="group-card"><div class="group-card-heading"><h2>{{ group.name }}</h2><p>{{ group.players_count }} игроков</p></div><div v-if="group.players?.length" class="group-members"><RouterLink v-for="player in sortedPlayers(group)" :key="player.id" :to="`/players/${player.id}`"><strong><span v-if="isPartyLeader(player)" class="party-crown" title="PL · лидер конст-пати">♛</span>{{ player.nickname }}</strong><span class="group-member-meta"><span class="class-tag">{{ classLabels[player.class] }}</span><small title="Посещённые праймы и мини-праймы">{{ player.primes_count ?? 0 }} / {{ player.mini_activities_count ?? 0 }}</small></span></RouterLink></div><p v-else class="group-empty">В конст-пати пока никого нет</p><div v-if="canManageGroup(group)" class="card-actions"><button @click="rename(group)">Переименовать</button><button class="danger" @click="remove(group)">Удалить</button></div></article>
    <article class="group-card solo"><div class="group-card-heading"><h2>Одиночки</h2><p>Формируется автоматически</p></div></article>
  </div>
</section></template>
