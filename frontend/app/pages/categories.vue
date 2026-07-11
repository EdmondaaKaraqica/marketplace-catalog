<script setup lang="ts">
const config = useRuntimeConfig()

const page = ref(1)
const limit = ref(20)

// useFetch re-runs automatically when `page` changes (query is reactive)
const { data, pending, error } = await useFetch('/api/categories', {
  baseURL: config.public.apiBase,
  query: { page, limit },
})

function nextPage() {
  if (data.value && page.value < data.value.pagination.pages) page.value++
}
function prevPage() {
  if (page.value > 1) page.value--
}
</script>

<template>
  <main style="font-family: sans-serif; padding: 2rem; max-width: 800px;">
    <h1>Categories</h1>

    <p v-if="pending">Loading…</p>
    <p v-else-if="error" style="color: red;">Failed to load categories.</p>

    <template v-else-if="data">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="text-align: left; border-bottom: 2px solid #ccc;">
            <th style="padding: 8px;">ID</th>
            <th style="padding: 8px;">Name</th>
            <th style="padding: 8px;">Number of products</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in data.items" :key="c.id" style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px;">{{ c.id }}</td>
            <td style="padding: 8px;">{{ c.name }}</td>
            <td style="padding: 8px;">{{ c.productCount }}</td>
          </tr>
        </tbody>
      </table>

      <div style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center;">
        <button :disabled="page <= 1" @click="prevPage">Prev</button>
        <span>Page {{ data.pagination.page }} of {{ data.pagination.pages }}</span>
        <button :disabled="page >= data.pagination.pages" @click="nextPage">Next</button>
      </div>
    </template>
  </main>
</template>