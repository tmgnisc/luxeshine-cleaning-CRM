"use client";

import { apiGet, http } from "@/lib/api";
import type { User } from "@/lib/types";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { createContext, useContext, type ReactNode } from "react";

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<User>;
  register: (payload: RegisterPayload) => Promise<User>;
  logout: () => Promise<void>;
  refresh: () => Promise<unknown>;
}

interface RegisterPayload {
  name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({
  children,
  initialUser = null,
}: {
  children: ReactNode;
  initialUser?: User | null;
}) {
  const router = useRouter();
  const queryClient = useQueryClient();

  const { data, isLoading, refetch } = useQuery({
    queryKey: ["auth", "me"],
    queryFn: async () => {
      try {
        return await apiGet<User>("auth/me");
      } catch {
        return null;
      }
    },
    initialData: initialUser,
    staleTime: 60_000,
  });

  const user = data ?? null;

  const login = async (email: string, password: string): Promise<User> => {
    const res = await http.post("/session/login", { email, password }, { baseURL: "" });
    const loggedIn = res.data.data.user as User;
    queryClient.setQueryData(["auth", "me"], loggedIn);
    await refetch();
    return loggedIn;
  };

  const register = async (payload: RegisterPayload): Promise<User> => {
    const res = await http.post("/session/register", payload, { baseURL: "" });
    const registered = res.data.data.user as User;
    queryClient.setQueryData(["auth", "me"], registered);
    await refetch();
    return registered;
  };

  const logout = async (): Promise<void> => {
    await http.post("/session/logout", undefined, { baseURL: "" });
    queryClient.setQueryData(["auth", "me"], null);
    queryClient.clear();
    router.push("/login");
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        isAuthenticated: user !== null,
        login,
        register,
        logout,
        refresh: refetch,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return ctx;
}

export function homePathForRole(role?: string): string {
  switch (role) {
    case "super_admin":
    case "admin":
      return "/admin";
    case "cleaner":
      return "/cleaner";
    case "customer":
      return "/account";
    default:
      return "/";
  }
}
