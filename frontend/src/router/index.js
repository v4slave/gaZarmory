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
import AccessDeniedPage from '../pages/AccessDeniedPage.vue'
import NotFoundPage from '../pages/NotFoundPage.vue'
import RosterReadinessPage from '../pages/RosterReadinessPage.vue'
import AttendanceAnalyticsPage from '../pages/AttendanceAnalyticsPage.vue'
import FinancialReconciliationPage from '../pages/FinancialReconciliationPage.vue'
import AdminUsersPage from '../pages/AdminUsersPage.vue'
import AdminRequestsPage from '../pages/AdminRequestsPage.vue'
import AdminActivitiesPage from '../pages/AdminActivitiesPage.vue'
import AdminLootPage from '../pages/AdminLootPage.vue'
import AdminAuditPage from '../pages/AdminAuditPage.vue'
import AdminEconomyPage from '../pages/AdminEconomyPage.vue'
import AdminIntegrationsPage from '../pages/AdminIntegrationsPage.vue'

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', component: DashboardPage, meta: { title: 'Дашборд' } },
  { path: '/roster', component: RosterPage, meta: { title: 'Состав' } },
  { path: '/groups', component: GroupsPage, meta: { title: 'Конст-пати' } },
  { path: '/roster-readiness', component: RosterReadinessPage, meta: { title: 'Готовность состава', roles: ['guild_leader', 'micro_guild_leader', 'developer', 'party_leader'] } },
  { path: '/attendance-analytics', component: AttendanceAnalyticsPage, meta: { title: 'Аналитика посещаемости', roles: ['guild_leader', 'micro_guild_leader', 'developer', 'party_leader'] } },
  { path: '/financial-reconciliation', component: FinancialReconciliationPage, meta: { title: 'Финансовая сверка', roles: ['guild_leader', 'developer'] } },
  { path: '/players/:id', component: PlayerPage, meta: { title: 'Игрок' } },
  { path: '/activities', component: ActivitiesPage, meta: { title: 'Активности' } },
  { path: '/activities/:id', component: ActivityPage, meta: { title: 'Активность' } },
  { path: '/treasury', component: TreasuryPage, meta: { title: 'Казна' } },
  { path: '/auctions', component: AuctionsPage, meta: { title: 'Аукционы' } },
  { path: '/auctions/:id', component: AuctionPage, meta: { title: 'Аукцион' } },
  { path: '/payouts', component: PayoutsPage, meta: { title: 'Нахрюк' } },
  { path: '/payouts/:id', component: PayoutPage, meta: { title: 'Нахрюк' } },
  { path: '/admin', component: AdminPage, meta: { title: 'Админка', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/users', component: AdminUsersPage, meta: { title: 'Пользователи и роли', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/requests', component: AdminRequestsPage, meta: { title: 'Заявки', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/activities', component: AdminActivitiesPage, meta: { title: 'Справочник активностей', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/loot', component: AdminLootPage, meta: { title: 'Справочник лута', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/audit', component: AdminAuditPage, meta: { title: 'Аудит', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/economy', component: AdminEconomyPage, meta: { title: 'Настройки экономики', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/admin/integrations', component: AdminIntegrationsPage, meta: { title: 'Discord и уведомления', roles: ['guild_leader', 'micro_guild_leader', 'developer'] } },
  { path: '/forbidden', name: 'forbidden', component: AccessDeniedPage, meta: { title: 'Нет доступа' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundPage, meta: { title: 'Страница не найдена' } },
]

export default createRouter({ history: createWebHistory(), routes })
