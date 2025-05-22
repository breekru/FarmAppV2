// service-worker.js
const CACHE_NAME = 'farmapp-v1';
const OFFLINE_URL = 'offline.html';

// Files to cache for offline use
const CACHE_FILES = [
    '/',
    '/quick_add.php',
    '/assets/css/styles.css',
    '/assets/js/main.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
    OFFLINE_URL
];

// Install event - cache resources
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Caching app shell');
                return cache.addAll(CACHE_FILES);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', event => {
    const request = event.request;
    
    // Handle navigation requests
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then(response => {
                    // If online, return the response
                    return response;
                })
                .catch(() => {
                    // If offline, return cached page or offline page
                    return caches.match(request)
                        .then(response => {
                            return response || caches.match(OFFLINE_URL);
                        });
                })
        );
        return;
    }
    
    // Handle form submissions when offline
    if (request.method === 'POST' && request.url.includes('quick_add.php')) {
        event.respondWith(
            fetch(request.clone())
                .then(response => {
                    // If online, return the response
                    return response;
                })
                .catch(() => {
                    // If offline, store the form data and return a success response
                    return request.formData().then(formData => {
                        const animalData = {};
                        for (let [key, value] of formData.entries()) {
                            animalData[key] = value;
                        }
                        
                        // Add timestamp
                        animalData.offline_timestamp = Date.now();
                        
                        // Store in IndexedDB
                        return storeOfflineData(animalData).then(() => {
                            return new Response(
                                JSON.stringify({
                                    success: true,
                                    offline: true,
                                    message: 'Animal saved offline. Will sync when online.'
                                }),
                                {
                                    status: 200,
                                    headers: { 'Content-Type': 'application/json' }
                                }
                            );
                        });
                    });
                })
        );
        return;
    }
    
    // For other requests, try cache first, then network
    event.respondWith(
        caches.match(request)
            .then(response => {
                return response || fetch(request);
            })
    );
});

// Store offline data in IndexedDB
async function storeOfflineData(data) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('FarmAppOffline', 1);
        
        request.onerror = () => reject(request.error);
        
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('offlineAnimals')) {
                db.createObjectStore('offlineAnimals', { keyPath: 'id', autoIncrement: true });
            }
        };
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['offlineAnimals'], 'readwrite');
            const store = transaction.objectStore('offlineAnimals');
            
            store.add(data);
            
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        };
    });
}

// Listen for online event to sync data
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SYNC_OFFLINE_DATA') {
        syncOfflineData();
    }
});

// Sync offline data when back online
async function syncOfflineData() {
    try {
        const offlineData = await getOfflineData();
        
        for (const data of offlineData) {
            try {
                const formData = new FormData();
                
                // Convert stored data back to FormData
                for (const [key, value] of Object.entries(data)) {
                    if (key !== 'id' && key !== 'offline_timestamp') {
                        formData.append(key, value);
                    }
                }
                
                // Send to server
                const response = await fetch('/quick_add.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    // Remove from offline storage
                    await removeOfflineData(data.id);
                    console.log('Synced offline animal:', data.name);
                }
            } catch (error) {
                console.error('Error syncing animal:', error);
            }
        }
    } catch (error) {
        console.error('Error during sync:', error);
    }
}

// Get offline data from IndexedDB
async function getOfflineData() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('FarmAppOffline', 1);
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['offlineAnimals'], 'readonly');
            const store = transaction.objectStore('offlineAnimals');
            
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => {
                resolve(getAllRequest.result);
            };
            
            getAllRequest.onerror = () => reject(getAllRequest.error);
        };
        
        request.onerror = () => reject(request.error);
    });
}

// Remove synced data from IndexedDB
async function removeOfflineData(id) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('FarmAppOffline', 1);
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['offlineAnimals'], 'readwrite');
            const store = transaction.objectStore('offlineAnimals');
            
            const deleteRequest = store.delete(id);
            
            deleteRequest.onsuccess = () => resolve();
            deleteRequest.onerror = () => reject(deleteRequest.error);
        };
        
        request.onerror = () => reject(request.error);
    });
}