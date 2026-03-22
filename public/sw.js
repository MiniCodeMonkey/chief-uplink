self.addEventListener('push', function (event) {
    if (!event.data) return;

    var data = event.data.json();

    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        badge: data.badge || '/favicon.ico',
        data: {
            url: data.url || '/',
        },
        tag: data.tag || 'chief-uplink',
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Chief Uplink', options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
