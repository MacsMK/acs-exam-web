const header = document.querySelector('.masthead');
const menu = header.querySelector('.mobile-menu');
const desktopLinks = Array.from(header.querySelectorAll('.desktop-nav a'));
const sections = desktopLinks.map(link => document.getElementById(link.hash.slice(1)));
const stage = document.querySelector('.figure-stage');
const studentPreview = document.querySelector('.student-preview');
const motionPreference = window.matchMedia('(prefers-reduced-motion: no-preference)');
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
let pointer = null;
let viewportChanged = true;
let updatePending = false;

function updateHeader() {
    const top = header.offsetHeight + 30;
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    const readPercent = scrollable > 0 ? Math.min(100, Math.max(0, window.scrollY / scrollable * 100)) : 0;
    const current = sections.filter(section => section.getBoundingClientRect().top <= top).pop();
    header.classList.toggle('is-scrolled', window.scrollY > 12);
    header.style.setProperty('--read-progress', readPercent + '%');
    desktopLinks.forEach(link => {
        if (current && link.hash === '#' + current.id) {
            link.setAttribute('aria-current', 'location');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

// Measure stationary frames. The artwork moves inside them, so its own
// transform never feeds back into the scroll or pointer calculations.
function updateDepth() {
    const height = window.innerHeight;
    const bounds = stage.getBoundingClientRect();
    if (bounds.bottom > 0 && bounds.top < height) {
        const x = pointer ? clamp(2 * (pointer.x - bounds.left) / bounds.width - 1, -1, 1) : 0;
        const y = pointer ? clamp(2 * (pointer.y - bounds.top) / bounds.height - 1, -1, 1) : 0;
        const progress = clamp((height - bounds.top) / (height + bounds.height), 0, 1);
        const travel = -16 + 40 * progress;
        stage.style.setProperty('--depth-x', (x * 14).toFixed(2) + 'px');
        stage.style.setProperty('--depth-y', (y * 9).toFixed(2) + 'px');
        stage.style.setProperty('--tilt-x', (-y * 4).toFixed(2) + 'deg');
        stage.style.setProperty('--tilt-y', (x * 4).toFixed(2) + 'deg');
        stage.style.setProperty('--travel', travel.toFixed(2) + 'px');
    }
    if (viewportChanged) {
        const phone = studentPreview.getBoundingClientRect();
        if (phone.bottom > 0 && phone.top < height) {
            const position = (height - 2 * phone.top - phone.height) / (height + phone.height);
            studentPreview.style.setProperty('--view-position', clamp(position, -1, 1).toFixed(4));
        }
    }
}

function renderFrame() {
    if (motionPreference.matches) updateDepth();
    if (viewportChanged) updateHeader();
    viewportChanged = false;
    updatePending = false;
}

function requestFrame() {
    if (updatePending) return;
    updatePending = true;
    window.requestAnimationFrame(renderFrame);
}

function onViewportChange() {
    viewportChanged = true;
    requestFrame();
}

window.addEventListener('scroll', onViewportChange, { passive: true });
window.addEventListener('resize', onViewportChange);
window.addEventListener('pageshow', onViewportChange);

stage.addEventListener('pointermove', event => {
    if (!motionPreference.matches || !finePointer.matches || event.pointerType === 'touch') return;
    pointer = { x: event.clientX, y: event.clientY };
    requestFrame();
});
function resetPointer() {
    pointer = null;
    requestFrame();
}
stage.addEventListener('pointerleave', resetPointer);
stage.addEventListener('pointercancel', resetPointer);
finePointer.addEventListener('change', resetPointer);

// Start each entrance on intersection. Unvisited sections remain readable
// during anchor jumps, browser search, printing, and without JavaScript.
const pendingEntrances = new Set(document.querySelectorAll(
    '.section-title, .capability-grid article, .workflow-steps li, .demo-window, .plan, .trial, .student-copy .intro'
));
const observer = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
    for (const entry of entries) {
        if (!entry.isIntersecting || !motionPreference.matches) continue;
        entry.target.classList.add('has-entered');
        pendingEntrances.delete(entry.target);
        observer.unobserve(entry.target);
    }
}, { threshold: 0.06 }) : null;

function syncMotion() {
    pointer = null;
    document.body.classList.toggle('motion-enabled', motionPreference.matches);
    observer?.disconnect();
    if (motionPreference.matches && observer) {
        pendingEntrances.forEach(element => observer.observe(element));
    } else {
        document.querySelectorAll('.has-entered').forEach(element => element.classList.remove('has-entered'));
    }
    onViewportChange();
}
motionPreference.addEventListener('change', syncMotion);
syncMotion();

document.addEventListener('click', event => {
    if (menu.open && (!menu.contains(event.target) || event.target.closest('a'))) {
        menu.open = false;
    }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu.open) {
        menu.open = false;
        menu.querySelector('summary').focus();
    }
});

const privacy = document.getElementById('aviso');
if (typeof privacy.showModal === 'function') {
    document.querySelector('[data-privacy-link]').addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        privacy.showModal();
    });
    privacy.addEventListener('click', event => {
        if (event.target === privacy) privacy.close();
    });
}
