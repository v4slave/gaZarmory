<script setup>
defineProps({ label: String, value: { type: [String, Number], default: '—' }, note: String, accent: Boolean })
</script>
<template><article :class="['stat-card',{accent}]"><span>{{ label }}</span><strong>{{ value }}</strong><small v-if="note">{{ note }}</small></article></template>

