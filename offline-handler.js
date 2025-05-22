// offline-handler.js - Include this in your quick_add.php page

class OfflineHandler {
    constructor() {
        this.isOnline = navigator.onLine;
        this.pendingUploads = [];
        this.init();
    }

    init() {
        // Register service worker - FIX THE PATH
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js') // Make sure this path is correct
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                    this.serviceWorkerRegistration = registration;
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
        }
    
        // Listen for online/offline events
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateConnectionStatus();
            this.syncOfflineData();
        });
    
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateConnectionStatus();
        });
    
        // Initial status update
        this.updateConnectionStatus();
        
        // Check for pending uploads on page load
        this.checkPendingUploads();
        
        // Set up form submission handler
        this.setupFormHandler();
    }

    updateConnectionStatus() {
        const statusElement = document.getElementById('connection-status');
        const formElement = document.getElementById('quick-add-form');
        
        if (statusElement) {
            if (this.isOnline) {
                statusElement.innerHTML = `
                    <div class="alert alert-success alert-sm">
                        <i class="bi bi-wifi"></i> Online - Data will be saved immediately
                    </div>
                `;
            } else {
                statusElement.innerHTML = `
                    <div class="alert alert-warning alert-sm">
                        <i class="bi bi-wifi-off"></i> Offline - Data will be saved locally and synced when online
                    </div>
                `;
            }
        }

        // Update form behavior based on connection
        if (formElement) {
            const submitButton = formElement.querySelector('button[type="submit"]');
            if (submitButton) {
                if (this.isOnline) {
                    submitButton.innerHTML = '<i class="bi bi-check2-circle"></i> Save';
                } else {
                    submitButton.innerHTML = '<i class="bi bi-download"></i> Save Offline';
                }
            }
        }
    }

    setupFormHandler() {
        const form = document.getElementById('quick-add-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const animalData = this.extractFormData(formData);
            
            if (this.isOnline) {
                // Try to submit online first
                try {
                    await this.submitOnline(formData);
                } catch (error) {
                    console.error('Online submission failed:', error);
                    // Fall back to offline storage
                    await this.saveOffline(animalData);
                }
            } else {
                // Save offline
                await this.saveOffline(animalData);
            }
        });
    }

    extractFormData(formData) {
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        data.offline_timestamp = Date.now();
        data.offline_id = 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        return data;
    }

    async submitOnline(formData) {
        const response = await fetch('/quick_add.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        // Handle successful submission
        this.showSuccessMessage('Animal saved successfully!');
        this.resetForm();
        
        return response;
    }

    async saveOffline(animalData) {
        try {
            // Store in IndexedDB
            await this.storeInIndexedDB(animalData);
            
            // Update pending uploads count
            await this.updatePendingCount();
            
            // Show offline success message
            this.showSuccessMessage('Animal saved offline! Will sync when online.', 'warning');
            this.resetForm();
            
        } catch (error) {
            console.error('Error saving offline:', error);
            this.showErrorMessage('Error saving animal offline. Please try again.');
        }
    }

    async storeInIndexedDB(data) {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('FarmAppOffline', 1);
            
            request.onerror = () => reject(request.error);
            
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains('offlineAnimals')) {
                    db.createObjectStore('offlineAnimals', { keyPath: 'offline_id' });
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

    async syncOfflineData() {
        if (!this.isOnline) return;

        try {
            const offlineData = await this.getOfflineData();
            
            if (offlineData.length === 0) return;

            this.showSyncMessage(`Syncing ${offlineData.length} offline entries...`);

            let syncedCount = 0;
            
            for (const data of offlineData) {
                try {
                    const formData = new FormData();
                    
                    // Convert stored data back to FormData
                    for (const [key, value] of Object.entries(data)) {
                        if (key !== 'offline_id' && key !== 'offline_timestamp') {
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
                        await this.removeOfflineData(data.offline_id);
                        syncedCount++;
                        console.log('Synced offline animal:', data.name || 'Unnamed');
                    }
                } catch (error) {
                    console.error('Error syncing animal:', error);
                }
            }
            
            if (syncedCount > 0) {
                this.showSuccessMessage(`Successfully synced ${syncedCount} offline entries!`);
                await this.updatePendingCount();
            }
            
        } catch (error) {
            console.error('Error during sync:', error);
        }
    }

    async getOfflineData() {
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

    async removeOfflineData(offlineId) {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('FarmAppOffline', 1);
            
            request.onsuccess = () => {
                const db = request.result;
                const transaction = db.transaction(['offlineAnimals'], 'readwrite');
                const store = transaction.objectStore('offlineAnimals');
                
                const deleteRequest = store.delete(offlineId);
                
                deleteRequest.onsuccess = () => resolve();
                deleteRequest.onerror = () => reject(deleteRequest.error);
            };
            
            request.onerror = () => reject(request.error);
        });
    }

    async checkPendingUploads() {
        try {
            const offlineData = await this.getOfflineData();
            await this.updatePendingCount(offlineData.length);
            
            if (offlineData.length > 0 && this.isOnline) {
                // Auto-sync if online
                setTimeout(() => {
                    this.syncOfflineData();
                }, 2000);
            }
        } catch (error) {
            console.error('Error checking pending uploads:', error);
        }
    }

    async updatePendingCount(count = null) {
        if (count === null) {
            const offlineData = await this.getOfflineData();
            count = offlineData.length;
        }

        const pendingElement = document.getElementById('pending-uploads');
        if (pendingElement) {
            if (count > 0) {
                pendingElement.innerHTML = `
                    <div class="alert alert-info alert-sm">
                        <i class="bi bi-cloud-upload"></i> ${count} animal(s) pending sync
                        ${this.isOnline ? '<button class="btn btn-sm btn-outline-primary ms-2" onclick="offlineHandler.syncOfflineData()">Sync Now</button>' : ''}
                    </div>
                `;
                pendingElement.style.display = 'block';
            } else {
                pendingElement.style.display = 'none';
            }
        }
    }

    showSuccessMessage(message, type = 'success') {
        this.showMessage(message, type);
    }

    showErrorMessage(message) {
        this.showMessage(message, 'danger');
    }

    showSyncMessage(message) {
        this.showMessage(message, 'info');
    }

    showMessage(message, type = 'success') {
        const messageContainer = document.getElementById('message-container');
        if (!messageContainer) return;

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        messageContainer.appendChild(alertDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    resetForm() {
        const form = document.getElementById('quick-add-form');
        if (form) {
            form.reset();
            
            // Reset image preview
            const imagePreview = document.getElementById('preview');
            if (imagePreview) {
                imagePreview.style.display = 'none';
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.offlineHandler = new OfflineHandler();
});

// Handle visibility change (when app comes back to foreground)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && navigator.onLine && window.offlineHandler) {
        // Check for pending uploads when app becomes visible
        window.offlineHandler.checkPendingUploads();
    }
});