import { shRepo } from '@iankibetsh/sh-core';
import { loadScript } from './loadScript';

const siteKey = import.meta.env.VITE_RECAPTCHA_KEY;
const apiHost = import.meta.env.VITE_RECAPTCHA_API_HOST || 'www.recaptcha.net';

export const withRecaptcha = async (data, action) => {
    if (!siteKey) {
        return data;
    }

    try {
        await loadScript(`https://${apiHost}/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`);
        await new Promise((resolve) => window.grecaptcha.ready(resolve));

        return {
            ...data,
            recaptcha_token: await window.grecaptcha.execute(siteKey, { action }),
        };
    } catch {
        shRepo.showToast('reCAPTCHA could not load. Please try again.', 'error');
        return false;
    }
};
