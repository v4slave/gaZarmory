<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const users = ref([])
const items = ref([])
const definitions = ref([])
const name = ref('')
const icon = ref(null)
const busy = ref(false)
const userBusy = ref(null)
const error = ref('')
const userError = ref('')
const audit = ref({ data: [], current_page: 1, last_page: 1, total: 0 })
const auditActions = ref([])
const auditSearch = ref('')
const auditAction = ref('')
const auditLoading = ref(false)
const expandedAudit = ref(null)
const definitionBusy = ref(null)
const roleLabels = { guild_leader: 'ГЛ', developer: 'Разработчик', party_leader: 'PL', member: 'Участник' }
const auditLabels = { 'user.roles_changed':'Изменены роли', 'player.created':'Добавлен игрок', 'player.updated':'Изменён игрок', 'player.group_changed':'Игрок перемещён', 'player.deactivated':'Игрок ликвидирован', 'player.self_renamed':'Игрок сменил имя', 'player.self_class_changed':'Игрок сменил класс', 'player.discord_link_changed':'Изменена Discord-привязка', 'group.created':'Создана конст-пати', 'group.updated':'Изменена конст-пати', 'group.deleted':'Удалена конст-пати', 'activity.created':'Создана активность', 'activity.updated':'Изменена активность', 'activity.deleted':'Удалена активность', 'activity_loot.created':'Добавлен лут', 'prime.calculated':'Рассчитан прайм', 'mini_activity.calculated':'Рассчитан мини-прайм', 'loot_import.confirmed':'Подтверждён импорт лута', 'treasury_item.sold':'Продан предмет', 'treasury_item.issued':'Выдан предмет', 'auction.created':'Создан аукцион', 'auction.updated':'Изменён аукцион', 'auction.started':'Запущен аукцион', 'auction.cancelled':'Отменён аукцион', 'auction.bid_placed':'Сделана ставка', 'auction.finished':'Завершён аукцион', 'auction.finished_without_bids':'Аукцион завершён без ставок', 'payout.created':'Создан нахрюк', 'payout.calculated':'Рассчитан нахрюк', 'payout.completed':'Нахрюк выплачен', 'payout.cancelled':'Нахрюк отменён', 'loot_catalog.created':'Добавлен предмет справочника', 'loot_catalog.updated':'Изменён предмет справочника', 'loot_catalog.restored':'Восстановлен предмет справочника', 'loot_catalog.deactivated':'Удалён предмет справочника' }

function avatarUrl(user) {
  if (!user.discord_avatar) return null
  if (/^https?:\/\//i.test(user.discord_avatar)) return user.discord_avatar
  const extension = user.discord_avatar.startsWith('a_') ? 'gif' : 'png'
  return `https://cdn.discordapp.com/avatars/${user.discord_id}/${user.discord_avatar}.${extension}?size=64`
}

async function loadItems() { if (auth.canAdmin) items.value = (await api.get('/api/loot-catalog')).data }
async function loadDefinitions() { if (auth.canAdmin) definitions.value = (await api.get('/api/activity-definitions')).data }
async function loadUsers() { if (auth.canAdmin) users.value = (await api.get('/api/admin/users')).data }
async function loadAudit(page = 1) {
  if (!auth.canAdmin) return
  auditLoading.value = true
  try {
    const { data } = await api.get('/api/admin/audit-logs', { params: { page, search: auditSearch.value || undefined, action: auditAction.value || undefined } })
    audit.value = data.logs; auditActions.value = data.actions
  } finally { auditLoading.value = false }
}
function auditActor(log) { return log.user?.discord_display_name || log.user?.discord_username || 'Система' }
function auditEntity(log) { return `${log.entity_type?.split('\\').pop() ?? 'Объект'} #${log.entity_id ?? '—'}` }
function auditDetails(log) { return JSON.stringify({ было: log.old_values, стало: log.new_values }, null, 2) }

async function toggleRole(user, role, checked) {
  const currentRoles = user.roles?.length ? [...user.roles] : [user.role]
  const roles = checked ? [...new Set([...currentRoles, role])] : currentRoles.filter(value => value !== role)
  if (!roles.length) { userError.value = 'У пользователя должна остаться хотя бы одна роль.'; return }
  userBusy.value = user.id; userError.value = ''
  try {
    const { data } = await api.patch(`/api/admin/users/${user.id}/roles`, { roles })
    Object.assign(user, data)
    if (user.id === auth.user?.id) await auth.fetchMe()
    await loadAudit()
  } catch (requestError) {
    userError.value = requestError.response?.data?.message ?? Object.values(requestError.response?.data?.errors ?? {}).flat()[0] ?? 'Не удалось изменить роль.'
    await loadUsers()
  } finally { userBusy.value = null }
}

async function unlinkPlayer(user) {
  if (!user.player || !confirm(`Отвязать персонажа «${user.player.nickname}» от Discord-пользователя?`)) return
  userBusy.value = user.id; userError.value = ''
  try {
    await api.put(`/api/players/${user.player.id}/user`, { user_id: null })
    await loadUsers()
    if (user.id === auth.user?.id) await auth.fetchMe()
    await loadAudit()
  } catch (requestError) { userError.value = requestError.response?.data?.message ?? 'Не удалось отвязать персонажа.' }
  finally { userBusy.value = null }
}

async function deactivatePlayer(user) {
  if (!user.player || !confirm(`Ликвидировать персонажа «${user.player.nickname}»? История будет сохранена.`)) return
  userBusy.value = user.id; userError.value = ''
  try { await api.delete(`/api/players/${user.player.id}`); await Promise.all([loadUsers(), loadAudit()]) }
  catch (requestError) { userError.value = requestError.response?.data?.message ?? 'Не удалось ликвидировать персонажа.' }
  finally { userBusy.value = null }
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
onMounted(async () => { await Promise.all([loadItems(), loadDefinitions(), loadUsers(), loadAudit()]) })
</script>

<template>
  <section v-if="auth.canAdmin">
    <div class="page-heading"><div><p class="eyebrow">GAZ ARMORY · УПРАВЛЕНИЕ</p><h1>Администрирование</h1><p class="muted">Пользователи, права доступа и справочник предметов</p></div></div>


    <div class="admin-action-guide">
      <article><strong>Отвязать Discord</strong><p>Убирает связь аккаунта с персонажем. Персонаж, его посещения и финансовая история сохраняются, после чего профиль можно привязать другому пользователю.</p></article>
      <article class="danger-guide"><strong>Ликвидировать персонажа</strong><p>Деактивирует игровой профиль и убирает его из активного состава и списков выбора. История не удаляется, Discord-аккаунт остаётся.</p></article>
    </div>
    <div class="panel admin-users-panel">
      <div class="panel-title"><div><h2>Пользователи и доступ</h2><p class="muted">Назначайте только необходимые права. Последнего ГЛ понизить нельзя.</p></div><span class="muted">{{ users.length }} пользователей</span></div>
      <p v-if="userError" class="notice error">{{ userError }}</p>
      <div class="admin-user-list">
        <article v-for="user in users" :key="user.id" class="admin-user-row">
          <div class="admin-user-avatar"><span>{{ (user.discord_display_name || user.discord_username).slice(0, 1).toUpperCase() }}</span><img v-if="avatarUrl(user)" :src="avatarUrl(user)" alt="" referrerpolicy="no-referrer" @error="$event.currentTarget.remove()"></div>
          <div class="admin-user-identity"><strong>{{ user.discord_display_name || user.discord_username }}</strong><small>@{{ user.discord_username }}</small></div>
          <div class="admin-user-player"><RouterLink v-if="user.player" :to="`/players/${user.player.id}`">{{ user.player.nickname }}</RouterLink><span v-else>Профиль не привязан</span><small v-if="user.player?.group">{{ user.player.group.name }}</small><small v-else-if="user.player">Одиночка</small></div>
          <div class="admin-role-select"><span>Права доступа</span><label v-for="(label, value) in roleLabels" :key="value"><input type="checkbox" :checked="(user.roles ?? [user.role]).includes(value)" :disabled="userBusy === user.id" @change="toggleRole(user, value, $event.target.checked)">{{ label }}</label></div>
          <div class="admin-user-actions"><button title="Снять связь Discord-аккаунта с игровым персонажем, сохранив профиль и историю" :disabled="!user.player || userBusy === user.id" @click="unlinkPlayer(user)">Отвязать Discord</button><button class="danger" title="Деактивировать персонажа без удаления истории" :disabled="!user.player?.is_active || userBusy === user.id" @click="deactivatePlayer(user)">Ликвидировать персонажа</button></div>
        </article>
        <p v-if="!users.length" class="empty">Discord-пользователей пока нет.</p>
      </div>
    </div>

    <div class="panel audit-panel">
      <div class="panel-title"><div><h2>Журнал действий</h2><p class="muted">Неизменяемая история административных и финансовых операций</p></div><span class="muted">{{ audit.total }} записей</span></div>
      <form class="audit-filters" @submit.prevent="loadAudit(1)"><input v-model.trim="auditSearch" placeholder="Пользователь или действие"><select v-model="auditAction"><option value="">Все действия</option><option v-for="action in auditActions" :key="action" :value="action">{{ auditLabels[action] ?? action }}</option></select><button class="secondary" :disabled="auditLoading">Найти</button><button type="button" :disabled="auditLoading" @click="auditSearch='';auditAction='';loadAudit(1)">Сбросить</button></form>
      <div class="table-wrap flat audit-table"><table><thead><tr><th>Дата</th><th>Пользователь</th><th>Действие</th><th>Объект</th><th>IP</th><th></th></tr></thead><tbody><template v-for="log in audit.data" :key="log.id"><tr><td class="muted">{{ new Date(log.created_at).toLocaleString('ru-RU') }}</td><td>{{ auditActor(log) }}</td><td><strong>{{ auditLabels[log.action] ?? log.action }}</strong></td><td class="muted">{{ auditEntity(log) }}</td><td class="muted">{{ log.ip_address ?? '—' }}</td><td class="right"><button class="audit-toggle" @click="expandedAudit=expandedAudit===log.id?null:log.id">{{ expandedAudit===log.id?'Скрыть':'Детали' }}</button></td></tr><tr v-if="expandedAudit===log.id" class="audit-detail-row"><td colspan="6"><pre>{{ auditDetails(log) }}</pre></td></tr></template><tr v-if="!auditLoading&&!audit.data.length"><td colspan="6" class="empty">Записей не найдено.</td></tr><tr v-if="auditLoading"><td colspan="6" class="empty">Загрузка…</td></tr></tbody></table></div>
      <div v-if="audit.last_page>1" class="audit-pagination"><button :disabled="auditLoading||audit.current_page<=1" @click="loadAudit(audit.current_page-1)">← Назад</button><span>{{ audit.current_page }} / {{ audit.last_page }}</span><button :disabled="auditLoading||audit.current_page>=audit.last_page" @click="loadAudit(audit.current_page+1)">Далее →</button></div>
    </div>

    <div class="panel admin-catalog-create"><h2>Добавить предмет в справочник</h2><form class="catalog-admin-form" @submit.prevent="add"><label>Название<input v-model.trim="name" required maxlength="255" placeholder="Название предмета"></label><label>Иконка<input id="catalog-icon" type="file" required accept="image/png,image/jpeg,image/webp,image/gif" @change="icon=$event.target.files[0]??null"></label><button class="primary" :disabled="busy||!icon">{{ busy?'Загрузка…':'Добавить предмет' }}</button></form><p v-if="error" class="notice error">{{ error }}</p></div>
    <div class="panel activity-definition-icons"><div class="panel-title"><div><h2>Изображения активностей</h2><p class="muted">Одно изображение используется во всех активностях выбранного события</p></div><span class="muted">{{ definitions.length }} событий</span></div><p v-if="error" class="notice error">{{ error }}</p><div class="definition-icon-grid"><article v-for="definition in definitions" :key="definition.id"><div class="definition-icon-preview"><img v-if="definition.icon_url" :src="definition.icon_url" :alt="definition.name"><span v-else>{{ definition.name.slice(0,1) }}</span></div><div><strong>{{ definition.name }}</strong><small>{{ definition.type==='prime'?'Прайм':'Мини-прайм' }}</small></div><label class="definition-upload"><input type="file" accept="image/png,image/jpeg,image/webp,image/gif" :disabled="definitionBusy===definition.id" @change="uploadDefinitionIcon(definition,$event.target.files[0]);$event.target.value=''">{{ definitionBusy===definition.id?'Загрузка…':definition.icon_url?'Заменить':'Загрузить' }}</label><button v-if="definition.icon_url" class="danger" :disabled="definitionBusy===definition.id" @click="deleteDefinitionIcon(definition)">Удалить</button></article></div></div>
    <div class="panel catalog-panel"><div class="panel-title"><h2>Справочник лута</h2><span class="muted">{{ items.length }} предметов</span></div><div v-if="items.length" class="catalog-grid"><article v-for="item in items" :key="item.id"><img :src="item.icon_url" :alt="item.name"><strong>{{ item.name }}</strong><button class="danger" @click="remove(item)">×</button></article></div><p v-else class="empty">Предметы ещё не загружены.</p></div>
  </section>
  <section v-else><div class="panel"><h1>Доступ запрещён</h1><p class="muted">Администрирование доступно только ГЛ и Разработчику.</p></div></section>
</template>
