<script setup lang="ts">
const token = useCookie<string | null>('auth_token')

function logout() {
  token.value = null
  navigateTo('/login')
}
</script>

<template>
  <div style="font-family: sans-serif;">
    <nav
      v-if="token"
      style="display: flex; gap: 1.5rem; padding: 1rem 2rem; border-bottom: 1px solid #ddd;"
    >
      <NuxtLink to="/categories">Categories</NuxtLink>
      <NuxtLink to="/products">Products</NuxtLink>
      <button style="margin-left: auto;" @click="logout">Logout</button>
    </nav>

    <slot />
  </div>
</template>
