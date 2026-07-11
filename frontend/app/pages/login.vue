<script setup lang="ts">
const config = useRuntimeConfig()
const token = useCookie<string | null>('auth_token')

const step = ref<'email' | 'code'>('email')
const email = ref('')
const code = ref('')
const error = ref('')

async function requestCode() {
  error.value = ''
  await $fetch('/api/login/request', {
    baseURL: config.public.apiBase,
    method: 'POST',
    body: { email: email.value },
  })
  step.value = 'code'
}

async function verifyCode() {
  error.value = ''
  try {
    const res = await $fetch<{ token: string }>('/api/login/verify', {
      baseURL: config.public.apiBase,
      method: 'POST',
      body: { email: email.value, code: code.value },
    })
    console.log('token val:', res.token)
    token.value = res.token          // store token in cookie
    await navigateTo('/categories')  // go to the app
  } catch {
    error.value = 'Invalid email or code.'
  }
}
</script>

<template>
  <main style="font-family: sans-serif; padding: 2rem; max-width: 360px; margin: 0 auto;">
    <h1>Login</h1>

    <form v-if="step === 'email'" @submit.prevent="requestCode">
      <p>Enter your email to receive a login code.</p>
      <input v-model="email" type="email" placeholder="you@example.com" required style="width: 100%; padding: 8px;">
      <button type="submit" style="margin-top: 8px;">Send code</button>
    </form>

    <form v-else @submit.prevent="verifyCode">
      <p>Enter the 6-digit code sent to {{ email }}.</p>
      <input v-model="code" placeholder="123456" required style="width: 100%; padding: 8px;">
      <button type="submit" style="margin-top: 8px;">Log in</button>
    </form>

    <p v-if="error" style="color: red;">{{ error }}</p>
  </main>
</template>