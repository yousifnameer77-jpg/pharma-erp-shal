"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import {
  api,
  ApiError,
  getCachedUser,
  getToken,
  setCachedUser,
  setScope,
  setToken,
} from "@/lib/api";
import type { User } from "@/lib/types";

interface LoginResponse {
  data: { user: User; token: string; token_type: string };
}

interface MeResponse {
  data: User;
}

interface AuthContextValue {
  user: User | null;
  /** true while the initial "am I already logged in" check is running. */
  loading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  /** True if the user holds `code` through ANY role assignment (any scope). */
  hasPermission: (code: string) => boolean;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(() => getCachedUser());
  const [loading, setLoading] = useState(() => !getCachedUser() && !!getToken());

  const loadMe = useCallback(async () => {
    const token = getToken();
    if (!token) {
      setUser(null);
      setCachedUser(null);
      return;
    }
    try {
      const res = await api.get<MeResponse>("/v1/auth/me");
      setUser(res.data);
      setCachedUser(res.data);
      setScope({ branch_id: res.data.branch?.id ?? null, warehouse_id: null });
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setToken(null);
        setCachedUser(null);
        setUser(null);
      } else {
        // Network drop or timeout: keep cached user session alive!
        const cached = getCachedUser();
        if (cached) {
          setUser(cached);
        }
      }
    }
  }, []);

  useEffect(() => {
    (async () => {
      await loadMe();
      setLoading(false);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = useCallback(async (username: string, password: string) => {
    const res = await api.post<LoginResponse>("/v1/auth/login", {
      username,
      password,
    });
    setToken(res.data.token);
    setUser(res.data.user);
    setCachedUser(res.data.user);
    setScope({
      branch_id: res.data.user.branch?.id ?? null,
      warehouse_id: null,
    });
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post("/v1/auth/logout");
    } catch {
      // best-effort
    }
    setToken(null);
    setCachedUser(null);
    setUser(null);
  }, []);

  const hasPermission = useCallback(
    (code: string) => {
      if (!user?.roles) return false;
      return user.roles.some((role) => role.permissions?.includes(code));
    },
    [user],
  );

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      login,
      logout,
      hasPermission,
      refreshUser: loadMe,
    }),
    [user, loading, login, logout, hasPermission, loadMe],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
