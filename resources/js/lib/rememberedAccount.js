const key = 'pius_videos_last_email';
const passkeyKey = 'pius_videos_passkey_emails';

export const getRememberedEmail = () => localStorage.getItem(key) || '';

export const rememberEmail = (email) => {
    if (email) {
        localStorage.setItem(key, email);
    }
};

const getPasskeyEmails = () => {
    try {
        return JSON.parse(localStorage.getItem(passkeyKey)) || [];
    } catch {
        return [];
    }
};

export const hasRememberedPasskey = (email = getRememberedEmail()) => Boolean(email)
    && getPasskeyEmails().includes(email.toLowerCase());

export const syncPasskeyAvailability = (email, available) => {
    if (!email) return;

    const normalizedEmail = email.toLowerCase();
    const emails = new Set(getPasskeyEmails());

    if (available) {
        emails.add(normalizedEmail);
    } else {
        emails.delete(normalizedEmail);
    }

    localStorage.setItem(passkeyKey, JSON.stringify([...emails]));
};
