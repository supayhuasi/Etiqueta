import * as SecureStore from 'expo-secure-store';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const SERVER_URL_KEY = 'nube_server_url';
const TOKEN_KEY = 'nube_token';
const USER_KEY = 'nube_usuario';

export type NubeUsuario = {
  id: number;
  usuario: string;
  nombre: string;
  rol?: string | null;
};

type AuthContextValue = {
  loading: boolean;
  serverUrl: string | null;
  token: string | null;
  usuario: NubeUsuario | null;
  signIn: (serverUrl: string, token: string, usuario: NubeUsuario) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [loading, setLoading] = useState(true);
  const [serverUrl, setServerUrl] = useState<string | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [usuario, setUsuario] = useState<NubeUsuario | null>(null);

  useEffect(() => {
    (async () => {
      const [storedUrl, storedToken, storedUser] = await Promise.all([
        SecureStore.getItemAsync(SERVER_URL_KEY),
        SecureStore.getItemAsync(TOKEN_KEY),
        SecureStore.getItemAsync(USER_KEY),
      ]);
      setServerUrl(storedUrl);
      setToken(storedToken);
      setUsuario(storedUser ? JSON.parse(storedUser) : null);
      setLoading(false);
    })();
  }, []);

  const signIn = useCallback(async (url: string, newToken: string, newUsuario: NubeUsuario) => {
    await Promise.all([
      SecureStore.setItemAsync(SERVER_URL_KEY, url),
      SecureStore.setItemAsync(TOKEN_KEY, newToken),
      SecureStore.setItemAsync(USER_KEY, JSON.stringify(newUsuario)),
    ]);
    setServerUrl(url);
    setToken(newToken);
    setUsuario(newUsuario);
  }, []);

  const signOut = useCallback(async () => {
    await Promise.all([
      SecureStore.deleteItemAsync(TOKEN_KEY),
      SecureStore.deleteItemAsync(USER_KEY),
    ]);
    setToken(null);
    setUsuario(null);
  }, []);

  const value = useMemo(
    () => ({ loading, serverUrl, token, usuario, signIn, signOut }),
    [loading, serverUrl, token, usuario, signIn, signOut]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth debe usarse dentro de AuthProvider');
  }
  return ctx;
}
