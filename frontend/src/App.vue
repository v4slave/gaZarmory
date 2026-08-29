<script setup>
import { computed } from 'vue'
import AppShell from './layouts/AppShell.vue'
import DeveloperShell from './layouts/DeveloperShell.vue'
import ToastStack from './components/ToastStack.vue'
import ConfirmationDialog from './components/ConfirmationDialog.vue'
import InputDialog from './components/InputDialog.vue'
import { useAuthStore } from './stores/auth.js'

const auth = useAuthStore()
const showNewInterface = computed(() => auth.authenticated && Boolean(auth.user?.player))
</script>

<template><DeveloperShell v-if="showNewInterface"/><AppShell v-else/><ToastStack/><ConfirmationDialog/><InputDialog/></template>
