<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const users = ref([])
const linkRequests = ref([])
const items = ref([])
const definitions = ref([])
const name = ref('')
const icon = ref(null)
const busy = ref(false)
const userBusy = ref(null)
const error = ref('')
const userError = ref('')
const userSearch = ref('')
const definitionBusy = ref(null)
const roleLabels = { guild_leader: 'ГЛ', micro_guild_leader: 'Микро-ГЛ', developer: 'Разработчик', party_leader: 'PL', member: 'Участник' }
const filteredUsers = computed(() => {
  const query = userSearch.value.trim().toLocaleLowerCase('ru-RU')
  if (!query) return users.value
  return users.value.filter(user => [user.discord_display_name, user.discord_username, user.player?.nickname]
    .some(value => value?.toLocaleLowerCase('ru-RU').includes(query)))
})
function avatarUrl(user) {
  if (!user.discord_avatar) return null
  if (/^https?:\/\//i.test(user.discord_avatar)) return user.discord_avatar
  const extension = user.discord_avatar.startsWith('a_') ? 'gif' : 'png'
  return `https://cdn.discordapp.com/avatars/${user.discord_id}/${user.discord_avatar}.${extension}?size=64`
}

async function loadItems() { if (auth.canAdmin) items.value = (await api.get('/api/loot-catalog')).data }
async function loadDefinitions() { if (auth.canAdmin) definitions.value = (await api.get('/api/activity-definitions')).data }
async function loadUsers() { if (auth.canAdmin) users.value = (await api.get('/api/admin/users')).data }
async function loadLinkRequests() { if (auth.canAdmin) linkRequests.value = (await api.get('/api/admin/player-link-requests')).data }
async function toggleRole(user, role, checked) {
  const currentRoles = user.roles?.length ? [...user.roles] : [user.role]
  const roles = checked ? [...new Set([...currentRoles, role])] : currentRoles.filter(value => value !== role)
  if (!roles.length) { userError.value = 'У пользователя должна остаться хотя бы одна роль.'; return }
  userBusy.value = user.id; userError.value = ''
  try {
    const { data } = await api.patch(`/api/admin/users/${user.id}/roles`, { roles })
    Object.assign(user, data)
    if (user.id === auth.user?.id) await auth.fetchMe()
  } catch (requestError) {
    userError.value = requestError.response?.data?.message ?? Object.values(requestError.response?.data?.errors ?? {}).flat()[0] ?? 'Не удалось изменить роль.'
    await loadUsers()
  } finally { userBusy.value = null }
}
function roleOptionDisabled(user, role) {
  if (userBusy.value === user.id) return true
  if (auth.canAssignElevatedRoles) return false
  const roles = user.roles ?? [user.role]
  return ['guild_leader', 'developer'].includes(role) || roles.some(value => ['guild_leader', 'developer'].includes(value))
}

async function unlinkPlayer(user) {
  if (!user.player || !confirm(`Отвязать персонажа «${user.player.nickname}» от Discord-пользователя?`)) return
  userBusy.value = user.id; userError.value = ''
  try {
    await api.put(`/api/players/${user.player.id}/user`, { user_id: null })
    await loadUsers()
    if (user.id === auth.user?.id) await auth.fetchMe()
  } catch (requestError) { userError.value = requestError.response?.data?.message ?? 'Не удалось отвязать персонажа.' }
  finally { userBusy.value = null }
}

async function deactivatePlayer(user) {
  if (!user.player || !confirm(`Ликвидировать персонажа «${user.player.nickname}»? История будет сохранена.`)) return
  userBusy.value = user.id; userError.value = ''
  try { await api.delete(`/api/players/${user.player.id}`); await loadUsers() }
  catch (requestError) { userError.value = requestError.response?.data?.message ?? 'Не удалось ликвидировать персонажа.' }
  finally { userBusy.value = null }
}

async function activatePlayer(user) {
  if (!user.player || !confirm(`Восстановить персонажа «${user.player.nickname}» в активном составе?`)) return
  userBusy.value = user.id; userError.value = ''
  try { await api.post(`/api/players/${user.player.id}/activate`); await loadUsers() }
  catch (requestError) { userError.value = requestError.response?.data?.message ?? 'Не удалось восстановить персонажа.' }
  finally { userBusy.value = null }
}

async function deleteUser(user) {
  const playerNote = user.player ? ` Персонаж «${user.player.nickname}» будет отвязан, но сохранится вместе с историей.` : ''
  if (!confirm(`Удалить Discord-пользователя @${user.discord_username} из базы?${playerNote}`)) return
  userBusy.value = user.id; userError.value = ''
  try {
    await api.delete(`/api/admin/users/${user.id}`)
    await loadUsers()
  } catch (requestError) {
    userError.value = Object.values(requestError.response?.data?.errors ?? {}).flat()[0] ?? requestError.response?.data?.message ?? 'Не удалось удалить пользователя.'
  } finally { userBusy.value = null }
}

async function reviewLinkRequest(linkRequest, decision) {
  userBusy.value = `link-${linkRequest.id}`; userError.value = ''
  try {
    await api.post(`/api/admin/player-link-requests/${linkRequest.id}/${decision}`)
    await Promise.all([loadLinkRequests(), loadUsers()])
  } catch (requestError) {
    userError.value = Object.values(requestError.response?.data?.errors ?? {}).flat()[0] ?? requestError.response?.data?.message ?? 'Не удалось обработать заявку.'
  } finally { userBusy.value = null }
}

async function add() {
  busy.value = true; error.value = ''
  try {
    const body = new FormData(); body.append('name', name.value); body.append('icon', icon.value)
    await api.post('/api/loot-catalog', body)
    name.value = ''; icon.value = null; document.querySelector('#catalog-icon').value = ''; await loadItems()
  } catch (requestError) { error.value = requestError.response?.data?.message ?? Object.values(requestError.response?.data?.errors ?? {}).flat()[0] ?? 'Не удалось добавить предмет.' }
  finally { busy.value = false }
}

async function remove(item) { if (confirm(`Убрать «${item.name}» из доступного лута?`)) { await api.delete(`/api/loot-catalog/${item.id}`); await loadItems() } }
async function uploadDefinitionIcon(definition, file) { if(!file)return;definitionBusy.value=definition.id;error.value='';try{const body=new FormData();body.append('icon',file);const {data}=await api.post(`/api/activity-definitions/${definition.id}/icon`,body);Object.assign(definition,data)}catch(e){error.value=Object.values(e.response?.data?.errors??{}).flat()[0]??e.response?.data?.message??'Не удалось загрузить изображение.'}finally{definitionBusy.value=null} }
async function deleteDefinitionIcon(definition) { if(!confirm(`Удалить изображение события «${definition.name}»?`))return;definitionBusy.value=definition.id;try{const {data}=await api.delete(`/api/activity-definitions/${definition.id}/icon`);Object.assign(definition,data)}finally{definitionBusy.value=null} }
onMounted(async () => { await Promise.all([loadItems(), loadDefinitions(), loadUsers(), loadLinkRequests()]) })
</script>

<template>
  <section v-if="auth.canAdmin">
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · УПРАВЛЕНИЕ</p><h1>Админка</h1><p class="muted">Пользователи, права доступа и справочник предметов</p></div></div>


    <div class="admin-action-guide">
      <article><strong>Отвязать Discord</strong><p>Убирает связь аккаунта с персонажем. Персонаж, его посещения и финансовая история сохраняются, после чего профиль можно привязать другому пользователю.</p></article>
      <article class="danger-guide"><strong>Ликвидировать персонажа</strong><p>Деактивирует игровой профиль и убирает его из активного состава и списков выбора. История не удаляется, Discord-аккаунт остаётся.</p></article>
    </div>
    <div class="panel link-request-panel">
      <div class="panel-title"><div><h2>Заявки на привязку</h2><p class="muted">Проверьте Discord-пользователя и персонажа перед подтверждением</p></div><span class="muted">{{ linkRequests.length }} ожидают</span></div>
      <div v-if="linkRequests.length" class="link-request-list">
        <article v-for="linkRequest in linkRequests" :key="linkRequest.id">
          <div><strong>{{ linkRequest.user?.discord_display_name || linkRequest.user?.discord_username }}</strong><small>@{{ linkRequest.user?.discord_username }}</small></div>
          <span>хочет привязать</span>
          <div><strong>{{ linkRequest.player?.nickname }}</strong><small>{{ linkRequest.player?.class }}</small></div>
          <div class="link-request-actions"><button class="primary" :disabled="userBusy===`link-${linkRequest.id}`" @click="reviewLinkRequest(linkRequest,'approve')">Подтвердить</button><button class="danger" :disabled="userBusy===`link-${linkRequest.id}`" @click="reviewLinkRequest(linkRequest,'reject')">Отклонить</button></div>
        </article>
      </div>
      <p v-else class="empty">Новых заявок нет.</p>
    </div>
    <div class="panel admin-users-panel">
      <div class="panel-title"><div><h2>Пользователи и доступ</h2><p class="muted">Назначайте только необходимые права. Последнего ГЛ понизить нельзя.</p></div><span class="muted">{{ users.length }} пользователей</span></div>
      <input v-model="userSearch" class="admin-user-search" type="search" placeholder="Найти по Discord или игровому никнейму">
      <p v-if="userError" class="notice error">{{ userError }}</p>
      <div class="admin-user-list">
        <article v-for="user in filteredUsers" :key="user.id" class="admin-user-row">
          <div class="admin-user-avatar"><span>{{ (user.discord_display_name || user.discord_username).slice(0, 1).toUpperCase() }}</span><img v-if="avatarUrl(user)" :src="avatarUrl(user)" alt="" referrerpolicy="no-referrer" @error="$event.currentTarget.remove()"></div>
          <div class="admin-user-identity"><strong>{{ user.discord_display_name || user.discord_username }}</strong><small>@{{ user.discord_username }}</small></div>
          <div class="admin-user-player"><RouterLink v-if="user.player" :to="`/players/${user.player.id}`">{{ user.player.nickname }}</RouterLink><span v-else>Профиль не привязан</span><small v-if="user.player?.group">{{ user.player.group.name }}</small><small v-else-if="user.player">Одиночка</small></div>
          <div class="admin-role-select"><span>Права доступа</span><label v-for="(label, value) in roleLabels" :key="value"><input type="checkbox" :checked="(user.roles ?? [user.role]).includes(value)" :disabled="roleOptionDisabled(user,value)" @change="toggleRole(user, value, $event.target.checked)">{{ label }}</label></div>
          <details class="admin-actions-menu"><summary>Действия</summary><div><button :disabled="!user.player || userBusy === user.id" @click="unlinkPlayer(user)">Отвязать Discord</button><button v-if="user.player?.is_active" class="danger" :disabled="userBusy === user.id" @click="deactivatePlayer(user)">Ликвидировать персонажа</button><button v-else-if="user.player" :disabled="userBusy === user.id" @click="activatePlayer(user)">Восстановить персонажа</button><button class="danger" :disabled="user.id === auth.user?.id || userBusy === user.id" @click="deleteUser(user)">Удалить пользователя</button></div></details>
        </article>
        <p v-if="!filteredUsers.length" class="empty">Пользователи не найдены.</p>
      </div>
    </div>

    <div class="panel activity-definition-icons"><div class="panel-title"><div><h2>Справочник активностей</h2><p class="muted">Одно изображение используется во всех активностях выбранного события</p></div><span class="muted">{{ definitions.length }} событий</span></div><p v-if="error" class="notice error">{{ error }}</p><div class="definition-icon-grid"><article v-for="definition in definitions" :key="definition.id"><div class="definition-icon-preview"><img v-if="definition.icon_url" :src="definition.icon_url" :alt="definition.name"><span v-else>{{ definition.name.slice(0,1) }}</span></div><div><strong>{{ definition.name }}</strong><small>{{ definition.type==='prime'?'Прайм':'Мини-прайм' }}</small></div><label class="definition-upload"><input type="file" accept="image/png,image/jpeg,image/webp,image/gif" :disabled="definitionBusy===definition.id" @change="uploadDefinitionIcon(definition,$event.target.files[0]);$event.target.value=''">{{ definitionBusy===definition.id?'Загрузка…':definition.icon_url?'Заменить':'Загрузить' }}</label><button v-if="definition.icon_url" class="danger" :disabled="definitionBusy===definition.id" @click="deleteDefinitionIcon(definition)">Удалить</button></article></div></div>
    <div class="panel catalog-panel"><div class="panel-title"><h2>Справочник лута</h2><span class="muted">{{ items.length }} предметов</span></div><div v-if="items.length" class="catalog-grid"><article v-for="item in items" :key="item.id"><img :src="item.icon_url" :alt="item.name"><strong>{{ item.name }}</strong><button class="danger" @click="remove(item)">×</button></article></div><p v-else class="empty">Предметы ещё не загружены.</p></div>
    <div class="panel admin-catalog-create"><h2>Добавить предмет в справочник</h2><form class="catalog-admin-form" @submit.prevent="add"><label>Название<input v-model.trim="name" required maxlength="255" placeholder="Название предмета"></label><label>Иконка<input id="catalog-icon" type="file" required accept="image/png,image/jpeg,image/webp,image/gif" @change="icon=$event.target.files[0]??null"></label><button class="primary" :disabled="busy||!icon">{{ busy?'Загрузка…':'Добавить предмет' }}</button></form><p v-if="error" class="notice error">{{ error }}</p></div>
  </section>
  <section v-else><div class="panel"><h1>Доступ запрещён</h1><p class="muted">Админка доступна только ГЛ и Разработчику.</p></div></section>
</template>
