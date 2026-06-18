import { API_URL } from '@/constants/config';
import * as SecureStore from 'expo-secure-store';

const ACCESS_KEY = 'jm_access_token';
const REFRESH_KEY = 'jm_refresh_token';

export type ApiUser = {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  display_name: string;
};

export type ApiBusiness = {
  id: number;
  name: string;
  timezone: string;
};

export type AuthSession = {
  access_token: string;
  refresh_token: string;
  expires_at: string;
  user: ApiUser;
  business: ApiBusiness;
  workspace_role: string;
  module_flags: Record<string, boolean>;
  label_job: string;
};

type ApiEnvelope<T> = {
  ok: boolean;
  data?: T;
  error?: string;
  errors?: Record<string, string>;
};

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string>;

  constructor(message: string, status: number, errors?: Record<string, string>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

async function readTokens(): Promise<{ access: string | null; refresh: string | null }> {
  const [access, refresh] = await Promise.all([
    SecureStore.getItemAsync(ACCESS_KEY),
    SecureStore.getItemAsync(REFRESH_KEY),
  ]);
  return { access, refresh };
}

export async function saveSession(session: AuthSession): Promise<void> {
  await Promise.all([
    SecureStore.setItemAsync(ACCESS_KEY, session.access_token),
    SecureStore.setItemAsync(REFRESH_KEY, session.refresh_token),
  ]);
}

export async function clearSession(): Promise<void> {
  await Promise.all([
    SecureStore.deleteItemAsync(ACCESS_KEY),
    SecureStore.deleteItemAsync(REFRESH_KEY),
  ]);
}

async function refreshAccessToken(refreshToken: string): Promise<string | null> {
  const response = await fetch(`${API_URL}/api/v1/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ refresh_token: refreshToken }),
  });
  const payload = (await response.json()) as ApiEnvelope<AuthSession>;
  if (!response.ok || !payload.ok || !payload.data) {
    await clearSession();
    return null;
  }
  await saveSession(payload.data);
  return payload.data.access_token;
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
  auth = true,
): Promise<T> {
  const headers = new Headers(options.headers ?? {});
  headers.set('Accept', 'application/json');
  if (!headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }

  if (auth) {
    const { access, refresh } = await readTokens();
    let token = access;
    if (!token && refresh) {
      token = await refreshAccessToken(refresh);
    }
    if (!token) {
      throw new ApiError('Unauthorized', 401);
    }
    headers.set('Authorization', `Bearer ${token}`);
  }

  let response = await fetch(`${API_URL}${path}`, { ...options, headers });

  if (response.status === 401 && auth) {
    const { refresh } = await readTokens();
    if (refresh) {
      const newToken = await refreshAccessToken(refresh);
      if (newToken) {
        headers.set('Authorization', `Bearer ${newToken}`);
        response = await fetch(`${API_URL}${path}`, { ...options, headers });
      }
    }
  }

  const payload = (await response.json()) as ApiEnvelope<T>;
  if (!response.ok || !payload.ok) {
    throw new ApiError(payload.error ?? 'Request failed', response.status, payload.errors);
  }

  return payload.data as T;
}

export async function login(
  email: string,
  password: string,
  deviceName?: string,
): Promise<AuthSession> {
  const data = await apiRequest<AuthSession>(
    '/api/v1/auth/login',
    {
      method: 'POST',
      body: JSON.stringify({ email, password, device_name: deviceName ?? 'JunkMetrix Mobile' }),
    },
    false,
  );
  await saveSession(data);
  return data;
}

export async function logout(): Promise<void> {
  const { refresh } = await readTokens();
  try {
    await apiRequest('/api/v1/auth/logout', {
      method: 'POST',
      body: JSON.stringify({ refresh_token: refresh ?? '' }),
    });
  } catch {
    // ignore network errors on logout
  }
  await clearSession();
}

export async function fetchMe(): Promise<AuthSession> {
  const me = await apiRequest<{
    user: ApiUser;
    business: ApiBusiness;
    workspace_role: string;
    module_flags: Record<string, boolean>;
    label_job: string;
  }>('/api/v1/auth/me');

  const { access, refresh } = await readTokens();
  return {
    access_token: access ?? '',
    refresh_token: refresh ?? '',
    expires_at: '',
    user: me.user,
    business: me.business,
    workspace_role: me.workspace_role,
    module_flags: me.module_flags,
    label_job: me.label_job,
  };
}
