<script setup>
import { useAuthStore } from '../stores/auth.js'
import { useLocale } from '../i18n.js'
import AdminNav from '../components/AdminNav.vue'
import NotificationCenter from '../components/NotificationCenter.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const auth = useAuthStore()
const { t } = useLocale()
</script>

<template><div class="admin-workspace"><header class="admin-workspace-top"><RouterLink class="admin-workspace-brand" to="/admin"><img src="/hamster-armory.png" alt=""><span><b>GAZ ARMORY</b><small>{{ t('Административное пространство') }}</small></span></RouterLink><RouterLink class="admin-back" to="/dashboard">← {{ t('Вернуться в гильдию') }}</RouterLink><div class="admin-workspace-profile"><LanguageSwitcher/><NotificationCenter/><RouterLink :to="`/players/${auth.user.player.id}`"><PlayerAvatar :player="{...auth.user.player,user:auth.user}" size="small"/><span>{{ auth.user.discord_display_name||auth.user.discord_username }}</span></RouterLink><button type="button" @click="auth.logout">{{ t('Выйти') }}</button></div></header><AdminNav class="admin-workspace-nav"/><main class="dev-content admin-workspace-content"><RouterView/></main></div></template>

<style scoped>
.admin-workspace{min-height:100vh;background:linear-gradient(rgba(2,2,2,.72),rgba(4,3,2,.85)),url('/images/gaz-armory-noir-background.png') center/cover fixed}.admin-workspace-top{position:sticky;z-index:180;top:0;display:grid;grid-template-columns:260px 1fr auto;align-items:center;min-height:68px;padding:0 22px;border-bottom:1px solid rgba(217,154,62,.32);background:rgba(6,6,6,.97)}.admin-workspace-brand,.admin-workspace-profile>a{display:flex;align-items:center;gap:10px;color:#f0e6d8;text-decoration:none}.admin-workspace-brand img{width:44px;height:44px;object-fit:contain}.admin-workspace-brand span,.admin-workspace-brand small{display:block}.admin-workspace-brand small{margin-top:3px;color:#a59582;font-size:9px}.admin-back{justify-self:start;color:#d4ad70;text-decoration:none}.admin-workspace-profile{display:flex;align-items:center;gap:10px}.admin-workspace-profile button{padding:8px 10px}.admin-workspace-nav{position:sticky;z-index:170;top:68px;margin:0!important;border-inline:0!important}.admin-workspace-content>section{width:min(1480px,calc(100% - 48px));margin:auto;padding:30px 0 46px}.admin-workspace-content :deep(.admin-tabs){display:none}@media(max-width:760px){.admin-workspace-top{grid-template-columns:1fr auto;padding:8px 12px}.admin-back{grid-column:1/-1;grid-row:2;padding:7px 0}.admin-workspace-profile>a span{display:none}.admin-workspace-nav{top:91px}.admin-workspace-content>section{width:calc(100% - 20px)}}
</style>
