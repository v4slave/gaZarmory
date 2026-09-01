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
    <div v-if="item" class="game-gear-tooltip">
      <header>
        <img :src="item.image_url" alt="">
        <div><span>{{ item.quality }}</span><b>{{ item.name }}</b></div>
      </header>
      <section v-if="item.stats?.length" class="gear-detail-stats">
        <p v-for="stat in item.stats" :key="stat">{{ stat }}</p>
      </section>
      <section v-if="item.rune" class="gear-detail-mod gear-detail-rune">
        <img :src="item.rune.image_url" alt=""><span>{{ item.rune.text }}</span>
      </section>
      <section v-if="item.gems?.length" class="gear-detail-gems">
        <div v-for="(gem, index) in item.gems" :key="`${gem.text}-${index}`">
          <img :src="gem.image_url" alt=""><span>{{ gem.text }}</span>
        </div>
      </section>
      <section v-if="item.synthesis?.length" class="gear-detail-synthesis">
        <small>Эффекты синтеза</small>
        <p v-for="stat in item.synthesis" :key="stat">{{ stat }}</p>
      </section>
    </div>
  </div>
</template>
