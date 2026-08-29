<script setup>
import { computed } from 'vue'

const props = defineProps({ page: { type: Number, default: 1 }, pages: { type: Number, default: 1 }, disabled: Boolean, label: { type: String, default: 'Страницы' } })
const emit = defineEmits(['change'])
const items = computed(() => {
  const last = props.pages
  if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1)
  const selected = new Set([1, last, props.page - 1, props.page, props.page + 1].filter(value => value >= 1 && value <= last))
  const sorted = [...selected].sort((a, b) => a - b)
  const result = []
  sorted.forEach((value, index) => { if (index && value - sorted[index - 1] > 1) result.push(`gap-${value}`); result.push(value) })
  return result
})
</script>

<template><nav v-if="pages>1" class="roster-pagination compact-pagination" :aria-label="label"><button type="button" :disabled="disabled||page<=1" @click="emit('change',page-1)">‹</button><template v-for="item in items" :key="item"><span v-if="typeof item==='string'" class="pagination-gap" aria-hidden="true">…</span><button v-else type="button" :class="{active:item===page}" :aria-current="item===page?'page':undefined" :disabled="disabled" @click="emit('change',item)">{{ item }}</button></template><button type="button" :disabled="disabled||page>=pages" @click="emit('change',page+1)">›</button></nav></template>
