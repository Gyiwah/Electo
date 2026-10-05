/**
 * Electo Voting System - PWA Client & Install Prompt Handler
 * Powered by GAF Tech
 */

(function () {
    'use strict';

    var deferredPrompt = null;

    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            // Determine relative path to sw.js based on current directory depth
            var swPath = window.location.pathname.indexOf('/admin/') !== -1 ? '../sw.js' : 'sw.js';
            navigator.serviceWorker.register(swPath, { scope: './' })
                .then(function (registration) {
                    console.log('[Electo PWA] Service Worker registered with scope:', registration.scope);
                })
                .catch(function (error) {
                    console.warn('[Electo PWA] Service Worker registration failed:', error);
                });
        });
    }

    // 2. Standalone Detection (iOS & Android)
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                       window.navigator.standalone === true;

    if (isStandalone) {
        document.documentElement.classList.add('pwa-standalone');
    }

    // 3. Listen for Install Prompt Event
    window.addEventListener('beforeinstallprompt', function (e) {
        // Prevent default mini-infobar on mobile Chrome
        e.preventDefault();
        deferredPrompt = e;

        // Only show if not previously dismissed in this session
        if (!sessionStorage.getItem('electo_pwa_dismissed')) {
            showInstallBanner();
        }
    });

    window.addEventListener('appinstalled', function () {
        console.log('[Electo PWA] Application was successfully installed!');
        deferredPrompt = null;
        removeInstallBanner();
    });

    // 4. Create and Render Install Banner
    function showInstallBanner() {
        if (document.getElementById('gaf-pwa-banner')) return;

        var banner = document.createElement('div');
        banner.id = 'gaf-pwa-banner';
        banner.className = 'gaf-pwa-banner';
        var iconPath = window.location.pathname.indexOf('/admin/') !== -1 ? '../favicon/android-chrome-192x192.png' : 'favicon/android-chrome-192x192.png';
        banner.innerHTML = [
            '<div class="pwa-banner-content">',
                '<div class="pwa-banner-icon" style="background: none; box-shadow: none; padding: 0;">',
                    '<img src="' + iconPath + '" alt="Electo Logo" style="width: 38px; height: 38px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.35);">',
                '</div>',
                '<div class="pwa-banner-text">',
                    '<strong>Install Electo App</strong>',
                    '<span>Fast, 1-tap voting & offline security</span>',
                '</div>',
            '</div>',
            '<div class="pwa-banner-actions">',
                '<button type="button" class="pwa-btn-install" id="gaf-pwa-install-btn">Install</button>',
                '<button type="button" class="pwa-btn-dismiss" id="gaf-pwa-dismiss-btn" aria-label="Dismiss">&times;</button>',
            '</div>'
        ].join('');

        document.body.appendChild(banner);

        // Bind events
        document.getElementById('gaf-pwa-install-btn').addEventListener('click', function () {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('[Electo PWA] User accepted installation prompt');
                    }
                    deferredPrompt = null;
                    removeInstallBanner();
                });
            }
        });

        document.getElementById('gaf-pwa-dismiss-btn').addEventListener('click', function () {
            sessionStorage.setItem('electo_pwa_dismissed', '1');
            removeInstallBanner();
        });
    }

    function removeInstallBanner() {
        var banner = document.getElementById('gaf-pwa-banner');
        if (banner) {
            banner.classList.add('pwa-banner-hiding');
            setTimeout(function () {
                if (banner.parentNode) banner.parentNode.removeChild(banner);
            }, 300);
        }
    }

    // 5. Connection Status Toast
    window.addEventListener('offline', function () {
        showConnectionToast('⚠️ You are offline. Changes will sync when reconnected.', 'offline');
    });

    window.addEventListener('online', function () {
        showConnectionToast('✅ Connection restored. You are back online.', 'online');
    });

    function showConnectionToast(message, type) {
        var existing = document.getElementById('gaf-connection-toast');
        if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

        var toast = document.createElement('div');
        toast.id = 'gaf-connection-toast';
        toast.className = 'gaf-connection-toast ' + (type === 'offline' ? 'toast-offline' : 'toast-online');
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function () {
            toast.classList.add('toast-show');
        }, 10);

        setTimeout(function () {
            toast.classList.remove('toast-show');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 400);
        }, 4000);
    }
})();
