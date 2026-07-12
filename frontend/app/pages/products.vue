<script setup lang="ts">
const config = useRuntimeConfig()

const page = ref(1)
const minPrice = ref('')
const maxPrice = ref('')
const inStock = ref(false)

// when a filter changes, jump back to page 1
watch([minPrice, maxPrice, inStock], () => { page.value = 1 })

// only send filters that are actually set (don't send empty ones)
const query = computed(() => {
  const q: Record<string, any> = { page: page.value, limit: 20 }
  if (minPrice.value !== '') q.minPrice = minPrice.value
  if (maxPrice.value !== '') q.maxPrice = maxPrice.value
  if (inStock.value) q.inStock = 1
  return q
})

const { data, pending, error } = await useApiFetch('/api/products', {
  query, // reactive: refetches whenever query changes
})

function nextPage() { if (data.value && page.value < data.value.pagination.pages) page.value++ }
function prevPage() { if (page.value > 1) page.value-- }
</script>

<template>
  <main style="font-family: sans-serif; padding: 2rem; max-width: 900px;">
    <h1>Products</h1>

    <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
      <label>Min price <input v-model="minPrice" type="number" min="0" style="width: 100px;"></label>
      <label>Max price <input v-model="maxPrice" type="number" min="0" style="width: 100px;"></label>
      <label><input v-model="inStock" type="checkbox"> Hide out of stock</label>
    </div>

    <p v-if="pending">Loading…</p>
    <p v-else-if="error" style="color: red;">Failed to load products.</p>

    <template v-else-if="data">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="text-align: left; border-bottom: 2px solid #ccc;">
            <th style="padding: 8px;">ID</th>
            <th style="padding: 8px;">Name</th>
            <th style="padding: 8px;">Category</th>
            <th style="padding: 8px;">SKU</th>
            <th style="padding: 8px;">Price</th>
            <th style="padding: 8px;">Stock</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in data.items" :key="p.id" style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px;">{{ p.id }}</td>
            <td style="padding: 8px;">{{ p.title }}</td>
            <td style="padding: 8px;">{{ p.category }}</td>
            <td style="padding: 8px;">{{ p.sku }}</td>
            <td style="padding: 8px;">{{ p.price }}</td>
            <td style="padding: 8px;">{{ p.stock }}</td>
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