<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api.js'
import { useLocale } from '../i18n.js'
import { formatDateTime } from '../utils/format.js'
import AppIcon from './AppIcon.vue'

const { t } = useLocale()
const root = ref(null), open = ref(false), loading = ref(false), error = ref(''), items = ref([]), unread = ref(0)
let loadedAt = 0
function icon(type){return({link_request:'users',auction_started:'auction',auction_finished:'auction',auction_outbid:'warning',payout_calculated:'payout',insufficient_gold:'treasury',activity_upcoming:'sword'})[type]??'info'}
async function load(force=false){
  if(loading.value||(!force&&Date.now()-loadedAt<15000))return
  loading.value=true;error.value=''
  try{const data=(await api.get('/api/notifications')).data;items.value=data.items;unread.value=data.unread_count;loadedAt=Date.now()}
  catch{error.value='Не удалось загрузить уведомления.'}
  finally{loading.value=false}
}
function toggle(){open.value=!open.value;if(open.value)load()}
async function mark(item){if(!item.read_at){await api.post(`/api/notifications/${item.id}/read`);item.read_at=new Date().toISOString();unread.value=Math.max(0,unread.value-1)}open.value=false}
async function markAll(){await api.post('/api/notifications/read-all');items.value.forEach(item=>item.read_at??=new Date().toISOString());unread.value=0}
function onDocument(event){if(open.value&&!root.value?.contains(event.target))open.value=false}
function onKey(event){if(event.key==='Escape')open.value=false}
onMounted(()=>{document.addEventListener('pointerdown',onDocument);document.addEventListener('keydown',onKey);load()})
onBeforeUnmount(()=>{document.removeEventListener('pointerdown',onDocument);document.removeEventListener('keydown',onKey)})
</script>

<template><div ref="root" class="notification-center"><button class="notification-bell" type="button" :aria-label="t('Уведомления')" :aria-expanded="open" @click="toggle"><AppIcon name="bell"/><b v-if="unread">{{ unread>99?'99+':unread }}</b></button><div v-if="open" class="notification-popover"><header><div><h2>{{ t('Уведомления') }}</h2><small>{{ unread }} {{ t('непрочитанных') }}</small></div><button v-if="unread" @click="markAll">{{ t('Прочитать все') }}</button></header><p v-if="loading" class="notification-state" role="status">{{ t('Загрузка…') }}</p><div v-else-if="error" class="notification-state notice error" role="alert">{{ t(error) }} <button @click="load(true)">{{ t('Повторить') }}</button></div><div v-else-if="items.length" class="notification-list"><component :is="item.data.url?'RouterLink':'div'" v-for="item in items" :key="item.id" :to="item.data.url||undefined" :class="{unread:!item.read_at}" @click="mark(item)"><span><AppIcon :name="icon(item.type)"/></span><div><strong>{{ t(item.data.title) }}</strong><p>{{ t(item.data.message) }}</p><small>{{ formatDateTime(item.created_at) }}</small></div></component></div><p v-else class="empty">{{ t('Уведомлений пока нет.') }}</p></div></div></template>
