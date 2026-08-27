<script setup>
import { useLocale } from '../i18n.js'

defineProps({ value: { type: [String, Number], default: '—' } })
const { t } = useLocale()
</script>

<template><span class="token-amount"><span>{{ value }}</span><img src="/images/treasury-token.png" :alt="t('жетоны')"></span></template>
