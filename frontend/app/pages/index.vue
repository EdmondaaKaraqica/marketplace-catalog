<script setup lang="ts">
const config = useRuntimeConfig()
const { data: health } = await useFetch(`${config.public.apiBase}/api/health`)
</script>

<template>
  <main style="font-family: sans-serif; padding: 2rem;">
    <h1>Frontend is running</h1>
    <p>Backend health: <strong>{{ health?.status ?? 'unavailable' }}</strong></p>
  </main>
</template>
