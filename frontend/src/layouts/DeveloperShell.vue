<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import NotificationCenter from '../components/NotificationCenter.vue'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'
import { useLocale } from '../i18n.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const route = useRoute()
const { t } = useLocale()
const menuRoot = ref(null)
const activeMenu = ref('')
let closeTimer
const navigationGroups = [
  { label: 'Основное', icon: 'users', items: [
    { to: '/roster', icon: 'users', label: 'Состав' }, { to: '/groups', icon: 'groups', label: 'Конст-пати' }, { to: '/activities', icon: 'sword', label: 'Активности' },
  ] },
  { label: 'Экономика', icon: 'treasury', items: [
    { to: '/treasury', icon: 'treasury', label: 'Казна' }, { to: '/auctions', icon: 'auction', label: 'Аукционы' }, { to: '/payouts', icon: 'payout', label: 'Нахрюк' },
  ] },
  { label: 'Доп. функционал', icon: 'play', items: [
    { href: 'https://archa.ge/', image: '/images/archa-calculator.png', label: 'Калькулятор' }, { href: 'https://discord.gg/gaz', image: '/images/discord-guild.png', label: 'Discord' }, { to: '/media', image: '/images/content-youtube.png', label: 'Контент' },
  ] },
]
const managementNavigation = [
  { to: '/roster-readiness', icon: 'readiness', label: 'Готовность состава', permission: 'canViewReadiness' },
  { to: '/attendance-analytics', icon: 'attendance', label: 'Посещаемость', permission: 'canViewReadiness' },
  { to: '/financial-reconciliation', icon: 'reconcile', label: 'Финансовая сверка', permission: 'canHandleTreasuryItems' },
  { to: '/admin', icon: 'settings', label: 'Административное пространство', permission: 'canAdmin' },
]
const availableManagementNavigation = computed(() => managementNavigation.filter(item => !item.permission || auth[item.permission]))
const groupActive = group => group.items.some(item => item.to && route.path.startsWith(item.to))
const activeNavigationGroup = computed(() => navigationGroups.find(group => group.label === activeMenu.value))
function openMenu(label) { window.clearTimeout(closeTimer); activeMenu.value = label }
function scheduleClose() { window.clearTimeout(closeTimer); closeTimer = window.setTimeout(closeMenus, 140) }
function closeMenus() { window.clearTimeout(closeTimer); activeMenu.value = '' }
function closeOutside(event) { if (!menuRoot.value?.contains(event.target)) closeMenus() }
function closeOnEscape(event) { if (event.key === 'Escape') closeMenus() }
onMounted(() => { auth.syncDiscordProfile(); document.addEventListener('click', closeOutside); document.addEventListener('keydown', closeOnEscape) })
onBeforeUnmount(() => { window.clearTimeout(closeTimer); document.removeEventListener('click', closeOutside); document.removeEventListener('keydown', closeOnEscape) })
</script>

<template>
  <div class="dev-shell">
    <header :class="['dev-top',{compact:!availableManagementNavigation.length}]">
      <RouterLink class="dev-brand" to="/dashboard">
        <img src="/hamster-armory.png" alt="Хомяк GAZ ARMORY">
        <span><b>GAZ ARMORY</b><small>ArcheAge guild</small></span>
      </RouterLink>
      <div class="dev-menus">
        <nav ref="menuRoot" class="dev-primary" aria-label="Primary navigation">
          <RouterLink to="/dashboard"><img class="dev-nav-image" src="/images/archeage-home.png" alt=""><span>{{ t('Главная') }}</span></RouterLink>
          <button v-for="group in navigationGroups" :key="group.label" type="button" :class="['dev-menu-trigger',{active:groupActive(group)||activeMenu===group.label}]" :aria-expanded="activeMenu===group.label" @mouseenter="openMenu(group.label)" @mouseleave="scheduleClose" @focus="openMenu(group.label)" @click="activeMenu===group.label?closeMenus():openMenu(group.label)"><AppIcon :name="group.icon" :size="18"/><span>{{ t(group.label) }}</span><b aria-hidden="true">⌄</b></button>
          <div v-if="activeNavigationGroup" class="dev-mega-menu" @mouseenter="openMenu(activeNavigationGroup.label)" @mouseleave="scheduleClose">
            <div class="dev-mega-menu-inner">
              <header><AppIcon :name="activeNavigationGroup.icon" :size="20"/><div><strong>{{ t(activeNavigationGroup.label) }}</strong><small>{{ t('Выберите раздел') }}</small></div></header>
              <div class="dev-mega-links"><template v-for="item in activeNavigationGroup.items" :key="item.to??item.href"><RouterLink v-if="item.to" :to="item.to" @click="closeMenus"><img v-if="item.image" :src="item.image" alt=""><AppIcon v-else :name="item.icon" :size="20"/><span>{{ t(item.label) }}</span><b aria-hidden="true">→</b></RouterLink><a v-else :href="item.href" target="_blank" rel="noopener noreferrer" @click="closeMenus"><img v-if="item.image" :src="item.image" alt=""><AppIcon v-else :name="item.icon" :size="20"/><span>{{ t(item.label) }}</span><b aria-hidden="true">↗</b></a></template></div>
            </div>
          </div>
        </nav>
        <nav v-if="availableManagementNavigation.length" class="dev-management" aria-label="Guild management">
          <RouterLink v-for="item in availableManagementNavigation" :key="item.to" :to="item.to">
            <AppIcon :name="item.icon" :size="15"/><span>{{ t(item.label) }}</span>
          </RouterLink>
        </nav>
      </div>
      <div class="dev-profile">
        <LanguageSwitcher/>
        <NotificationCenter/>
        <RouterLink class="dev-profile-link" :to="`/players/${auth.user.player.id}`" :title="t('Открыть свой профиль')">
          <PlayerAvatar :player="{ ...auth.user.player, user: auth.user }" size="small"/>
          <span><b>{{ auth.user?.discord_display_name || auth.user?.discord_username }}</b><small>{{ auth.user?.player?.nickname }}</small></span>
        </RouterLink>
        <button type="button" @click="auth.logout">{{ t('Выйти') }}</button>
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
.dev-primary .dev-discord-link{min-width:100px;padding:0 13px;color:#bdb4a8;border-color:transparent;border-bottom-color:rgba(217,154,62,.08);border-radius:0;background:transparent}.dev-primary .dev-discord-link:hover{color:#f3e3c9;border-color:rgba(217,154,62,.28);border-bottom-color:#c98c38;border-radius:6px 6px 0 0;background:linear-gradient(180deg,rgba(217,154,62,.03),rgba(217,154,62,.1))}.dev-discord-link img{width:18px;height:18px;border-radius:50%;object-fit:cover}
.dev-nav-image{width:18px;height:18px;flex:none;border-radius:50%;object-fit:cover;opacity:.72;filter:grayscale(1);transition:.16s ease}.dev-primary a:hover .dev-nav-image{opacity:.95}.dev-primary a.router-link-active .dev-nav-image{opacity:1;filter:sepia(1) saturate(1.45) brightness(1.35)}
.dev-external-links{display:flex;align-self:stretch;margin-left:6px;border-left:1px solid rgba(217,154,62,.24)}.dev-primary .dev-external-links>a{min-width:auto;padding-inline:11px}.dev-primary .dev-tool-link{color:#d8b77f}.dev-primary .dev-tool-link:hover{color:#ffe0a7;border-color:rgba(217,154,62,.28);border-bottom-color:#c98c38;border-radius:6px 6px 0 0;background:linear-gradient(180deg,rgba(217,154,62,.03),rgba(217,154,62,.1))}
.dev-menu-dropdown{position:relative;align-self:stretch}.dev-menu-dropdown summary{display:flex;align-items:center;justify-content:center;gap:8px;height:100%;min-width:128px;padding:0 14px;color:#bdb4a8;border:1px solid transparent;border-bottom-color:rgba(217,154,62,.08);cursor:pointer;list-style:none;font-size:12px;transition:.16s ease}.dev-menu-dropdown summary::-webkit-details-marker{display:none}.dev-menu-dropdown summary>b{color:#a8793d;font-size:12px;transition:transform .15s}.dev-menu-dropdown[open] summary>b{transform:rotate(180deg)}.dev-menu-dropdown:is(.active,[open]) summary{color:#efb85f;border-color:rgba(217,154,62,.42);background:linear-gradient(180deg,rgba(102,66,24,.24),rgba(46,30,13,.4))}.dev-menu-dropdown>div{position:absolute;z-index:220;top:calc(100% + 7px);left:0;display:grid;min-width:220px;padding:6px;border:1px solid rgba(229,167,74,.48);border-radius:8px;background:rgba(17,14,10,.985);box-shadow:0 18px 42px rgba(0,0,0,.55)}.dev-primary .dev-menu-dropdown>div>a{justify-content:flex-start;min-width:0;min-height:42px;padding:9px 11px;border:0;border-radius:5px;font-size:12px}.dev-primary .dev-menu-dropdown>div>a:hover,.dev-primary .dev-menu-dropdown>div>a.router-link-active{color:#f3c77f;background:rgba(103,66,25,.42);box-shadow:none}.dev-menu-dropdown>div>a>b{margin-left:auto}.dev-menu-dropdown>div>a>img{width:17px;height:17px;border-radius:50%}
.dev-management{grid-column:2;grid-row:2;align-items:start;padding-top:3px;border-top:1px solid rgba(217,154,62,.09)}.dev-management a{min-height:31px;padding:5px 13px;border:1px solid rgba(193,139,57,.2);border-radius:5px;color:#a9a095;background:rgba(9,9,9,.78);font-size:10px}.dev-management a:hover{color:#eed8b8;border-color:rgba(217,154,62,.45);background:rgba(59,39,17,.45)}.dev-management a.router-link-active{color:#edbd72;border-color:rgba(224,161,67,.68);background:linear-gradient(180deg,rgba(74,48,20,.62),rgba(33,22,12,.72));box-shadow:inset 0 0 16px rgba(211,143,43,.08)}
.dev-profile{grid-column:3;grid-row:1/3;display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:0 18px}.dev-profile-link{display:flex;align-items:center;gap:9px;padding:6px 8px;border:1px solid transparent;border-radius:7px;color:#f1e8dc;text-decoration:none}.dev-profile-link:hover{border-color:rgba(193,139,57,.35);background:rgba(70,45,18,.22)}.dev-profile-link .player-avatar{flex:none}.dev-profile span b,.dev-profile span small{display:block;text-align:right}.dev-profile button{padding:9px 11px;color:#decdb3;border:1px solid rgba(193,139,57,.43);border-radius:5px;background:rgba(16,12,8,.76);white-space:nowrap}.dev-profile button:hover{color:#ffe4b0;border-color:#d99a3e;background:#2a1b0d}
.dev-content{min-height:calc(100vh - 96px);background:transparent}.dev-content>section{width:min(1480px,calc(100% - 48px));margin-inline:auto}
.dev-top.compact{grid-template-rows:64px;min-height:64px}.dev-top.compact .dev-brand,.dev-top.compact .dev-profile{grid-row:1}.dev-top.compact .dev-primary{grid-row:1}.dev-top.compact+.dev-content{min-height:calc(100vh - 64px)}
@media(max-width:1350px){.dev-top{grid-template-columns:190px minmax(0,1fr)}.dev-profile{display:none}.dev-primary a{min-width:auto;padding-inline:10px}.dev-menus a{gap:5px}}
@media(max-width:1050px){.dev-top{position:relative;display:block;min-height:0}.dev-brand,.dev-profile{display:none}.dev-menus{display:grid;gap:0;padding:8px 10px;overflow-x:auto}.dev-menus nav{justify-content:flex-start;width:max-content}.dev-primary a{min-height:40px;border-radius:5px}.dev-management{padding-top:7px}.dev-content{min-height:100vh}}
@media(max-width:1350px){.dev-top{grid-template-columns:180px minmax(0,1fr) 160px}.dev-profile{display:flex;padding:0 10px}.dev-profile-link span{display:none}.dev-profile button{padding-inline:9px}.dev-primary a{font-size:12px}.dev-management a{font-size:11px}}
@media(max-width:1050px){.dev-top{position:sticky;display:grid;grid-template-columns:minmax(0,1fr) auto;grid-template-rows:52px auto;min-height:0}.dev-brand{grid-column:1;grid-row:1;display:flex;padding:0 12px}.dev-brand img{width:38px;height:38px}.dev-brand span{display:none}.dev-profile{grid-column:2;grid-row:1;display:flex;padding:0 10px}.dev-menus{grid-column:1/-1;grid-row:2;display:grid;gap:6px;padding:0 10px 8px;overflow-x:auto}.dev-primary,.dev-management{grid-column:1;grid-row:auto;justify-content:flex-start!important;width:max-content}.dev-primary a{min-height:38px;border-radius:5px}.dev-management{padding-top:0}.dev-content{min-height:calc(100vh - 120px)}}
@media(max-width:560px){.dev-profile-link{padding-inline:3px}.dev-profile button{padding:7px 8px;font-size:11px}.dev-menus a{gap:6px}.dev-primary a{font-size:11px}.dev-management a{font-size:10px}}
@media(max-width:560px){.dev-menu-dropdown summary{min-width:108px;padding-inline:10px}.dev-menu-dropdown>div{position:fixed;top:102px;right:10px;left:10px;min-width:0}.dev-top.compact .dev-menu-dropdown>div{top:102px}}
.dev-shell{color:#f7f0e6;background:linear-gradient(90deg,rgba(3,3,3,.38),rgba(5,4,3,.16) 48%,rgba(3,3,3,.36)),linear-gradient(rgba(0,0,0,.08),rgba(0,0,0,.3)),url('/images/gaz-armory-noir-background.png') center top/cover fixed}
.dev-top{border-bottom-color:rgba(229,167,74,.42);background:linear-gradient(180deg,rgba(13,12,10,.97),rgba(17,13,9,.95));box-shadow:0 10px 34px rgba(0,0,0,.34)}
.dev-menu-trigger{display:flex;align-items:center;justify-content:center;gap:8px;min-width:138px;padding:0 14px;color:#bdb4a8;border:1px solid transparent;border-bottom-color:rgba(217,154,62,.08);border-radius:0;background:transparent;font-size:12px;cursor:pointer;transition:.16s ease}.dev-menu-trigger>b{color:#a8793d;transition:transform .15s}.dev-menu-trigger:is(.active,:hover,:focus-visible){color:#efb85f;border-color:rgba(217,154,62,.42);background:linear-gradient(180deg,rgba(102,66,24,.24),rgba(46,30,13,.4));outline:0}.dev-menu-trigger[aria-expanded=true]>b{transform:rotate(180deg)}
.dev-mega-menu{position:fixed;z-index:215;top:56px;right:0;left:0;padding:10px 24px 16px;border-block:1px solid rgba(229,167,74,.42);background:linear-gradient(180deg,rgba(20,16,11,.99),rgba(12,11,9,.985));box-shadow:0 20px 45px rgba(0,0,0,.55)}.dev-top.compact .dev-mega-menu{top:64px}.dev-mega-menu-inner{display:grid;grid-template-columns:220px minmax(0,900px);gap:22px;align-items:center;width:min(1160px,100%);margin:auto}.dev-mega-menu header{display:flex;align-items:center;gap:12px;padding:10px 18px;border-right:1px solid rgba(229,167,74,.24)}.dev-mega-menu header div{display:grid;gap:3px}.dev-mega-menu header strong{color:#f1c276;font-size:14px}.dev-mega-menu header small{color:#938777;font-size:9px}.dev-mega-menu nav{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0}.dev-primary .dev-mega-menu nav>a{justify-content:flex-start;min-width:0;min-height:56px;padding:10px 13px;border:1px solid rgba(217,154,62,.2);border-radius:7px;color:#d6cdc1;background:rgba(255,255,255,.025);font-size:12px}.dev-primary .dev-mega-menu nav>a:hover,.dev-primary .dev-mega-menu nav>a.router-link-active{color:#f5c980;border-color:rgba(229,167,74,.55);background:rgba(103,66,25,.34);box-shadow:none}.dev-mega-menu nav>a>b{margin-left:auto}.dev-mega-menu nav>a>img{width:20px;height:20px;border-radius:50%}
.dev-mega-links{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.dev-primary .dev-mega-links>a{display:flex;align-items:center;justify-content:flex-start;gap:8px;min-width:0;min-height:56px;padding:10px 13px;border:1px solid rgba(217,154,62,.2);border-radius:7px;color:#d6cdc1;background:rgba(255,255,255,.025);font-size:12px;text-decoration:none}.dev-primary .dev-mega-links>a:hover,.dev-primary .dev-mega-links>a.router-link-active{color:#f5c980;border-color:rgba(229,167,74,.55);background:rgba(103,66,25,.34);box-shadow:none}.dev-mega-links>a>b{margin-left:auto}.dev-mega-links>a>img{width:24px;height:24px;flex:none;border-radius:4px;object-fit:contain}
@media(max-width:700px){.dev-menu-trigger{min-width:116px;padding-inline:9px}.dev-mega-menu,.dev-top.compact .dev-mega-menu{top:102px;padding-inline:10px}.dev-mega-menu-inner{grid-template-columns:1fr;gap:5px}.dev-mega-menu header{padding:7px 4px;border-right:0}.dev-mega-menu nav{grid-template-columns:1fr}.dev-primary .dev-mega-menu nav>a{min-height:44px}}
@media(max-width:700px){.dev-mega-links{grid-template-columns:1fr}.dev-primary .dev-mega-links>a{min-height:44px}}
</style>
