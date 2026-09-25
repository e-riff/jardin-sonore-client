export const PORTAL_SESSION_COOKIE_NAME = "__Host-portal_session";
export const PORTAL_IMPERSONATION_COOKIE_NAME = "__Host-portal_impersonation";
export const PORTAL_SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 7;
export const PORTAL_IMPERSONATION_MAX_AGE_SECONDS = 30 * 60;

export interface PortalCookieWriter { set(name: string, value: string, options: {httpOnly: true; maxAge?: number; path: "/"; sameSite: "lax"; secure: true}): void; }

const options = (): {httpOnly: true; maxAge: number; path: "/"; sameSite: "lax"; secure: true} => ({httpOnly: true, maxAge: PORTAL_SESSION_MAX_AGE_SECONDS, path: "/", sameSite: "lax", secure: true});

export const writePortalSession = (writer: PortalCookieWriter, token: string, maxAge = PORTAL_SESSION_MAX_AGE_SECONDS): void => writer.set(PORTAL_SESSION_COOKIE_NAME, token, {...options(), maxAge});
export const expirePortalSession = (writer: PortalCookieWriter): void => writer.set(PORTAL_SESSION_COOKIE_NAME, "", {...options(), maxAge: 0});
export const writePortalImpersonation = (writer: PortalCookieWriter): void => writer.set(PORTAL_IMPERSONATION_COOKIE_NAME, "1", {...options(), maxAge: PORTAL_IMPERSONATION_MAX_AGE_SECONDS});
export const expirePortalImpersonation = (writer: PortalCookieWriter): void => writer.set(PORTAL_IMPERSONATION_COOKIE_NAME, "", {...options(), maxAge: 0});
