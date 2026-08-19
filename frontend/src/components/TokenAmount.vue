<script setup>
defineProps({ value: { type: [String, Number], default: '—' } })
</script>

<template><span class="token-amount"><span>{{ value }}</span><img src="/images/treasury-token.png" alt="жетоны"></span></template>
