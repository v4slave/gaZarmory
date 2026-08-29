<script setup>
import { onMounted } from 'vue'
import AppIcon from '../components/AppIcon.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import { useLocale } from '../i18n.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const { t } = useLocale()
onMounted(() => auth.syncDiscordProfile())
const primaryNavigation = [
  { to: '/dashboard', icon: 'home', label: 'Главная' },
  { to: '/roster', icon: 'users', label: 'Состав' },
  { to: '/groups', icon: 'groups', label: 'Конст-пати' },
  { to: '/activities', icon: 'sword', label: 'Активности' },
  { to: '/media', icon: 'play', label: 'Контент' },
  { to: '/treasury', icon: 'treasury', label: 'Казна' },
  { to: '/auctions', icon: 'auction', label: 'Аукционы' },
]
const managementNavigation = [
  { to: '/payouts', icon: 'payout', label: 'Нахрюк' },
  { to: '/roster-readiness', icon: 'readiness', label: 'Готовность состава', permission: 'canViewReadiness' },
  { to: '/attendance-analytics', icon: 'attendance', label: 'Посещаемость', permission: 'canViewReadiness' },
  { to: '/financial-reconciliation', icon: 'reconcile', label: 'Финансовая сверка', permission: 'canHandleTreasuryItems' },
  { to: '/admin', icon: 'settings', label: 'Админка', permission: 'canAdmin' },
]
</script>

<template>
  <div class="dev-shell">
    <header class="dev-top">
      <RouterLink class="dev-brand" to="/dashboard">
        <img src="/hamster-armory.png" alt="Хомяк GAZ ARMORY">
        <span><b>GAZ ARMORY</b><small>ArcheAge guild</small></span>
      </RouterLink>
      <div class="dev-menus">
        <nav class="dev-primary" aria-label="Primary navigation">
          <RouterLink v-for="item in primaryNavigation" :key="item.to" :to="item.to">
            <AppIcon :name="item.icon" :size="18"/><span>{{ item.label }}</span>
          </RouterLink>
          <a class="dev-discord-link" href="https://discord.gg/gaz" target="_blank" rel="noopener noreferrer" :title="t('Открыть Discord гильдии')">
            <span class="dev-discord-icon" aria-hidden="true">◉</span><span>Discord</span><b aria-hidden="true">↗</b>
          </a>
        </nav>
        <nav class="dev-management" aria-label="Guild management">
          <RouterLink v-for="item in managementNavigation.filter(item => !item.permission || auth[item.permission])" :key="item.to" :to="item.to">
            <AppIcon :name="item.icon" :size="15"/><span>{{ item.label }}</span>
          </RouterLink>
        </nav>
      </div>
      <div class="dev-profile">
        <RouterLink class="dev-profile-link" :to="`/players/${auth.user.player.id}`" :title="t('Открыть свой профиль')">
          <PlayerAvatar :player="{ ...auth.user.player, user: auth.user }" size="small"/>
          <span><b>{{ auth.user?.discord_display_name || auth.user?.discord_username }}</b><small>{{ auth.user?.player?.nickname }}</small></span>
        </RouterLink>
        <button type="button" @click="auth.logout">Выйти</button>
      </div>
    </header>
    <main class="dev-content"><RouterView/></main>
  </div>
</template>

<style scoped>
.dev-shell{min-height:100vh;color:#f3ede3;background:linear-gradient(90deg,rgba(3,3,3,.55),rgba(5,4,3,.3) 48%,rgba(3,3,3,.54)),linear-gradient(rgba(0,0,0,.2),rgba(0,0,0,.48)),url('/images/gaz-armory-noir-background.png') center top/cover fixed}
.dev-top{position:sticky;z-index:180;top:0;display:grid;grid-template-columns:220px minmax(0,1fr) 330px;grid-template-rows:56px 40px;min-height:96px;border-bottom:1px solid rgba(217,154,62,.3);background:linear-gradient(180deg,rgba(5,5,5,.98),rgba(8,7,6,.965));box-shadow:0 10px 34px rgba(0,0,0,.42);backdrop-filter:blur(12px)}
.dev-brand{grid-row:1/3;display:flex;align-items:center;gap:11px;padding:0 20px;color:#f3ede3;text-decoration:none}.dev-brand img{width:48px;height:48px;object-fit:contain}.dev-brand b,.dev-brand small{display:block}.dev-brand b{font-size:16px;letter-spacing:.07em}.dev-brand small,.dev-profile small{margin-top:3px;color:#918777;font-size:9px}
.dev-menus{display:contents}.dev-menus nav{display:flex;justify-content:center;gap:5px;margin:0}.dev-menus a{display:flex;align-items:center;justify-content:center;gap:8px;color:#bdb4a8;text-decoration:none;white-space:nowrap;transition:.16s ease}.dev-primary{grid-column:2;grid-row:1}.dev-primary a{min-width:100px;padding:0 13px;border:1px solid transparent;border-bottom-color:rgba(217,154,62,.08);border-radius:0;background:transparent;font-size:12px}.dev-primary a:hover{color:#f3e3c9;background:linear-gradient(180deg,rgba(217,154,62,.03),rgba(217,154,62,.1))}.dev-primary a.router-link-active{color:#efb85f;border-color:rgba(217,154,62,.5);border-bottom-color:#e5a846;border-radius:6px 6px 0 0;background:linear-gradient(180deg,rgba(102,66,24,.3),rgba(46,30,13,.48));box-shadow:0 5px 18px rgba(188,119,28,.11),inset 0 1px rgba(255,225,173,.05)}
.dev-primary .dev-discord-link{min-width:auto;margin-left:7px;padding-inline:12px;color:#c7caff;border-color:rgba(88,101,242,.38);border-radius:6px;background:rgba(88,101,242,.1)}.dev-primary .dev-discord-link:hover{color:#fff;border-color:#7d88f8;background:rgba(88,101,242,.28)}.dev-discord-icon{color:#8993ff;font-size:17px;line-height:1}.dev-discord-link b{font-size:11px;font-weight:400}
.dev-management{grid-column:2;grid-row:2;align-items:start;padding-top:3px;border-top:1px solid rgba(217,154,62,.09)}.dev-management a{min-height:31px;padding:5px 13px;border:1px solid rgba(193,139,57,.2);border-radius:5px;color:#a9a095;background:rgba(9,9,9,.78);font-size:10px}.dev-management a:hover{color:#eed8b8;border-color:rgba(217,154,62,.45);background:rgba(59,39,17,.45)}.dev-management a.router-link-active{color:#edbd72;border-color:rgba(224,161,67,.68);background:linear-gradient(180deg,rgba(74,48,20,.62),rgba(33,22,12,.72));box-shadow:inset 0 0 16px rgba(211,143,43,.08)}
.dev-profile{grid-column:3;grid-row:1/3;display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:0 18px}.dev-profile-link{display:flex;align-items:center;gap:9px;padding:6px 8px;border:1px solid transparent;border-radius:7px;color:#f1e8dc;text-decoration:none}.dev-profile-link:hover{border-color:rgba(193,139,57,.35);background:rgba(70,45,18,.22)}.dev-profile-link .player-avatar{flex:none}.dev-profile span b,.dev-profile span small{display:block;text-align:right}.dev-profile button{padding:9px 11px;color:#decdb3;border:1px solid rgba(193,139,57,.43);border-radius:5px;background:rgba(16,12,8,.76);white-space:nowrap}.dev-profile button:hover{color:#ffe4b0;border-color:#d99a3e;background:#2a1b0d}
.dev-content{min-height:calc(100vh - 96px);background:transparent}.dev-content>section{width:min(1480px,calc(100% - 48px));margin-inline:auto}
@media(max-width:1350px){.dev-top{grid-template-columns:190px minmax(0,1fr)}.dev-profile{display:none}.dev-primary a{min-width:auto;padding-inline:10px}.dev-menus a{gap:5px}}
@media(max-width:1050px){.dev-top{position:relative;display:block;min-height:0}.dev-brand,.dev-profile{display:none}.dev-menus{display:grid;gap:0;padding:8px 10px;overflow-x:auto}.dev-menus nav{justify-content:flex-start;width:max-content}.dev-primary a{min-height:40px;border-radius:5px}.dev-management{padding-top:7px}.dev-content{min-height:100vh}}
</style>
