export const PORTAL_SESSION_COOKIE_NAME = "__Host-portal_session";
export const PORTAL_SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 7;

export interface PortalCookieOptions {
    httpOnly: true;
    maxAge?: number;
    path: "/";
    sameSite: "lax";
    secure: true;
}

export interface PortalCookieWriter {
    set(name: string, value: string, options: PortalCookieOptions): void;
}

export interface PortalSessionOptions {
    impersonating?: boolean;
}

const portalCookieOptions = ({impersonating = false}: PortalSessionOptions = {}): PortalCookieOptions => ({
    httpOnly: true,
    ...(impersonating ? {} : {maxAge: PORTAL_SESSION_MAX_AGE_SECONDS}),
    path: "/",
    sameSite: "lax",
    secure: true,
});

export const writePortalSession = (
    cookieWriter: PortalCookieWriter,
    token: string,
    options: PortalSessionOptions = {},
): void => {
    cookieWriter.set(PORTAL_SESSION_COOKIE_NAME, token, portalCookieOptions(options));
};

export const expirePortalSession = (cookieWriter: PortalCookieWriter): void => {
    cookieWriter.set(PORTAL_SESSION_COOKIE_NAME, "", {
        ...portalCookieOptions(),
        maxAge: 0,
    });
};
