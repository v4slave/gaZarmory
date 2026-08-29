<script setup>
import { useAuthStore } from '../stores/auth.js'
import DashboardPage from './DashboardPage.vue'
import DeveloperDashboardPage from './DeveloperDashboardPage.vue'

const auth = useAuthStore()
</script>

<template><DeveloperDashboardPage v-if="auth.isDeveloper"/><DashboardPage v-else/></template>
