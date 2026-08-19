<script setup>
import { computed } from 'vue'

const props = defineProps({ player: { type: Object, default: null }, size: { type: String, default: 'medium' } })
const user = computed(() => props.player?.user ?? null)
const label = computed(() => props.player?.nickname || user.value?.discord_display_name || user.value?.discord_username || '?')
const avatarUrl = computed(() => {
  if (!user.value?.discord_avatar || !user.value?.discord_id) return null
  if (/^https?:\/\//i.test(user.value.discord_avatar)) return user.value.discord_avatar
  const extension = user.value.discord_avatar.startsWith('a_') ? 'gif' : 'png'
  return `https://cdn.discordapp.com/avatars/${user.value.discord_id}/${user.value.discord_avatar}.${extension}?size=128`
})
</script>

<template>
  <span :class="['player-avatar', `player-avatar-${size}`]" :title="label">
    <img v-if="avatarUrl" :src="avatarUrl" :alt="`Аватар ${label}`" referrerpolicy="no-referrer" @error="$event.currentTarget.remove()">
    <span>{{ label.slice(0, 1).toUpperCase() }}</span>
  </span>
</template>
