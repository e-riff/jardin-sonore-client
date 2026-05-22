import {createChallenge, pbkdf2, verifySolution, type Challenge, type Payload} from "altcha/lib";

export const ALTCHA_ALGORITHM = "PBKDF2/SHA-256";
export const ALTCHA_COST = 30_000;
export const ALTCHA_EXPIRES_IN_MS = 1000 * 60 * 10;

const usedChallenges = new Map<string, number>();

const getSecret = (): string => process.env.ALTCHA_HMAC_SECRET ?? "dev-altcha-secret-change-me";
const toTimestampMs = (value: number): number => value < 1_000_000_000_000 ? value * 1000 : value;

const cleanupUsedChallenges = (): void => {
    const now = Date.now();

    for (const [nonce, expiresAt] of usedChallenges.entries()) {
        if (expiresAt <= now) {
            usedChallenges.delete(nonce);
        }
    }
};

export const createAltchaChallenge = async (): Promise<Challenge> => createChallenge({
    algorithm: ALTCHA_ALGORITHM,
    cost: ALTCHA_COST,
    deriveKey: pbkdf2.deriveKey,
    expiresAt: new Date(Date.now() + ALTCHA_EXPIRES_IN_MS),
    hmacSignatureSecret: getSecret(),
});

export const verifyAltchaPayload = async (payload: string | null): Promise<boolean> => {
    if (!payload) {
        return false;
    }

    try {
        cleanupUsedChallenges();

        const parsed = JSON.parse(Buffer.from(payload, "base64").toString("utf8")) as Payload;
        const nonce = parsed.challenge.parameters.nonce;

        if (usedChallenges.has(nonce)) {
            return false;
        }

        const result = await verifySolution({
            challenge: parsed.challenge,
            deriveKey: pbkdf2.deriveKey,
            hmacSignatureSecret: getSecret(),
            solution: parsed.solution,
        });

        if (result.verified) {
            const expiresAt = parsed.challenge.parameters.expiresAt
                ? toTimestampMs(parsed.challenge.parameters.expiresAt)
                : Date.now() + ALTCHA_EXPIRES_IN_MS;
            usedChallenges.set(nonce, expiresAt);
        }

        return result.verified;
    } catch {
        return false;
    }
};
