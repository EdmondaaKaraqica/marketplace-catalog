<script setup lang="ts">
// Landing route: send logged-in users to the app, everyone else to login.
const token = useCookie<string | null>('auth_token')
await navigateTo(token.value ? '/categories' : '/login')
</script>

<template>
  <div />
</template>
