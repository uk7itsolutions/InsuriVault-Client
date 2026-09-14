const TONES = {
    success: 'bg-emerald-600 text-white',
    danger: 'bg-rose-600 text-white',
    warning: 'bg-amber-400 text-slate-900',
    info: 'bg-sky-600 text-white',
};

const DISMISS_ICON = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>';

/**
 * Renders a transient notification in the layout's toast container. The message is written as text
 * and never as markup, because several callers pass an error string that came back from the API.
 * An unknown tone reads as informational rather than throwing.
 */
export function showToast(message, tone) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex w-80 max-w-[calc(100vw-2rem)] translate-y-2 items-start gap-3 rounded-md px-4 py-3 text-sm opacity-0 shadow-lg transition duration-300 ease-out ${TONES[tone] || TONES.info}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    const body = document.createElement('p');
    body.className = 'grow';
    body.textContent = message;

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'shrink-0 opacity-70 transition hover:opacity-100';
    dismiss.setAttribute('aria-label', 'Close');
    dismiss.innerHTML = DISMISS_ICON;

    toast.append(body, dismiss);
    container.append(toast);

    requestAnimationFrame(() => toast.classList.remove('translate-y-2', 'opacity-0'));

    const remove = () => {
        toast.classList.add('opacity-0');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    };

    const hideAfterFiveSeconds = setTimeout(remove, 5000);

    dismiss.addEventListener('click', () => {
        clearTimeout(hideAfterFiveSeconds);
        remove();
    });
}
