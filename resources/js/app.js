import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const executedInlineScripts = new Set();
let toastTimer = null;

function cleanUrl(url) {
    return String(url || window.location.href).split('#')[0];
}

function showAjaxToast(message, type = 'success') {
    const toast = document.querySelector('[data-ajax-toast]');

    if (! toast || ! message) {
        return;
    }

    const text = message.replace(/\s+/g, ' ').trim();
    const shortText = text.length > 190 ? text.slice(0, 187) + '...' : text;

    toast.textContent = shortText;
    toast.className = 'fixed right-4 top-4 z-50 max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg '
        + (type === 'error' ? 'bg-rose-600' : 'bg-emerald-600');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.add('hidden');
    }, 3500);
}

function scriptHash(value) {
    let hash = 0;

    for (let index = 0; index < value.length; index += 1) {
        hash = ((hash << 5) - hash + value.charCodeAt(index)) | 0;
    }

    return String(hash);
}

function executeRegionScripts(region) {
    region.querySelectorAll('script').forEach((oldScript) => {
        const src = oldScript.getAttribute('src');
        const content = oldScript.textContent || '';
        const key = src ? 'src:' + src : 'inline:' + scriptHash(content);

        if (executedInlineScripts.has(key)) {
            oldScript.remove();
            return;
        }

        if (! src && ! content.trim()) {
            oldScript.remove();
            return;
        }

        executedInlineScripts.add(key);

        const script = document.createElement('script');

        Array.from(oldScript.attributes).forEach((attribute) => {
            script.setAttribute(attribute.name, attribute.value);
        });

        script.textContent = content;
        oldScript.replaceWith(script);
    });
}

function applyAjaxHtml(html, url, options = {}) {
    const { push = true, preserveScroll = false, scrollY = null } = options;
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const region = document.querySelector('[data-ajax-region]');
    const nextRegion = doc.querySelector('[data-ajax-region]');

    if (! region || ! nextRegion) {
        window.location.href = url;
        return;
    }

    if (window.Alpine?.destroyTree) {
        try {
            window.Alpine.destroyTree(region);
        } catch (error) {
            // La región puede no tener componentes Alpine activos.
        }
    }

    region.innerHTML = nextRegion.innerHTML;
    executeRegionScripts(region);

    if (window.Alpine?.initTree) {
        window.Alpine.initTree(region);
    }

    if (doc.title) {
        document.title = doc.title;
    }

    const samePage = cleanUrl(url) === cleanUrl(window.location.href);

    if (push) {
        const state = { ajax: true, scrollY: window.scrollY };

        if (samePage) {
            window.history.replaceState(state, '', url);
        } else {
            window.history.pushState(state, '', url);
        }
    }

    if (! preserveScroll) {
        window.scrollTo(0, scrollY ?? 0);
    }

    const status = nextRegion.querySelector('[data-flash-status]');
    const error = nextRegion.querySelector('[data-flash-error]');

    if (status) {
        showAjaxToast(status.textContent, 'success');
    } else if (error) {
        showAjaxToast(error.textContent, 'error');
    }
}

async function softNavigate(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Accept': 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            window.location.href = url;
            return;
        }

        const html = await response.text();
        applyAjaxHtml(html, response.url, options);
    } catch (error) {
        window.location.href = url;
    }
}

async function submitFormWithoutReload(form, submitter) {
    const submitButton = submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    const method = (form.getAttribute('method') || 'GET').toUpperCase();

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.classList.add('opacity-60', 'pointer-events-none');
    }

    try {
        let body;

        try {
            body = new FormData(form, submitter || undefined);
        } catch (error) {
            body = new FormData(form);
        }

        const response = await fetch(form.action, {
            method,
            body,
            headers: {
                'Accept': 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            redirect: 'follow',
        });

        if (response.status === 419) {
            showAjaxToast('La sesión expiró. Recarga la página para continuar.', 'error');
            return;
        }

        if (! response.ok) {
            showAjaxToast('No se pudo guardar. Inténtalo de nuevo.', 'error');
            return;
        }

        const contentType = response.headers.get('content-type') || '';

        if (! contentType.includes('text/html')) {
            await softNavigate(window.location.href, { push: false, preserveScroll: true });
            return;
        }

        const html = await response.text();
        const samePage = cleanUrl(response.url) === cleanUrl(window.location.href);
        const forceScrollTop = form.hasAttribute('data-ajax-scroll-top');

        applyAjaxHtml(html, response.url, {
            push: true,
            preserveScroll: samePage && ! forceScrollTop,
        });
    } catch (error) {
        showAjaxToast('No se pudo guardar. Revisa tu conexión.', 'error');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-60', 'pointer-events-none');
        }
    }
}

document.querySelectorAll('script').forEach((script) => {
    if (script.src) {
        executedInlineScripts.add('src:' + script.src);
        return;
    }

    if (script.textContent.trim()) {
        executedInlineScripts.add('inline:' + scriptHash(script.textContent));
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement)) {
        return;
    }

    if (! document.body.hasAttribute('data-ajax-saves')) {
        return;
    }

    if (event.defaultPrevented || event.returnValue === false) {
        return;
    }

    if (form.hasAttribute('data-no-ajax')) {
        return;
    }

    const method = (form.getAttribute('method') || 'GET').toUpperCase();

    if (method === 'GET' || method === 'DIALOG') {
        return;
    }

    const target = form.getAttribute('target');

    if (target && target !== '_self') {
        return;
    }

    event.preventDefault();
    submitFormWithoutReload(form, event.submitter);
});

window.addEventListener('popstate', (event) => {
    if (! document.body.hasAttribute('data-ajax-saves')) {
        return;
    }

    softNavigate(window.location.href, {
        push: false,
        preserveScroll: false,
        scrollY: event.state?.scrollY ?? 0,
    });
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const scriptMeta = document.querySelector('meta[name="service-worker"]');
        const scopeMeta = document.querySelector('meta[name="service-worker-scope"]');
        const scriptUrl = scriptMeta?.content || '/sw.js';
        const scope = scopeMeta?.content;

        const options = scope ? { scope } : undefined;

        navigator.serviceWorker.register(scriptUrl, options).catch(() => {
            // Sin service worker la app sigue funcionando en línea.
        });
    });
}
