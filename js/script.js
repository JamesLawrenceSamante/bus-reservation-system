// ============================================
// Bus Reservation System - Client Scripts
// ============================================

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Log Out Confirmation ----------
    const logoutLink = document.getElementById('logoutLink');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');

    if (logoutLink && logoutModal && cancelLogoutBtn) {
        const closeLogoutModal = () => {
            logoutModal.classList.remove('active');
            logoutModal.setAttribute('aria-hidden', 'true');
            logoutLink.focus();
        };

        logoutLink.addEventListener('click', (event) => {
            event.preventDefault();
            logoutModal.classList.add('active');
            logoutModal.setAttribute('aria-hidden', 'false');
            cancelLogoutBtn.focus();
        });

        cancelLogoutBtn.addEventListener('click', closeLogoutModal);
        logoutModal.addEventListener('click', (event) => {
            if (event.target === logoutModal) closeLogoutModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && logoutModal.classList.contains('active')) closeLogoutModal();
        });
    }

    // ---------- Show Log Out only while scrolling ----------
    if (logoutLink) {
        const HIDE_DELAY = 1500;
        let hideTimer = null;
        let hovering = false;

        const canScroll = () => document.documentElement.scrollHeight > window.innerHeight;

        const scheduleHide = () => {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                // Keep it while the user is pointing at it or the page can't scroll at all
                if (!hovering && document.activeElement !== logoutLink && canScroll()) {
                    logoutLink.classList.remove('scroll-visible');
                }
            }, HIDE_DELAY);
        };

        const showLogout = () => {
            logoutLink.classList.add('scroll-visible');
            scheduleHide();
        };

        logoutLink.classList.add('scroll-reveal');
        if (!canScroll()) logoutLink.classList.add('scroll-visible');

        window.addEventListener('scroll', showLogout, { passive: true });
        window.addEventListener('resize', () => {
            if (!canScroll()) logoutLink.classList.add('scroll-visible');
        });
        logoutLink.addEventListener('mouseenter', () => { hovering = true; clearTimeout(hideTimer); });
        logoutLink.addEventListener('mouseleave', () => { hovering = false; scheduleHide(); });
        logoutLink.addEventListener('focus', showLogout);
        logoutLink.addEventListener('blur', scheduleHide);
    }

    // ---------- Login/Signup Tab Switching ----------
    const tabButtons = document.querySelectorAll('.tab-btn');
    if (tabButtons.length) {
        tabButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                tabButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
                document.getElementById(this.dataset.tab + '-panel').classList.remove('hidden');
            });
        });

        // Auto-open Sign Up tab if arriving via a #signup link (e.g. from the homepage)
        if (window.location.hash === '#signup') {
            const signupTabBtn = document.querySelector('.tab-btn[data-tab="signup"]');
            if (signupTabBtn) signupTabBtn.click();
        }
    }

    // ---------- Password visibility ----------
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function () {
            const input = this.closest('.password-field').querySelector('input');
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            this.classList.toggle('is-visible', !isVisible);
            this.setAttribute('aria-pressed', String(!isVisible));
            this.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        });
    });

    // ---------- Caps Lock warning on password fields ----------
    document.querySelectorAll('.password-field input').forEach(input => {
        const warning = document.createElement('p');
        warning.className = 'caps-warning';
        warning.setAttribute('role', 'status');
        warning.textContent = '⚠️ Caps Lock is on';
        warning.hidden = true;
        input.closest('.password-field').after(warning);

        const check = (event) => {
            if (typeof event.getModifierState === 'function') {
                warning.hidden = !event.getModifierState('CapsLock');
            }
        };
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('mousedown', check);   // clicking into the field also reports Caps Lock
        input.addEventListener('blur', () => { warning.hidden = true; });
    });

    // ---------- Cancelation Page: Cancel Modal ----------
    const cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
        const cancelReservationId = document.getElementById('cancelReservationId');
        const closeCancelModalBtn = document.getElementById('closeCancelModalBtn');

        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                cancelReservationId.value = this.dataset.id;
                cancelModal.classList.add('active');
            });
        });

        closeCancelModalBtn.addEventListener('click', () => cancelModal.classList.remove('active'));
        cancelModal.addEventListener('click', (e) => {
            if (e.target === cancelModal) cancelModal.classList.remove('active');
        });
    }

    // ---------- Password Policy + Match Validation (Sign Up, Reset Password) ----------
    document.querySelectorAll('[data-password-policy]').forEach(pwInput => {
        const policyForm = pwInput.closest('form');
        const rulesList = policyForm.querySelector('.password-rules');
        const confirmInput = policyForm.querySelector('[data-password-confirm]');
        const rules = {
            length: pw => pw.length >= 8,
            upper: pw => /[A-Z]/.test(pw),
            lower: pw => /[a-z]/.test(pw),
            number: pw => /[0-9]/.test(pw),
        };
        // Ticks each rule as the user types (same rules the PHP checks on submit)
        const updateRules = () => {
            let allMet = true;
            rulesList.querySelectorAll('li').forEach(li => {
                const met = rules[li.dataset.rule](pwInput.value);
                li.classList.toggle('met', met);
                allMet = allMet && met;
            });
            return allMet;
        };
        pwInput.addEventListener('input', () => {
            rulesList.classList.remove('show-errors');
            updateRules();
        });

        policyForm.addEventListener('submit', function (e) {
            if (!updateRules()) {
                e.preventDefault();
                rulesList.classList.add('show-errors');
                pwInput.focus();
            } else if (confirmInput && pwInput.value !== confirmInput.value) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    });

    // ---------- Public Nav: Mobile Toggle ----------
    const navToggle = document.getElementById('publicNavToggle');
    const navLinks = document.getElementById('publicNavLinks');
    if (navToggle && navLinks) {
        const publicNav = navToggle.closest('.public-nav');
        const setPublicMenu = (open) => {
            publicNav.classList.toggle('menu-open', open);
            navToggle.setAttribute('aria-expanded', open);
        };
        navToggle.addEventListener('click', () => setPublicMenu(!publicNav.classList.contains('menu-open')));
        const navClose = document.getElementById('publicNavClose');
        if (navClose) navClose.addEventListener('click', () => setPublicMenu(false));
        // Tapping the dark backdrop (the header's ::after) lands on the header itself
        publicNav.addEventListener('click', (e) => { if (e.target === publicNav) setPublicMenu(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setPublicMenu(false); });
    }

    // ---------- Logged-in Sidebar: Mobile Toggle ----------
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const sidebar = sidebarToggle.closest('.sidebar');
        const setSidebar = (open) => {
            sidebar.classList.toggle('open', open);
            sidebarToggle.setAttribute('aria-expanded', open);
        };
        const sidebarClose = document.getElementById('sidebarClose');
        if (sidebarClose) sidebarClose.addEventListener('click', () => setSidebar(false));
        const isPhone = () => window.matchMedia('(max-width: 768px)').matches;
        sidebarToggle.addEventListener('click', () => {
            if (isPhone()) {
                setSidebar(!sidebar.classList.contains('open'));
                return;
            }
            // Desktop: collapse/expand the sidebar and remember the choice across pages
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', !collapsed);
            try { localStorage.setItem('brsSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        });
        sidebarToggle.setAttribute('aria-expanded', !isPhone() && !document.documentElement.classList.contains('sidebar-collapsed'));
        // Tapping the dark backdrop (the sidebar's ::after) lands on the sidebar itself
        sidebar.addEventListener('click', (e) => { if (e.target === sidebar) setSidebar(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSidebar(false); });
    }

    // ---------- FAQ Accordion ----------
    document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', function () {
            const item = this.closest('.faq-item');
            const wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
            if (!wasOpen) item.classList.add('open');
        });
    });

});
