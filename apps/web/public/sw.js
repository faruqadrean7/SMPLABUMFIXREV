/// <reference lib="webworker" />

/**
 * Service Worker for SISTLABUM Web Push Notifications
 * Compliant with AGENTS.md and 09_SECURITY_PRIVACY.md
 */

self.addEventListener('install', (event) => {
  // Activate immediately without waiting for existing clients to close
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

/**
 * Sanitize and validate target URL to prevent open-redirect attacks.
 * Rejects control characters, backslashes, protocol-relative '//', and non-http(s) schemes.
 * Only internal relative paths (e.g. '/portal/siswa/cbt') or same-origin URLs are allowed.
 */
function sanitizeTargetUrl(urlInput) {
  if (!urlInput || typeof urlInput !== 'string') {
    return '/';
  }

  const trimmed = urlInput.trim();

  // Reject control characters, newlines, tabs, and backslashes
  if (/[\x00-\x1F\x7F\\]/.test(trimmed)) {
    return '/';
  }

  // Reject protocol-relative URLs (//evil.com)
  if (trimmed.startsWith('//')) {
    return '/';
  }

  // Reject dangerous schemes or external origins
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) {
    try {
      const parsed = new URL(trimmed);
      if (parsed.origin === self.location.origin) {
        return parsed.pathname + parsed.search + parsed.hash;
      }
    } catch (e) {
      // Invalid URL
    }
    return '/';
  }

  // Allow safe root-relative path
  if (trimmed.startsWith('/')) {
    return trimmed;
  }

  return '/';
}

// =============================================================================
// Push Event Handler
// =============================================================================
self.addEventListener('push', (event) => {
  let payload = {
    title: 'Notifikasi SISTLABUM',
    body: 'Anda memiliki pemberitahuan baru.',
    icon: '/images/logo-icon.png',
    badge: '/images/badge-icon.png',
    data: {
      url: '/',
    },
  };

  if (event.data) {
    try {
      const data = event.data.json();
      payload.title = data.title || payload.title;
      payload.body = data.body || payload.body;
      payload.icon = data.icon || payload.icon;
      payload.badge = data.badge || payload.badge;

      // Extract safe deep link URL
      const targetUrl = data.data?.deep_link || data.data?.url || data.url || '/';
      payload.data = {
        ...data.data,
        url: sanitizeTargetUrl(targetUrl),
      };
    } catch (e) {
      payload.body = event.data.text() || payload.body;
    }
  }

  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: payload.icon,
      badge: payload.badge,
      data: payload.data,
      tag: payload.data?.tag || 'sistlabum-notification',
      renotify: true,
    })
  );
});

// =============================================================================
// Notification Click Handler
// =============================================================================
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetPath = sanitizeTargetUrl(event.notification.data?.url);
  const targetUrl = new URL(targetPath, self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Focus existing tab if open on same origin
      for (const client of clientList) {
        if ('focus' in client && client.url.startsWith(self.location.origin)) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }

      // Otherwise open a new window
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
    })
  );
});
