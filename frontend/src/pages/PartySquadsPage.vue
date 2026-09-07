<script setup>
import { computed,onMounted,ref } from 'vue'
import { useRoute,useRouter } from 'vue-router'
import { api } from '../api.js'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import { formatInteger } from '../utils/format.js'
const route=useRoute(),router=useRouter(),data=ref({group:null,players:[],squads:[]}),loading=ref(true),error=ref('')
const labels={melee:'Милик',archer:'Лучник',mage:'Маг',healer:'Хил',bard:'Бард',tank:'Танк'}
const byId=computed(()=>new Map(data.value.players.map(p=>[p.id,p])))
const players=s=>s.player_ids.map(id=>byId.value.get(id)).filter(Boolean)
const unassigned=computed(()=>data.value.players.filter(p=>!data.value.squads.some(s=>s.player_ids.includes(p.id))))
async function load(){try{data.value=(await api.get(`/api/groups/${route.params.id}/squads`)).data}catch(e){if(e.response?.status===403)router.replace('/forbidden');else error.value=e.response?.data?.message??'Не удалось загрузить состав.'}finally{loading.value=false}}
onMounted(load)
</script>
<template><section class="party-squads-page"><div class="page-heading"><div><p class="eyebrow">КОНСТ-ПАТИ · СОСТАВ</p><h1>{{ data.group?.name??'Пятёрки' }}</h1></div></div><p v-if="error" class="notice error">{{ error }}</p><div v-if="loading" class="panel">Загружаем состав…</div><template v-else><div class="squads-grid"><article v-for="squad in data.squads" :key="squad.id" class="panel squad-card"><header><h2>{{ squad.name }}</h2><b>{{ squad.player_ids.length }} / 5</b></header><div class="squad-list"><div v-for="player in players(squad)" :key="player.id" class="squad-player"><PlayerAvatar :player="player" size="small"/><RouterLink :to="`/players/${player.id}`"><strong>{{ player.nickname }} <img v-if="player.has_ship" class="ship-mark" src="/images/profile-assets/ship-badge.png" alt="Есть корабль" title="Есть корабль"></strong><small>ГС {{ formatInteger(player.gear_score||0) }}</small></RouterLink><span :class="['class-tag',`class-${player.class}`]">{{ labels[player.class] }}</span></div></div></article></div><section class="panel unassigned-panel"><h2>Без группы</h2><div v-for="player in unassigned" :key="player.id" class="squad-player"><PlayerAvatar :player="player" size="small"/><RouterLink :to="`/players/${player.id}`"><strong>{{ player.nickname }} <img v-if="player.has_ship" class="ship-mark" src="/images/profile-assets/ship-badge.png" alt="Есть корабль" title="Есть корабль"></strong></RouterLink></div></section></template></section></template>

