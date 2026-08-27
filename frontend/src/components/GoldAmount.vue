<script setup>
import { useLocale } from '../i18n.js'

defineProps({ value: { type: [String, Number], required: true } })
const { t } = useLocale()
</script>

<template><span class="gold-amount"><span>{{ value }}</span><img src="/images/gold.png" :alt="t('золото')"></span></template>
