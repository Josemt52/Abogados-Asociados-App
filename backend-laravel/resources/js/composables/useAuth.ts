import { computed, readonly, ref } from 'vue';

export interface UserRole {
  id: number;
  nombre: string;
}

export interface User {
  id: number | string;
  nombre: string;
  username: string;
  email?: string | null;
  rol: UserRole;
}

const AUTH_TOKEN_KEY = 'auth_token';
const USER_KEY = 'user';

const user = ref<User | null>(null);
const token = ref<string | null>(null);
const isLoading = ref(true);
let initialized = false;
let logoutListenerRegistered = false;

const canUseStorage = () => typeof window !== 'undefined' && 'localStorage' in window;

const clearStoredSession = () => {
  if (!canUseStorage()) {
    return;
  }

  try {
    window.localStorage.removeItem(AUTH_TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);
  } catch (error) {
    console.warn('[Auth] No se pudo limpiar la sesión guardada.', error);
  }
};

const resetSession = () => {
  clearStoredSession();
  user.value = null;
  token.value = null;
  isLoading.value = false;
};

const isStoredUser = (value: unknown): value is User => {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const candidate = value as Partial<User>;
  return typeof candidate.username === 'string' && candidate.username.trim().length > 0;
};

const registerLogoutListener = () => {
  if (typeof window === 'undefined' || logoutListenerRegistered) {
    return;
  }

  window.addEventListener('app:logout', resetSession);
  logoutListenerRegistered = true;
};

export const initializeAuth = () => {
  if (initialized) {
    return;
  }

  initialized = true;
  registerLogoutListener();

  if (!canUseStorage()) {
    isLoading.value = false;
    return;
  }

  try {
    const storedToken = window.localStorage.getItem(AUTH_TOKEN_KEY);
    const storedUser = window.localStorage.getItem(USER_KEY);

    if (!storedToken || !storedUser) {
      resetSession();
      return;
    }

    const parsedUser: unknown = JSON.parse(storedUser);
    if (!isStoredUser(parsedUser)) {
      resetSession();
      return;
    }

    token.value = storedToken;
    user.value = parsedUser;
  } catch (error) {
    console.warn('[Auth] La sesión guardada no es válida.', error);
    resetSession();
    return;
  }

  isLoading.value = false;
};

export const login = (authenticatedUser: User, accessToken?: string | null) => {
  if (!accessToken) {
    console.warn('[Auth] Se intentó iniciar sesión sin un token JWT.');
    resetSession();
    return;
  }

  try {
    if (canUseStorage()) {
      window.localStorage.setItem(AUTH_TOKEN_KEY, accessToken);
      window.localStorage.setItem(USER_KEY, JSON.stringify(authenticatedUser));
    }
  } catch (error) {
    console.warn('[Auth] No se pudo guardar la sesión.', error);
  }

  user.value = authenticatedUser;
  token.value = accessToken;
  isLoading.value = false;
};

export const logout = () => {
  resetSession();
};

const isAuthenticated = computed(() => Boolean(user.value && token.value));
const isAdmin = computed(() => user.value?.rol?.nombre?.toUpperCase() === 'ADMIN');

export const useAuth = () => {
  initializeAuth();

  return {
    user: readonly(user),
    token: readonly(token),
    isAuthenticated: readonly(isAuthenticated),
    isLoading: readonly(isLoading),
    isAdmin: readonly(isAdmin),
    login,
    logout,
  };
};

