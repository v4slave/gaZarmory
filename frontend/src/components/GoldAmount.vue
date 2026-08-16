<script setup>
defineProps({ value: { type: [String, Number], required: true } })
</script>

<template><span class="gold-amount"><span>{{ value }}</span><img src="/images/gold.png" alt="золото"></span></template>
