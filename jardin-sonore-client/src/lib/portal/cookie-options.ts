export const PORTAL_SESSION_COOKIE_NAME = "__Host-portal_session";
export const PORTAL_SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 7;

export interface PortalCookieWriter { set(name: string, value: string, options: {httpOnly: true; maxAge?: number; path: "/"; sameSite: "lax"; secure: true}): void; }

const options = (): {httpOnly: true; maxAge: number; path: "/"; sameSite: "lax"; secure: true} => ({httpOnly: true, maxAge: PORTAL_SESSION_MAX_AGE_SECONDS, path: "/", sameSite: "lax", secure: true});

export const writePortalSession = (writer: PortalCookieWriter, token: string): void => writer.set(PORTAL_SESSION_COOKIE_NAME, token, options());
export const expirePortalSession = (writer: PortalCookieWriter): void => writer.set(PORTAL_SESSION_COOKIE_NAME, "", {...options(), maxAge: 0});
