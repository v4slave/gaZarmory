<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from './layouts/AppShell.vue'
import DeveloperDashboardPage from './pages/DeveloperDashboardPage.vue'
import ToastStack from './components/ToastStack.vue'
import ConfirmationDialog from './components/ConfirmationDialog.vue'
import InputDialog from './components/InputDialog.vue'
import { useAuthStore } from './stores/auth.js'

const auth = useAuthStore()
const route = useRoute()
const showDeveloperDashboard = computed(() => route.path === '/dashboard' && auth.isDeveloper)
</script>

<template><DeveloperDashboardPage v-if="showDeveloperDashboard"/><AppShell v-else/><ToastStack/><ConfirmationDialog/><InputDialog/></template>
