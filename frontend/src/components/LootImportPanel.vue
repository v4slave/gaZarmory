<script setup>
import { computed, ref } from 'vue'
import { useActivitiesStore } from '../stores/activities.js'
import { useConfirmationStore } from '../stores/confirmation.js'

const props=defineProps({activityId:{type:[String,Number],required:true}})
const activities=useActivitiesStore(); const file=ref(null); const busy=ref(false); const error=ref(''); const saved=ref({})
const confirmation=useConfirmationStore()
const draft=computed(()=>activities.lootImport)
const hasInvalid=computed(()=>draft.value?.rows?.some(row=>row.status!=='valid')??false)
function choose(event){file.value=event.target.files?.[0]??null;error.value=''}
async function upload(){
  if(!file.value)return
  if(file.value.size>10*1024*1024){error.value='Файл не должен превышать 10 МБ.';return}
  busy.value=true;error.value=''
  try{await activities.uploadLootTable(props.activityId,file.value)}
  catch(e){error.value=Object.values(e.response?.data?.errors??{}).flat()[0]??e.response?.data?.message??(e.code==='ECONNABORTED'?'Обработка заняла слишком много времени. Проверьте таблицу.':'Не удалось импортировать таблицу.')}
  finally{busy.value=false}
}
async function save(row){busy.value=true;error.value='';try{await activities.updateLootRow(draft.value.id,row.id,{item_name:row.item_name,quantity:Number(row.quantity),unit_price:Number(row.unit_price)});saved.value[row.id]=true;setTimeout(()=>delete saved.value[row.id],1200)}catch(e){error.value=e.response?.data?.message??'Проверьте значения строки.'}finally{busy.value=false}}
async function confirmImport(){if(!await confirmation.ask({title:'Подтвердить импорт?',message:'Предметы поступят в казну, а созданные транзакции больше нельзя будет изменить.',confirmLabel:'Подтвердить в казну'}))return;busy.value=true;error.value='';try{await activities.confirmLootImport(draft.value.id)}catch(e){error.value=e.response?.data?.message??'Не удалось подтвердить импорт.'}finally{busy.value=false}}
</script>

<template><div class="panel loot-import"><div class="panel-title"><div><h2>Импорт лута</h2><p class="muted">CSV/XLSX · черновик до подтверждения</p></div><span v-if="draft" :class="['import-status',draft.status]">{{ draft.status==='draft'?'Черновик':'Подтверждён' }}</span></div>
  <template v-if="!draft"><div class="upload-zone"><input id="loot-file" type="file" accept=".csv,.txt,.xls,.xlsx" @change="choose"><label for="loot-file"><strong>{{ file?.name??'Выберите таблицу лута' }}</strong><span>Колонки: item_name, quantity, unit_price · до 10 МБ</span></label><button class="primary" :disabled="!file||busy" @click="upload">{{ busy?'Загрузка…':'Создать черновик' }}</button></div></template>
  <template v-else><div class="import-summary"><span>Файл: <strong>{{ draft.original_filename }}</strong></span><span>Строк: <strong>{{ draft.rows.length }}</strong></span><span>Стоимость: <strong>{{ draft.rows.reduce((sum,row)=>sum+Number(row.quantity)*Number(row.unit_price),0).toLocaleString('ru-RU') }}</strong></span></div>
    <div class="table-wrap import-table"><table><thead><tr><th>Строка</th><th>Предмет</th><th>Количество</th><th>Цена за единицу</th><th>Сумма</th><th></th></tr></thead><tbody><tr v-for="row in draft.rows" :key="row.id" :class="{invalid:row.status!=='valid'}"><td>{{ row.row_number }}</td><td><input v-model.trim="row.item_name" :disabled="draft.status!=='draft'"></td><td><input v-model.number="row.quantity" type="number" min="1" :disabled="draft.status!=='draft'"></td><td><input v-model.number="row.unit_price" type="number" min="0" :disabled="draft.status!=='draft'"></td><td>{{ (Number(row.quantity)*Number(row.unit_price)).toLocaleString('ru-RU') }}</td><td><button v-if="draft.status==='draft'" class="row-save" :disabled="busy" @click="save(row)">{{ saved[row.id]?'Готово':'Сохранить' }}</button></td></tr></tbody></table></div>
    <div v-if="draft.status==='draft'" class="confirm-bar"><p><strong>Проверьте данные.</strong><br><span class="muted">После подтверждения предметы и цены попадут в казну.</span></p><button class="primary" :disabled="busy||hasInvalid" @click="confirmImport">Подтвердить в казну</button></div>
  </template><p v-if="error" class="notice error">{{ error }}</p>
</div></template>
