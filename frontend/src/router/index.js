import { createRouter, createWebHistory } from 'vue-router'
import DashboardPage from '../pages/DashboardPage.vue'
import RosterPage from '../pages/RosterPage.vue'
import GroupsPage from '../pages/GroupsPage.vue'
import ActivitiesPage from '../pages/ActivitiesPage.vue'
import ActivityPage from '../pages/ActivityPage.vue'
import TreasuryPage from '../pages/TreasuryPage.vue'
import PayoutsPage from '../pages/PayoutsPage.vue'
import PayoutPage from '../pages/PayoutPage.vue'
import AuctionsPage from '../pages/AuctionsPage.vue'
import PlayerPage from '../pages/PlayerPage.vue'
import AdminPage from '../pages/AdminPage.vue'
import AuctionPage from '../pages/AuctionPage.vue'

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', component: DashboardPage, meta: { title: 'Обзор' } },
  { path: '/roster', component: RosterPage, meta: { title: 'Состав' } },
  { path: '/groups', component: GroupsPage, meta: { title: 'Конст-пати' } },
  { path: '/players/:id', component: PlayerPage, meta: { title: 'Игрок' } },
  { path: '/activities', component: ActivitiesPage, meta: { title: 'Активности' } },
  { path: '/activities/:id', component: ActivityPage, meta: { title: 'Активность' } },
  { path: '/treasury', component: TreasuryPage, meta: { title: 'Казна' } },
  { path: '/auctions', component: AuctionsPage, meta: { title: 'Аукционы' } },
  { path: '/auctions/:id', component: AuctionPage, meta: { title: 'Аукцион' } },
  { path: '/payouts', component: PayoutsPage, meta: { title: 'Нахрюк' } },
  { path: '/payouts/:id', component: PayoutPage, meta: { title: 'Нахрюк' } },
  { path: '/admin', component: AdminPage, meta: { title: 'Администрирование', roles: ['guild_leader', 'developer'] } },
]

export default createRouter({ history: createWebHistory(), routes })
