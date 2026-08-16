<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api.js'
import { useActivitiesStore } from '../stores/activities.js'
import { apiErrorMessage, useNotificationsStore } from '../stores/notifications.js'

const props = defineProps({ activityId: { type: Number, required: true } })
const activities = useActivitiesStore()
const notifications = useNotificationsStore()
const catalog = ref([])
const selectedId = ref('')
const price = ref(0)
const quantity = ref(1)
const busy = ref(false)
const error = ref('')
const open = ref(false)
const search = ref('')
const selected = computed(() => catalog.value.find(item => item.id === Number(selectedId.value)))
const filtered = computed(() => catalog.value.filter(item => item.name.toLowerCase().includes(search.value.toLowerCase())))

onMounted(async () => { try { catalog.value = (await api.get('/api/loot-catalog')).data } catch { error.value = 'Не удалось загрузить справочник лута.' } })
function choose(item) { selectedId.value = item.id; open.value = false; search.value = '' }
async function submit() { busy.value = true; error.value = ''; try { await activities.addLoot(props.activityId, { loot_catalog_item_id: selectedId.value, unit_price: price.value, quantity: quantity.value }); notifications.success('Лут добавлен в активность.'); selectedId.value = ''; price.value = 0; quantity.value = 1 } catch (e) { error.value = apiErrorMessage(e, 'Не удалось добавить лут.'); notifications.error(error.value) } finally { busy.value = false } }
</script>

<template><div class="panel manual-loot" :class="{'picker-open':open}"><div class="panel-title"><div><h2>Добавить лут</h2><p class="muted">Выберите предмет из справочника и укажите цену</p></div><RouterLink class="catalog-link" to="/admin">Справочник лута</RouterLink></div><form class="catalog-loot-form" @submit.prevent="submit"><label>Предмет<div class="loot-combobox"><button type="button" class="loot-combobox-trigger" :aria-expanded="open" @click="open=!open"><span class="loot-choice"><img v-if="selected?.icon_url" :src="selected.icon_url" :alt="selected.name"><span v-else class="loot-choice-empty">?</span><strong>{{ selected?.name ?? 'Выберите предмет' }}</strong></span><span>⌄</span></button><div v-if="open" class="loot-options"><input v-model.trim="search" autofocus placeholder="Поиск предмета…"><button v-for="item in filtered" :key="item.id" type="button" @click="choose(item)"><img v-if="item.icon_url" :src="item.icon_url" :alt="item.name"><span v-else class="loot-choice-empty">?</span><span>{{ item.name }}</span></button><p v-if="!filtered.length" class="empty">Предметы не найдены.</p></div></div></label><label>Цена за единицу, золото<input v-model.number="price" required type="number" min="0" step="1"></label><label>Количество<input v-model.number="quantity" required type="number" min="1" step="1"></label><button class="primary" :disabled="busy||!selectedId">{{ busy?'Добавление…':'Добавить лут' }}</button></form><p v-if="!catalog.length&&!error" class="empty">Справочник пуст. Сначала добавьте предметы в разделе администрирования.</p><p v-if="error" class="notice error">{{ error }}</p></div></template>
