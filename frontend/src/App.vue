<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth.js'
import AppShell from './layouts/AppShell.vue'
import ToastStack from './components/ToastStack.vue'

const auth = useAuthStore()
onMounted(() => auth.fetchMe())
</script>

<template><AppShell /><ToastStack /></template>
