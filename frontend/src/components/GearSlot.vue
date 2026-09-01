<script setup>
defineProps({
  slot: { type: String, required: true },
  item: { type: Object, default: null },
})

function statClass(stat) {
  return /^(Эффективность исцеления|Урон|Сопротивление|Защита|Эффективность ячейки)/i.test(stat) ? 'gear-stat-accent' : ''
}
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
      <div v-if="item.stats?.length" class="gear-detail-block gear-detail-stats">
        <p v-for="stat in item.stats" :key="stat" :class="statClass(stat)">{{ stat }}</p>
      </div>
      <div v-if="item.rune" class="gear-detail-block gear-detail-mod gear-detail-rune">
        <img :src="item.rune.image_url" alt=""><span>{{ item.rune.text }}</span>
      </div>
      <div v-if="item.gems?.length" class="gear-detail-block gear-detail-gems">
        <div v-for="(gem, index) in item.gems" :key="`${gem.text}-${index}`">
          <img :src="gem.image_url" alt=""><span>{{ gem.text }}</span>
        </div>
      </div>
      <div v-if="item.synthesis?.length" class="gear-detail-block gear-detail-synthesis">
        <small>Эффекты синтеза</small>
        <p v-for="stat in item.synthesis" :key="stat">{{ stat }}</p>
      </div>
    </div>
  </div>
</template>
