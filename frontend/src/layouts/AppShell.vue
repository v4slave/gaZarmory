<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useGuildStore } from '../stores/guild.js'
const auth = useAuthStore()
const guild = useGuildStore()
const route = useRoute()
const menuOpen = ref(false)
const showLinker = ref(false)
const selectedPlayerId = ref('')
const linking = ref(false)
const linkError = ref('')
const freePlayers = computed(() => guild.players.filter(player => player.is_active && !player.user))
const links = [
  ['/dashboard', 'Обзор'], ['/roster', 'Состав'], ['/groups', 'Конст-пати'],
  ['/activities', 'Активности'], ['/treasury', 'Казна'], ['/auctions', 'Аукционы'], ['/payouts', 'Нахрюк'],
]
watch(() => route.fullPath, () => { menuOpen.value = false })
async function openLinker() {
  showLinker.value = true
  linkError.value = ''
  await guild.fetchPlayers({ active: true, per_page: 100 })
}
async function linkProfile() {
  if (!selectedPlayerId.value) return
  linking.value = true; linkError.value = ''
  try { await auth.linkPlayer(selectedPlayerId.value); showLinker.value = false }
  catch (error) { linkError.value = error.response?.data?.message ?? 'Не удалось привязать игровой профиль.' }
  finally { linking.value = false }
}
</script>

<template>
  <div class="shell">
    <aside :class="{ open: menuOpen }">
      <div class="brand">
        <img src="/gaz-armory-logo.png" alt="GAZ ARMORY">
        <div>GAZ ARMORY<small>ArcheAge guild</small></div>
      </div>
      <nav><RouterLink v-for="link in links" :key="link[0]" :to="link[0]">{{ link[1] }}</RouterLink></nav>
      <RouterLink v-if="auth.canAdmin" class="admin" to="/admin">Администрирование</RouterLink>
    </aside>
    <button v-if="menuOpen" class="mobile-nav-backdrop" type="button" aria-label="Закрыть меню" @click="menuOpen=false"></button>
    <main>
      <header>
        <button class="mobile-menu-button" type="button" :aria-expanded="menuOpen" aria-label="Открыть меню" @click="menuOpen=!menuOpen"><span></span><span></span><span></span></button>
        <RouterLink class="mobile-brand" to="/dashboard">GAZ ARMORY</RouterLink>
        <div class="header-spacer"></div>
        <RouterLink v-if="auth.user?.player" class="user-link" :to="`/players/${auth.user.player.id}`">{{ auth.user.discord_display_name || auth.user.discord_username }}</RouterLink>
        <button v-else-if="auth.user" class="user-unlinked" title="Привязать игровой профиль" @click="openLinker">{{ auth.user.discord_display_name || auth.user.discord_username }} · привязать профиль</button>
        <button v-if="auth.authenticated" @click="auth.logout">Выйти</button>
        <button v-else-if="!auth.loading" class="primary" @click="auth.login">Войти через Discord</button>
      </header>
      <RouterView />
    </main>
    <div v-if="showLinker" class="modal" @click.self="showLinker=false">
      <form class="form-card" @submit.prevent="linkProfile">
        <h2>Привязать игровой профиль</h2>
        <p class="muted">Выберите своего персонажа. После привязки этот профиль нельзя будет занять другому пользователю.</p>
        <label>Игровой никнейм
          <select v-model="selectedPlayerId" required>
            <option value="" disabled>Выберите персонажа</option>
            <option v-for="player in freePlayers" :key="player.id" :value="player.id">{{ player.nickname }}</option>
          </select>
        </label>
        <p v-if="!guild.loading&&!freePlayers.length" class="empty">Свободных активных профилей не найдено.</p>
        <p v-if="linkError" class="notice error">{{ linkError }}</p>
        <div class="form-actions"><button type="button" @click="showLinker=false">Отмена</button><button class="primary" :disabled="linking||!selectedPlayerId">{{ linking?'Привязка…':'Привязать' }}</button></div>
      </form>
    </div>
  </div>
</template>
