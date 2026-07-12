export default defineNuxtRouteMiddleware((to) => {
  const token = useCookie<string | null>('auth_token')

  // allow the login page through; everything else needs a token
  if (to.path !== '/login' && !token.value) {
    return navigateTo('/login')
  }

  // if already logged in, keep them out of /login
  if (to.path === '/login' && token.value) {
    return navigateTo('/categories')
  }
})