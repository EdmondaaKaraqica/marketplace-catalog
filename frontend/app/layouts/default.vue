<template>
  <div style="font-family: sans-serif;">
    <nav style="display: flex; gap: 1.5rem; padding: 1rem 2rem; border-bottom: 1px solid #ddd;">
      <NuxtLink to="/categories">Categories</NuxtLink>
      <NuxtLink to="/products">Products</NuxtLink>
    </nav>
    <slot />
  </div>
</template>