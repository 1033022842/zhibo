const STORAGE_KEYS = {
  ACCESS_TOKEN: 'live_access_token',
  REFRESH_TOKEN: 'live_refresh_token',
  USER_INFO: 'live_user_info',
}

export interface UserInfo {
  id: number
  user_no: string
  nickname: string
  avatar: string
  level: number
}

let cachedAccessToken: string | null = null

export function getAccessToken(): string | null {
  if (cachedAccessToken) return cachedAccessToken
  cachedAccessToken = localStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN)
  return cachedAccessToken
}

export function setTokens(accessToken: string, refreshToken: string): void {
  cachedAccessToken = accessToken
  localStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN, accessToken)
  localStorage.setItem(STORAGE_KEYS.REFRESH_TOKEN, refreshToken)
}

export function clearTokens(): void {
  cachedAccessToken = null
  localStorage.removeItem(STORAGE_KEYS.ACCESS_TOKEN)
  localStorage.removeItem(STORAGE_KEYS.REFRESH_TOKEN)
  localStorage.removeItem(STORAGE_KEYS.USER_INFO)
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(STORAGE_KEYS.REFRESH_TOKEN)
}

export function getStoredUserInfo(): UserInfo | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEYS.USER_INFO)
    if (!raw) return null
    return JSON.parse(raw) as UserInfo
  } catch {
    return null
  }
}

export function setStoredUserInfo(user: UserInfo): void {
  localStorage.setItem(STORAGE_KEYS.USER_INFO, JSON.stringify(user))
}

export function isLoggedIn(): boolean {
  return getAccessToken() !== null
}
