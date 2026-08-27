<script setup>
import { useLocale } from '../i18n.js'

defineProps({ rows:{type:Number,default:5}, columns:{type:Number,default:4} })
const { t } = useLocale()
</script>
<template><div class="skeleton-list" :aria-label="t('Загрузка')"><div v-for="row in rows" :key="row" class="skeleton-row"><i v-for="column in columns" :key="column"></i></div></div></template>
