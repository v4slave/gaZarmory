import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, loading: true, connectionError: '' }),
  getters: {
    authenticated: (state) => Boolean(state.user),
    canManage: (state) => (state.user?.roles ?? [state.user?.role]).some(role => ['guild_leader', 'micro_guild_leader', 'developer'].includes(role)),
    canAdmin: (state) => (state.user?.roles ?? [state.user?.role]).some(role => ['guild_leader', 'developer'].includes(role)),
    canCreateAuctions: (state) => (state.user?.roles ?? [state.user?.role]).some(role => ['guild_leader', 'developer'].includes(role)),
    canHandleTreasuryItems: (state) => (state.user?.roles ?? [state.user?.role]).some(role => ['guild_leader', 'developer'].includes(role)),
    canCreatePayouts: (state) => (state.user?.roles ?? [state.user?.role]).some(role => ['guild_leader', 'developer'].includes(role)),
    canAssignElevatedRoles: (state) => (state.user?.roles ?? [state.user?.role]).includes('guild_leader'),
    isGuildLeader: (state) => (state.user?.roles ?? [state.user?.role]).includes('guild_leader'),
    isDeveloper: (state) => (state.user?.roles ?? [state.user?.role]).includes('developer'),
    isPartyLeader: (state) => (state.user?.roles ?? [state.user?.role]).includes('party_leader'),
    canViewReadiness: (state) => (state.user?.roles ?? [state.user?.role]).some(role => ['guild_leader', 'micro_guild_leader', 'developer', 'party_leader'].includes(role)),
    partyGroupId: (state) => (state.user?.roles ?? [state.user?.role]).includes('party_leader') ? state.user?.player?.group_id ?? null : null,
  },
  actions: {
    async fetchMe() {
      this.connectionError = ''
      try { this.user = (await api.get('/api/me')).data }
      catch (error) {
        if (error.response?.status === 401) this.user = null
        else this.connectionError = 'Не удалось подключиться к серверу. Проверьте соединение и попробуйте снова.'
      }
      finally { this.loading = false }
    },
    async syncDiscordProfile() {
      if (!this.user) return
      try { this.user = (await api.post('/api/me/discord-profile/sync')).data } catch { /* Profile sync is best-effort. */ }
    },
    login() { window.location.assign(`${api.defaults.baseURL}/auth/discord`) },
    async logout() { await api.post('/api/logout'); this.user = null },
    async requestPlayerLink(playerId) { await api.post('/api/me/player', { player_id: playerId }); await this.fetchMe() },
    async renamePlayer(nickname) { await api.patch('/api/me/player/nickname', { nickname }); await this.fetchMe() },
    async changePlayerClass(playerClass) { await api.patch('/api/me/player/class', { class: playerClass }); await this.fetchMe() },
  },
})
