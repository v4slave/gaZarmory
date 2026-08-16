<script setup>
defineProps({ label: String, value: { type: [String, Number], default: '—' }, note: String, accent: Boolean, gold: Boolean })
</script>
<template><article :class="['stat-card',{accent}]"><span>{{ label }}</span><strong><span>{{ value }}</span><img v-if="gold" class="gold-icon" src="/images/gold.png" alt="золото"></strong><small v-if="note">{{ note }}</small></article></template>
