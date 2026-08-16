<script setup>
import { useRoute } from 'vue-router'
const route = useRoute()
</script>

<template><section><p class="eyebrow">GAZ ARMORY</p><h1>{{ route.meta.title }}</h1><div class="panel"><p class="empty">Раздел подготовлен для следующего этапа реализации.</p></div></section></template>
