<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import AppModal from './AppModal.vue'
import { useActivitiesStore } from '../stores/activities.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const props=defineProps({open:Boolean,activityId:{type:[String,Number],required:true}})
const emit=defineEmits(['close'])
const activities=useActivitiesStore(); const notifications=useNotificationsStore()
const file=ref(null); const preview=ref(''); const result=ref(null); const selected=ref([]); const busy=ref(false); const error=ref('')
const matches=computed(()=>result.value?.matches??[])
function clearPreview(){if(preview.value)URL.revokeObjectURL(preview.value);preview.value=''}
function choose(event){clearPreview();file.value=event.target.files?.[0]??null;result.value=null;selected.value=[];error.value='';if(file.value)preview.value=URL.createObjectURL(file.value)}
async function scan(){if(!file.value)return;busy.value=true;error.value='';try{result.value=await activities.scanParticipants(props.activityId,file.value);selected.value=result.value.matches.map(item=>item.player_id)}catch(e){error.value=apiErrorMessage(e,'Не удалось распознать участников.')}finally{busy.value=false}}
async function add(){if(!selected.value.length)return;busy.value=true;error.value='';try{const count=selected.value.length;await activities.addPlayers(props.activityId,selected.value);notifications.success(`Добавлено участников со скриншота: ${count}.`);close()}catch(e){error.value=apiErrorMessage(e,'Не удалось добавить участников.')}finally{busy.value=false}}
function close(){if(busy.value)return;clearPreview();file.value=null;result.value=null;selected.value=[];error.value='';emit('close')}
onBeforeUnmount(clearPreview)
</script>

<template><AppModal :open="open" title="Добавить участников со скриншота" :dismissible="!busy" @close="close"><section class="form-card participant-scan-card"><p class="eyebrow">OCR · РАСПОЗНАВАНИЕ СОСТАВА</p><h2>Добавить участников со скриншота</h2><p class="muted">Загрузите скрин рейд-фреймов. Проверьте найденные совпадения перед добавлением.</p><label class="participant-scan-upload"><input type="file" accept="image/png,image/jpeg,image/webp" @change="choose"><span>{{ file?.name??'Выбрать скриншот' }}</span><small>PNG, JPG или WEBP · до 12 МБ</small></label><img v-if="preview" class="participant-scan-preview" :src="preview" alt="Загруженный скриншот"><button v-if="file&&!result" type="button" class="primary" :disabled="busy" @click="scan">{{ busy?'Распознаём…':'Распознать ники' }}</button><template v-if="result"><div class="participant-scan-summary"><strong>Найдено совпадений: {{ matches.length }}</strong><button type="button" @click="selected=selected.length?[]:matches.map(item=>item.player_id)">{{ selected.length?'Снять выбор':'Выбрать всех' }}</button></div><div v-if="matches.length" class="participant-scan-results"><label v-for="item in matches" :key="item.player_id" :class="[`class-${item.class}`,{selected:selected.includes(item.player_id)}]"><input v-model="selected" type="checkbox" :value="item.player_id"><span>{{ item.nickname }}</span><small>{{ item.confidence }}%</small></label></div><p v-else class="empty">Совпадений с активным составом не найдено. Попробуйте более чёткий скриншот.</p></template><p v-if="error" class="notice error">{{ error }}</p><div class="form-actions"><button type="button" :disabled="busy" @click="close">Отмена</button><button v-if="result" type="button" class="primary" :disabled="busy||!selected.length" @click="add">Добавить ({{ selected.length }})</button></div></section></AppModal></template>
