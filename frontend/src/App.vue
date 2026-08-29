<script setup>
import { computed, ref } from 'vue'
import AppShell from './layouts/AppShell.vue'
import DeveloperShell from './layouts/DeveloperShell.vue'
import ToastStack from './components/ToastStack.vue'
import ConfirmationDialog from './components/ConfirmationDialog.vue'
import InputDialog from './components/InputDialog.vue'
import { useAuthStore } from './stores/auth.js'

const auth = useAuthStore()
const interfaceMode = ref(window.localStorage.getItem('gaz-armory-interface') ?? 'developer')
const showDeveloperShell = computed(() => auth.authenticated && auth.isDeveloper && interfaceMode.value === 'developer')
function switchInterface(mode) {
  interfaceMode.value = mode
  window.localStorage.setItem('gaz-armory-interface', mode)
}
</script>

<template><DeveloperShell v-if="showDeveloperShell" @switch-interface="switchInterface('main')"/><AppShell v-else @switch-interface="switchInterface('developer')"/><ToastStack/><ConfirmationDialog/><InputDialog/></template>
