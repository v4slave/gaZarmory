import { defineStore } from 'pinia'
import { api } from '../api.js'

export const useActivitiesStore = defineStore('activities', {
  state: () => ({ items: [], definitions: [], current: null, lootImport: null, loading: false, error: '', filters: { type: '', date_from: '', date_to: '' } }),
  actions: {
    async fetchDefinitions() {
      try { this.definitions = (await api.get('/api/activity-definitions')).data }
      catch (e) { this.error = e.response?.data?.message ?? 'Не удалось загрузить справочник событий.' }
    },
    async fetchActivities() {
      this.loading = true; this.error = ''
      try {
        const params = Object.fromEntries(Object.entries(this.filters).filter(([, value]) => value))
        this.items = (await api.get('/api/activities', { params })).data.data
      } catch (e) { this.error = e.response?.data?.message ?? 'Backend недоступен.' }
      finally { this.loading = false }
    },
    async fetchActivity(id) {
      this.loading = true; this.error = ''
      try { this.current = (await api.get(`/api/activities/${id}`)).data; this.lootImport = this.current.loot_imports?.[0] ?? null }
      catch (e) { this.error = e.response?.data?.message ?? 'Не удалось загрузить активность.' }
      finally { this.loading = false }
    },
    async createActivity(payload) { return (await api.post('/api/activities', payload)).data },
    async addPlayers(id, playerIds) { await api.post(`/api/activities/${id}/players`, { player_ids: playerIds }); await this.fetchActivity(id) },
    async removePlayer(id, playerId) { await api.delete(`/api/activities/${id}/players/${playerId}`); await this.fetchActivity(id) },
    async updateActivity(id, payload) { await api.patch(`/api/activities/${id}`, payload); await Promise.all([this.fetchActivity(id),this.fetchActivities()]) },
    async deleteActivity(id) { await api.delete(`/api/activities/${id}`); this.current = null; await this.fetchActivities() },
    async addLoot(id, payload) {
      const body = new FormData()
      Object.entries(payload).forEach(([key, value]) => { if (value !== null && value !== '') body.append(key, value) })
      await api.post(`/api/activities/${id}/loot`, body)
      await this.fetchActivity(id)
    },
    async removeLoot(id, lootId) { await api.delete(`/api/activities/${id}/loot/${lootId}`); await this.fetchActivity(id) },
    async calculatePrime(id) { this.current = (await api.post(`/api/activities/${id}/calculate-prime`)).data },
    async completeActivity(id) { this.current = (await api.post(`/api/activities/${id}/complete`)).data },
    async uploadLootTable(activityId, file) {
      const body = new FormData(); body.append('file', file)
      this.lootImport = (await api.post(`/api/activities/${activityId}/loot-imports`, body, { timeout: 30000 })).data
      return this.lootImport
    },
    async updateLootRow(importId, rowId, payload) {
      const updated = (await api.put(`/api/loot-imports/${importId}/rows/${rowId}`, payload)).data
      const index = this.lootImport.rows.findIndex(row => row.id === rowId)
      if (index >= 0) this.lootImport.rows[index] = updated
    },
    async confirmLootImport(importId) {
      this.lootImport = (await api.post(`/api/loot-imports/${importId}/confirm`)).data
      await this.fetchActivity(this.current.id)
    },
  },
})
