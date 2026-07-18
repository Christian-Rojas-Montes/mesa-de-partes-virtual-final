import './bootstrap';
import 'bootstrap';

const serviceWorkerAllowed = window.isSecureContext
    || ['localhost', '127.0.0.1'].includes(window.location.hostname);

if ('serviceWorker' in navigator && serviceWorkerAllowed) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('No fue posible registrar el service worker.', error);
        });
    });
}

let deferredInstallPrompt = null;
const installPrompt = document.querySelector('#pwa-install-prompt');
const installButton = document.querySelector('#pwa-install-button');
const installDismiss = document.querySelector('#pwa-install-dismiss');
const connectionStatus = document.querySelector('#connection-status');

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    installPrompt?.removeAttribute('hidden');
});

installButton?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
        return;
    }

    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installPrompt?.setAttribute('hidden', '');
});

installDismiss?.addEventListener('click', () => {
    installPrompt?.setAttribute('hidden', '');
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    installPrompt?.setAttribute('hidden', '');
});

const updateConnectionStatus = () => {
    if (navigator.onLine) {
        connectionStatus?.setAttribute('hidden', '');
        if (deferredInstallPrompt) {
            installPrompt?.removeAttribute('hidden');
        }
    } else {
        connectionStatus?.removeAttribute('hidden');
        installPrompt?.setAttribute('hidden', '');
    }
};

window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);
updateConnectionStatus();

document.querySelectorAll('.offcanvas').forEach((offcanvas) => {
    offcanvas.addEventListener('shown.bs.offcanvas', () => {
        offcanvas.querySelector('a, button:not([disabled])')?.focus();
    });
});

document.querySelectorAll('form').forEach((form, formIndex) => {
    form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((control, controlIndex) => {
        if (!control.id) {
            control.id = `form-${formIndex}-field-${controlIndex}`;
        }

        const container = control.closest('.mb-3, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, fieldset, .form-check');
        const label = container?.querySelector('label:not([for])');
        if (label) {
            label.htmlFor = control.id;
        }

        const feedback = container?.querySelector('.invalid-feedback, .text-danger');
        if (feedback) {
            control.setAttribute('aria-invalid', 'true');
            feedback.id ||= `${control.id}-error`;
            control.setAttribute('aria-describedby', feedback.id);
        }
    });
});

document.querySelectorAll('table').forEach((table, index) => {
    if (!table.querySelector('caption')) {
        const caption = document.createElement('caption');
        caption.className = 'visually-hidden';
        caption.textContent = table.getAttribute('aria-label') || `Tabla de resultados ${index + 1}`;
        table.prepend(caption);
    }

    table.querySelectorAll('thead th').forEach((header) => header.setAttribute('scope', 'col'));
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.hasAttribute('data-requires-online') && !navigator.onLine) {
        event.preventDefault();
        connectionStatus?.removeAttribute('hidden');
        connectionStatus?.focus();
        return;
    }

    if (!form.dataset.confirmSubmit) {
        return;
    }

    if (!window.confirm(form.dataset.confirmSubmit)) {
        event.preventDefault();
    }
});
