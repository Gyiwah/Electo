/**
 * Electo Voting System - Service Worker
 * Powered by GAF Tech
 * Version: 1.0.0
 */

const CACHE_NAME = 'electo-v1.0.0';

const PRECACHE_ASSETS = [
    './offline.html',
    './manifest.json',
    './favicon/favicon.ico',
    './favicon/apple-touch-icon.png',
    './favicon/android-chrome-192x192.png',
    './favicon/android-chrome-512x512.png',
    './favicon/favicon-32x32.png',
    './favicon/favicon-16x16.png',
    './favicon/Electo Voting Logo with Checkmark.png',
    './dist/css/modern-theme.css',
    './dist/js/pwa.js',
    './bower_components/bootstrap/dist/css/bootstrap.min.css',
    './bower_components/font-awesome/css/font-awesome.min.css',
    './bower_components/jquery/dist/jquery.min.js'
];

// URLs that must NEVER be served from cache (live tallies, submissions, auth)
const NETWORK_ONLY_PATTERNS = [
    /submit_ballot\.php/i,
    /login\.php/i,
    /logout\.php/i,
    /live_data\.php/i,
    /votes_reset\.php/i,
    /.*_export\.php/i,
    /.*_add\.php/i,
    /.*_edit\.php/i,
    /.*_delete\.php/i,
    /.*_row\.php/i
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[Electo SW] Precache warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        console.log('[Electo SW] Removing stale cache:', key);
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Check if URL matches network-only patterns
    const isNetworkOnly = NETWORK_ONLY_PATTERNS.some((pattern) => pattern.test(url.pathname));
    if (isNetworkOnly) {
        event.respondWith(fetch(request));
        return;
    }

    // Navigation requests (HTML pages)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(() => {
                    return caches.match('./offline.html');
                })
        );
        return;
    }

    // Static assets: cache-first with network fallback
    if (
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.woff') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.ttf') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Update cache in background
                    fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, networkResponse);
                            });
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Default network-first
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});
