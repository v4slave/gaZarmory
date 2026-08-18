import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useGuildStore = defineStore('guild', {
  state: () => ({ players: [], groups: [], pagination: null, loading: false, error: '', filters: { search: '', class: '' } }),
  actions: {
    async fetchPlayers(paramsOverride = null) {
      this.loading = true; this.error = ''
      try {
        const source = paramsOverride ?? this.filters
        const params = Object.fromEntries(Object.entries(source)
          .filter(([, value]) => value !== '' && value !== false)
          .map(([key, value]) => [key, typeof value === 'boolean' ? Number(value) : value]))
        const { data } = await api.get('/api/players', { params })
        this.players = data.data; this.pagination = data.meta ?? { current_page: data.current_page, last_page: data.last_page, total: data.total }
      } catch (error) { this.error = error.response?.status === 401 ? 'Войдите через Discord, чтобы загрузить состав.' : (error.response?.data?.message ?? 'Backend недоступен. Запустите Laravel API.') }
      finally { this.loading = false }
    },
    async fetchGroups() {
      try { this.groups = (await api.get('/api/groups')).data }
      catch (error) { this.error = error.response?.data?.message ?? 'Не удалось загрузить конст-пати.' }
    },
    async createPlayer(payload) { await api.post('/api/players', payload); await this.fetchPlayers() },
    async updatePlayer(id, payload) { await api.put(`/api/players/${id}`, payload); await this.fetchPlayers() },
    async movePlayer(id, groupId) { await api.put(`/api/players/${id}/group`, { group_id: groupId }); await this.fetchPlayers() },
    async deletePlayer(id) { await api.delete(`/api/players/${id}/permanent`); await Promise.all([this.fetchPlayers(), this.fetchGroups()]) },
    async createGroup(name) { await api.post('/api/groups', { name }); await this.fetchGroups() },
    async renameGroup(id, name) { await api.put(`/api/groups/${id}`, { name }); await this.fetchGroups() },
    async deleteGroup(id) { await api.delete(`/api/groups/${id}`); await Promise.all([this.fetchGroups(), this.fetchPlayers()]) },
  },
})
