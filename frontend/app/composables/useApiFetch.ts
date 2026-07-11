export function useApiFetch<T>(url: string, opts: any = {}) {
  const config = useRuntimeConfig()
  const token = useCookie<string | null>('auth_token')

  return useFetch<T>(url, {
    baseURL: config.public.apiBase,
    headers: token.value ? { Authorization: `Bearer ${token.value}` } : {},
    ...opts,
  })
}