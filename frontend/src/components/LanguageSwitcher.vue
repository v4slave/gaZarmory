<script setup>
import { useLocale } from '../i18n.js'

defineProps({ floating: { type: Boolean, default: false } })
const { locale, setLocale, t } = useLocale()
</script>

<template>
  <div :class="['language-switcher', { 'language-switcher-floating': floating }]" role="group" :aria-label="t('Язык интерфейса')">
    <button type="button" :class="{ active: locale === 'ru' }" :aria-pressed="locale === 'ru'" :title="t('Русский')" @click="setLocale('ru')">RU</button>
    <button type="button" :class="{ active: locale === 'en' }" :aria-pressed="locale === 'en'" :title="t('Английский')" @click="setLocale('en')">EN</button>
  </div>
</template>
