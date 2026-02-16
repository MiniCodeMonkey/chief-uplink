// Chief Web Push Service Worker

self.addEventListener('push', function (event) {
    if (!event.data) return;

    let data;
    try {
        data = event.data.json();
    } catch {
        return;
    }

    const title = data.title || 'Chief';
    const options = {
        body: data.body || '',
        icon: '/favicon.svg',
        badge: '/favicon.svg',
        tag: data.tag || 'chief-notification',
        data: data.data || {},
        requireInteraction: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (clientList) {
                // Focus existing window if available
                for (const client of clientList) {
                    if (client.url.includes(url) && 'focus' in client) {
                        return client.focus();
                    }
                }

                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            }),
    );
});
