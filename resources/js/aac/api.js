const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function request(method, url, body) {
    const opts = {
        method,
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': csrf,
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    if (body instanceof FormData) {
        opts.body = body;
    } else if (body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }

    const res = await fetch(url, opts);
    const data = res.status === 204 ? null : await res.json().catch(() => null);

    if (!res.ok) {
        const err = new Error(
            res.status === 419
                ? 'Your session expired. Reload the page and try again.'
                : data?.message || `Request failed (${res.status}).`
        );
        err.status = res.status;
        err.errors = data?.errors;
        throw err;
    }
    return data;
}

export const api = {
    post: (url, body) => request('POST', url, body),
    put: (url, body) => request('PUT', url, body),
    del: (url) => request('DELETE', url),
    form: (url, formData) => request('POST', url, formData),
};
