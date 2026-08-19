<script setup>defineProps({ rows:{type:Number,default:5}, columns:{type:Number,default:4} })</script>
<template><div class="skeleton-list" aria-label="Загрузка"><div v-for="row in rows" :key="row" class="skeleton-row"><i v-for="column in columns" :key="column"></i></div></div></template>
