<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api.js'

const loading=ref(true),error=ref(''),report=ref({summary:{},checks:[]})
const statusLabels={ok:'Расхождений нет',warning:'Требует внимания',critical:'Критическое расхождение'}
const statusIcons={ok:'✓',warning:'!',critical:'×'}
const metricsLabels={calculated_balance:'Баланс по движениям',current_balance:'Текущий баланс',transactions:'Транзакций',items:'Предметов',movements:'Движений',payouts:'Нахрюков',linked_earnings:'Начислений',active_auctions:'Активных аукционов',reserved_items:'Предметов в резерве'}
const checkedAt=computed(()=>report.value.checked_at?new Date(report.value.checked_at).toLocaleString('ru-RU'):'—')
async function load(){loading.value=true;error.value='';try{report.value=(await api.get('/api/financial-reconciliation')).data}catch(e){error.value=e.response?.data?.message??'Не удалось выполнить финансовую сверку.'}finally{loading.value=false}}
function entityLink(issue){const map={auction:'/auctions/',payout:'/payouts/',activity:'/activities/'};return map[issue.entityType]?`${map[issue.entityType]}${issue.entityId}`:null}
onMounted(load)
</script>

<template><section>
  <div class="page-heading reconciliation-heading"><div><p class="eyebrow">GAZ ARMORY · ФИНАНСОВЫЙ КОНТРОЛЬ</p><h1>Финансовая сверка</h1></div><button class="primary" :disabled="loading" @click="load">{{ loading?'Проверяем…':'Запустить сверку' }}</button></div>
  <p v-if="error" class="notice error">{{ error }}</p><div v-if="loading" class="panel empty">Проверяем журналы движений, остатки и связанные операции…</div>
  <template v-else>
    <div :class="['reconciliation-overview',`status-${report.status}`]"><span class="reconciliation-overview-icon">{{ statusIcons[report.status] }}</span><div><small>Результат сверки</small><h2>{{ statusLabels[report.status] }}</h2><p>Проверено: {{ checkedAt }}</p></div><div class="reconciliation-totals"><span><b>{{ report.summary.passed }}</b><small>пройдено</small></span><span><b>{{ report.summary.issues }}</b><small>замечаний</small></span><span><b>{{ report.summary.critical }}</b><small>критических</small></span></div></div>
    <div class="reconciliation-grid"><article v-for="check in report.checks" :key="check.key" :class="['panel','reconciliation-check',`status-${check.status}`]"><div class="reconciliation-check-heading"><span>{{ statusIcons[check.status] }}</span><div><h2>{{ check.title }}</h2><p>{{ check.description }}</p></div><b>{{ check.issues_count }}</b></div><div v-if="Object.keys(check.metrics).length" class="reconciliation-metrics"><span v-for="(value,key) in check.metrics" :key="key"><small>{{ metricsLabels[key]??key }}</small><b>{{ Number(value).toLocaleString('ru-RU') }}</b></span></div><div v-if="check.issues.length" class="reconciliation-issues"><article v-for="(issue,index) in check.issues" :key="`${issue.entityType}-${issue.entityId}-${index}`" :class="issue.severity"><span>{{ issue.severity==='critical'?'×':'!' }}</span><div><strong>{{ issue.title }}</strong><small>{{ issue.details }}</small></div><RouterLink v-if="entityLink(issue)" :to="entityLink(issue)">Открыть →</RouterLink></article></div><p v-else class="reconciliation-ok">✓ Проверка пройдена, расхождений не найдено</p></article></div>
  </template>
</section></template>
