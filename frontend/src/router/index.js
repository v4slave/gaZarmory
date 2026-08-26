import { createRouter, createWebHistory } from 'vue-router'

const page = (name) => () => import(`../pages/${name}.vue`)
const DashboardPage = page('DashboardPage')
const RosterPage = page('RosterPage')
const GroupsPage = page('GroupsPage')
const PartySquadsPage = page('PartySquadsPage')
const ActivitiesPage = page('ActivitiesPage')
const ActivityPage = page('ActivityPage')
const TreasuryPage = page('TreasuryPage')
const PayoutsPage = page('PayoutsPage')
const PayoutPage = page('PayoutPage')
const AuctionsPage = page('AuctionsPage')
const PlayerPage = page('PlayerPage')
const AdminPage = page('AdminPage')
const AuctionPage = page('AuctionPage')
const AccessDeniedPage = page('AccessDeniedPage')
const NotFoundPage = page('NotFoundPage')
const RosterReadinessPage = page('RosterReadinessPage')
const AttendanceAnalyticsPage = page('AttendanceAnalyticsPage')
const FinancialReconciliationPage = page('FinancialReconciliationPage')
const AdminUsersPage = page('AdminUsersPage')
const AdminRequestsPage = page('AdminRequestsPage')
const AdminActivitiesPage = page('AdminActivitiesPage')
const AdminLootPage = page('AdminLootPage')
const AdminAuditPage = page('AdminAuditPage')
const AdminEconomyPage = page('AdminEconomyPage')
const AdminIntegrationsPage = page('AdminIntegrationsPage')
const MediaPage = page('MediaPage')

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', component: DashboardPage, meta: { title: 'Дашборд' } },
  { path: '/roster', component: RosterPage, meta: { title: 'Состав' } },
  { path: '/groups', component: GroupsPage, meta: { title: 'Конст-пати' } },
  { path: '/groups/:id/squads', component: PartySquadsPage, meta: { title: 'Пятёрки КП' } },
  { path: '/roster-readiness', component: RosterReadinessPage, meta: { title: 'Готовность состава', roles: ['guild_leader', 'micro_guild_leader', 'developer', 'party_leader'] } },
  { path: '/attendance-analytics', component: AttendanceAnalyticsPage, meta: { title: 'Аналитика посещаемости', roles: ['guild_leader', 'micro_guild_leader', 'developer', 'party_leader'] } },
  { path: '/financial-reconciliation', component: FinancialReconciliationPage, meta: { title: 'Финансовая сверка', roles: ['guild_leader', 'developer'] } },
  { path: '/players/:id', component: PlayerPage, meta: { title: 'Игрок' } },
  { path: '/activities', component: ActivitiesPage, meta: { title: 'Активности' } },
  { path: '/activities/:id', component: ActivityPage, meta: { title: 'Активность' } },
  { path: '/media', component: MediaPage, meta: { title: 'Контент' } },
  { path: '/treasury', component: TreasuryPage, meta: { title: 'Казна' } },
  { path: '/auctions', component: AuctionsPage, meta: { title: 'Аукционы' } },
  { path: '/auctions/:id', component: AuctionPage, meta: { title: 'Аукцион' } },
  { path: '/payouts', component: PayoutsPage, meta: { title: 'Нахрюк' } },
  { path: '/payouts/:id', component: PayoutPage, meta: { title: 'Нахрюк' } },
  { path: '/admin', component: AdminPage, meta: { title: 'Админка', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/users', component: AdminUsersPage, meta: { title: 'Пользователи и роли', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/requests', component: AdminRequestsPage, meta: { title: 'Заявки', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/activities', component: AdminActivitiesPage, meta: { title: 'Справочник активностей', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/loot', component: AdminLootPage, meta: { title: 'Справочник лута', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/audit', component: AdminAuditPage, meta: { title: 'Аудит', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/economy', component: AdminEconomyPage, meta: { title: 'Настройки экономики', roles: ['guild_leader', 'developer'] } },
  { path: '/admin/integrations', component: AdminIntegrationsPage, meta: { title: 'Discord и уведомления', roles: ['guild_leader', 'developer'] } },
  { path: '/forbidden', name: 'forbidden', component: AccessDeniedPage, meta: { title: 'Нет доступа' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundPage, meta: { title: 'Страница не найдена' } },
]

export default createRouter({ history: createWebHistory(), routes })
