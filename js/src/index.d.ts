export interface PortalSsoOptions {
  /** Portal untuk panggilan server-ke-server (boleh IP internal). */
  host: string;
  /** Portal untuk redirect browser (domain publik). Default: host. */
  hostDomain?: string;
  clientId?: string;
  clientSecret?: string;
  /** Harus sama dengan redirect URI OAuth client di portal, mis. https://app/callback. */
  callbackUrl: string;
  scopes?: string;
  timeoutMs?: number;
  logoutTimeoutMs?: number;
  fetch?: typeof fetch;
  logger?: (message: string, context: Record<string, unknown>) => void;
}

export interface PortalResponse {
  status: number;
  ok: boolean;
  json: Record<string, any> | null;
  text: string;
}

export interface PortalLogin {
  /** Data user dari portal, minimal berisi nip. */
  user: Record<string, any> & { nip: string | number };
  /** Access token portal. Jangan dicatat ke log. */
  token: string;
  flow: 'oauth' | 'portal-tile';
  nip: string;
}

export type SessionLike = Record<string, any>;

export interface PortalSso {
  isConfigured(): boolean;
  authorizeUrl(state: string): string;
  exchangeCode(code: string, state: string): Promise<PortalResponse>;
  user(token: string): Promise<PortalResponse>;
  verifyToken(token: string): Promise<PortalResponse>;
  checkUser(token: string): Promise<PortalResponse>;
  get(token: string, path: string): Promise<PortalResponse>;
  revoke(token: string): Promise<boolean>;
  logoutUrl(): string;
  begin(session: SessionLike): string;
  handleCallback(session: SessionLike, query: Record<string, unknown>): Promise<PortalLogin>;
  handleCallbackSession(query: Record<string, unknown>): Promise<PortalLogin>;
  logout(token: string | null | undefined): Promise<string | null>;
}

export declare class SsoError extends Error {
  context: Record<string, unknown>;
  constructor(message: string, context?: Record<string, unknown>, cause?: unknown);
}

export declare const MESSAGES: Readonly<{
  NOT_CONFIGURED: string;
  INVALID_STATE: string;
  MISSING_CODE: string;
  EXCHANGE_FAILED: string;
  USER_FAILED: string;
  MISSING_SESSION_TOKEN: string;
  INVALID_SESSION_TOKEN: string;
  MISSING_NIP: string;
  UNKNOWN_USER: string;
}>;

export declare const STATE_KEY: 'portal_sso_state';

export declare function createPortalSso(options: PortalSsoOptions): PortalSso;

export declare function configFromEnv(
  env?: Record<string, string | undefined>,
  overrides?: Partial<PortalSsoOptions>
): Partial<PortalSsoOptions>;
