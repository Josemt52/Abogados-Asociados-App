import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';

export interface User {
  id: string;
  nombre: string;
  username: string;
  rol: { id: number; nombre: string };
}

interface AuthContextValue {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (user: User, token?: string | null) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const t = localStorage.getItem('auth_token');
    const userStr = localStorage.getItem('user');

    let validUser: User | null = null;
    if (t && userStr) {
      try {
        validUser = JSON.parse(userStr);
      } catch (e) {
        validUser = null;
      }
    }

    if (!t || !userStr || !validUser || !validUser.username) {
      console.debug('[Auth] no valid stored session found, clearing storage');
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      setUser(null);
      setToken(null);
      setIsAuthenticated(false);
      setIsLoading(false);
    } else {
      console.debug('[Auth] restoring session from storage', { username: validUser.username });
      setUser(validUser);
      setToken(t);
      setIsAuthenticated(true);
      setIsLoading(false);
    }
  }, []);

  // Listen to global logout events (dispatched by API interceptor on 401)
  useEffect(() => {
    const handler = () => {
      console.debug('[Auth] received app:logout event');
      logout();
    };
    window.addEventListener('app:logout', handler);
    return () => window.removeEventListener('app:logout', handler);
  }, []);

  const login = (u: User, t?: string | null) => {
    if (t) {
      try {
        localStorage.setItem('auth_token', t);
      } catch (e) {
        console.warn('[Auth] could not persist token to localStorage', e);
      }
    } else {
      console.warn('[Auth] login() called without token');
    }

    try {
      localStorage.setItem('user', JSON.stringify(u));
    } catch (e) {
      console.warn('[Auth] could not persist user to localStorage', e);
    }

    console.debug('[Auth] login()', { username: u.username, hasToken: !!t });
    setUser(u);
    setToken(t ?? null);
    setIsAuthenticated(true);
    setIsLoading(false);
  };

  const logout = () => {
    console.debug('[Auth] logout()');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    setUser(null);
    setToken(null);
    setIsAuthenticated(false);
    setIsLoading(false);
  };

  return (
    <AuthContext.Provider value={{ user, token, isAuthenticated, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextValue => {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return ctx;
};
