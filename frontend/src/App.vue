<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from './layouts/AppShell.vue'
import DeveloperShell from './layouts/DeveloperShell.vue'
import AdminShell from './layouts/AdminShell.vue'
import ToastStack from './components/ToastStack.vue'
import ConfirmationDialog from './components/ConfirmationDialog.vue'
import InputDialog from './components/InputDialog.vue'
import { useAuthStore } from './stores/auth.js'

const auth = useAuthStore()
const route = useRoute()
const showNewInterface = computed(() => auth.authenticated && Boolean(auth.user?.player))
const showAdminWorkspace = computed(() => showNewInterface.value && route.path.startsWith('/admin'))
</script>

<template><AdminShell v-if="showAdminWorkspace"/><DeveloperShell v-else-if="showNewInterface"/><AppShell v-else/><ToastStack/><ConfirmationDialog/><InputDialog/></template>
