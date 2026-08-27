<script setup>
import { useLocale } from '../i18n.js'

defineProps({ label: String, value: { type: [String, Number], default: '—' }, note: String, accent: Boolean, gold: Boolean, secondaryValue: { type: [String, Number], default: null }, secondaryIcon: String, secondaryAlt: String })
const { t } = useLocale()
</script>
<template><article :class="['stat-card',{accent}]"><span>{{ label }}</span><strong><span>{{ value }}</span><img v-if="gold" class="gold-icon" src="/images/gold.png" :alt="t('золото')"><template v-if="secondaryValue!==null"><span class="stat-divider">/</span><span>{{ secondaryValue }}</span><img v-if="secondaryIcon" class="token-icon" :src="secondaryIcon" :alt="secondaryAlt"></template></strong><small v-if="note">{{ note }}</small></article></template>
