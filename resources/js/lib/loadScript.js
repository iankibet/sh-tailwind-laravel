const scripts = new Map();

export const loadScript = (src) => {
    if (scripts.has(src)) {
        return scripts.get(src);
    }

    const promise = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);

        if (existing) {
            existing.addEventListener('load', resolve, { once: true });
            existing.addEventListener('error', reject, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', reject, { once: true });
        document.head.appendChild(script);
    });

    const retryablePromise = promise.catch((error) => {
        scripts.delete(src);
        throw error;
    });

    scripts.set(src, retryablePromise);
    return retryablePromise;
};
