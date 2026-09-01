<script setup>
defineProps({
  slot: { type: String, required: true },
  item: { type: Object, default: null },
})
</script>

<template>
  <div :class="['game-gear-slot', item ? `gear-grade-${item.grade}` : 'empty']" :title="item ? `${slot}: ${item.name} — ${item.quality}` : `${slot}: пусто`">
    <img v-if="item" :src="item.image_url" :alt="item.name" loading="lazy" referrerpolicy="no-referrer">
    <span v-else aria-hidden="true">×</span>
    <small>{{ slot }}</small>
    <div v-if="item" class="game-gear-tooltip"><b>{{ item.name }}</b><span>{{ item.quality }}</span></div>
  </div>
</template>
