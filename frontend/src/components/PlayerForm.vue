<script setup>
import { reactive, ref } from 'vue'
import { useGuildStore } from '../stores/guild.js'

const emit = defineEmits(['saved', 'cancel'])
const guild = useGuildStore()
const saving = ref(false)
const error = ref('')
const form = reactive({ nickname: '', class: 'melee', group_id: null, is_active: true })
const classes = [['melee', 'Милик'], ['archer', 'Лук'], ['mage', 'Маг'], ['healer', 'Хил'], ['bard', 'Бард'], ['tank', 'Танк']]

async function submit() {
  saving.value = true; error.value = ''
  try { await guild.createPlayer(form); emit('saved') }
  catch (e) { error.value = e.response?.data?.message ?? 'Не удалось сохранить игрока.' }
  finally { saving.value = false }
}
</script>

<template>
  <form class="form-card" @submit.prevent="submit">
    <h2>Новый игрок</h2>
    <label>Никнейм<input v-model.trim="form.nickname" required maxlength="18" pattern="[A-Za-zА-Яа-яЁё]+" title="Только русские или латинские буквы, без пробелов, цифр и специальных символов"></label>
    <label>Класс<select v-model="form.class"><option v-for="item in classes" :key="item[0]" :value="item[0]">{{ item[1] }}</option></select></label>
    <label>Конст-пати<select v-model="form.group_id"><option :value="null">Одиночки</option><option v-for="group in guild.groups" :key="group.id" :value="group.id">{{ group.name }}</option></select></label>
    <p v-if="error" class="error">{{ error }}</p>
    <div class="form-actions"><button type="button" @click="$emit('cancel')">Отмена</button><button class="primary" :disabled="saving">{{ saving ? 'Сохранение…' : 'Создать' }}</button></div>
  </form>
</template>
