/**
 * FarmApp - Main JavaScript File
 * 
 * This file contains application-wide JavaScript functionality
 * for the FarmApp livestock management system.
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    initTooltips();
    
    // Initialize dynamic form controls
    initFormControls();
    
    // Initialize data tables
    initDataTables();
    
    // Initialize charts if they exist on the page
    initCharts();
    
    // Setup confirmation modals
    setupConfirmations();
    
    // Auto-dismiss alerts after delay
    setupAutoDismissAlerts();
    
    // Initialize image preview functionality
    setupImagePreviews();
});

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize dynamic form controls
 */
function initFormControls() {
    // Show/hide fields based on status selection
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        const statusDependentFields = document.querySelectorAll('.status-dependent');
        
        function updateFieldVisibility() {
            const selectedStatus = statusSelect.value;
            
            statusDependentFields.forEach(field => {
                const statusValues = field.getAttribute('data-status').split(',');
                if (statusValues.includes(selectedStatus)) {
                    field.style.display = 'block';
                } else {
                    field.style.display = 'none';
                }
            });
        }
        
        // Initial update
        updateFieldVisibility();
        
        // Update on status change
        statusSelect.addEventListener('change', updateFieldVisibility);
    }
    
    // Dynamic behavior for the for-sale toggle
    const forSaleSelect = document.getElementById('for_sale');
    const statusField = document.getElementById('status');
    
    if (forSaleSelect && statusField) {
        forSaleSelect.addEventListener('change', function() {
            if (this.value === 'Yes') {
                // If marked for sale, update status
                statusField.value = 'For Sale';
            } else if (this.value === 'Has Been Sold') {
                // If marked as sold, update status
                statusField.value = 'Sold';
            }
        });
        
        // Also update for_sale based on status
        statusField.addEventListener('change', function() {
            if (this.value === 'For Sale') {
                forSaleSelect.value = 'Yes';
            } else if (this.value === 'Sold') {
                forSaleSelect.value = 'Has Been Sold';
            } else if (forSaleSelect.value === 'Yes') {
                // If status changed from 'For Sale' to something else, update for_sale
                forSaleSelect.value = 'No';
            }
        });
    }
    
    // Generic form validation for required fields
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Initialize DataTables for enhanced table functionality
 */
function initDataTables() {
    // Check if DataTables library is loaded
    if (typeof $.fn.DataTable !== 'undefined') {
        const tables = document.querySelectorAll('.datatable');
        tables.forEach(table => {
            $(table).DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                }
            });
        });
    } else {
        // Fallback for simple table searching
        const searchInputs = document.querySelectorAll('.table-search-input');
        
        searchInputs.forEach(input => {
            const targetTable = document.querySelector(input.dataset.target);
            
            if (targetTable) {
                input.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = targetTable.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    }
}

/**
 * Initialize Charts using Chart.js
 */
function initCharts() {
    // Check if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        // Animal Type Distribution Chart
        const animalTypeChartElement = document.getElementById('animalTypeChart');
        if (animalTypeChartElement && animalTypeChartElement.dataset.values) {
            const labels = JSON.parse(animalTypeChartElement.dataset.labels || '[]');
            const values = JSON.parse(animalTypeChartElement.dataset.values || '[]');
            const colors = [
                'rgba(25, 135, 84, 0.7)',    // Green
                'rgba(13, 110, 253, 0.7)',   // Blue
                'rgba(255, 193, 7, 0.7)',    // Yellow
                'rgba(220, 53, 69, 0.7)',    // Red
                'rgba(108, 117, 125, 0.7)'   // Gray
            ];
            
            new Chart(animalTypeChartElement, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: 'white',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Status Distribution Chart
        const statusChartElement = document.getElementById('statusChart');
        if (statusChartElement && statusChartElement.dataset.values) {
            const labels = JSON.parse(statusChartElement.dataset.labels || '[]');
            const values = JSON.parse(statusChartElement.dataset.values || '[]');
            const colors = [
                'rgba(25, 135, 84, 0.7)',    // Green - Alive
                'rgba(220, 53, 69, 0.7)',    // Red - Dead
                'rgba(13, 110, 253, 0.7)',   // Blue - Sold
                'rgba(255, 193, 7, 0.7)',    // Yellow - For Sale
                'rgba(108, 117, 125, 0.7)'   // Gray - Harvested
            ];
            
            new Chart(statusChartElement, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: 'white',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Monthly Activity Chart
        const activityChartElement = document.getElementById('activityChart');
        if (activityChartElement && activityChartElement.dataset.values) {
            const labels = JSON.parse(activityChartElement.dataset.labels || '[]');
            const added = JSON.parse(activityChartElement.dataset.added || '[]');
            const sold = JSON.parse(activityChartElement.dataset.sold || '[]');
            
            new Chart(activityChartElement, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Added',
                            data: added,
                            backgroundColor: 'rgba(25, 135, 84, 0.7)',
                            borderColor: 'rgba(25, 135, 84, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Sold/Removed',
                            data: sold,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }
}

/**
 * Setup confirmation modals for delete actions
 */
function setupConfirmations() {
    const deleteButtons = document.querySelectorAll('[data-action="delete"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const target = this.dataset.target;
            const message = this.dataset.message || 'Are you sure you want to delete this item?';
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Setup auto-dismissing alerts
 */
function setupAutoDismissAlerts() {
    const alerts = document.querySelectorAll('.alert-dismissible:not(.alert-persistent)');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            // Check if Bootstrap is available
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                // Fallback
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
                alert.style.transition = 'opacity 0.5s';
            }
        }, 5000); // Auto dismiss after 5 seconds
    });
}

/**
 * Setup image previews for file inputs
 */
function setupImagePreviews() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        const previewContainer = document.querySelector(input.dataset.preview || '#imagePreview');
        
        if (previewContainer) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        let previewImage = previewContainer.querySelector('img');
                        
                        if (!previewImage) {
                            previewImage = document.createElement('img');
                            previewImage.className = 'img-preview img-fluid img-thumbnail';
                            previewContainer.appendChild(previewImage);
                        }
                        
                        previewImage.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    });
}

/**
 * Generate lineage tree visualization
 * @param {string} containerId - ID of the container element
 * @param {Object} animalData - JSON object containing animal and lineage data
 */
function generateFamilyTree(containerId, animalData) {
    const container = document.getElementById(containerId);
    if (!container || !animalData) return;
    
    // Check if d3.js is available
    if (typeof d3 === 'undefined') {
        container.innerHTML = '<div class="alert alert-warning">Family tree visualization requires d3.js library. Please include it in your page.</div>';
        return;
    }
    
    // Clear the container
    container.innerHTML = '';
    
    // Set dimensions
    const width = container.clientWidth;
    const height = 500;
    
    // Create SVG
    const svg = d3.select(`#${containerId}`)
        .append('svg')
        .attr('width', width)
        .attr('height', height);
    
    // Create a hierarchical tree layout
    const treeLayout = d3.tree()
        .size([width - 100, height - 100]);
    
    // Convert data to d3 hierarchy
    const root = d3.hierarchy(animalData);
    
    // Assign positions to nodes
    const treeData = treeLayout(root);
    
    // Add links between nodes
    svg.selectAll('.link')
        .data(treeData.links())
        .enter()
        .append('path')
        .attr('class', 'link')
        .attr('d', d => {
            return `M${d.source.x},${d.source.y}C${d.source.x},${(d.source.y + d.target.y) / 2} ${d.target.x},${(d.source.y + d.target.y) / 2} ${d.target.x},${d.target.y}`;
        })
        .attr('fill', 'none')
        .attr('stroke', '#ccc')
        .attr('stroke-width', 2);
    
    // Add nodes
    const nodes = svg.selectAll('.node')
        .data(treeData.descendants())
        .enter()
        .append('g')
        .attr('class', 'node')
        .attr('transform', d => `translate(${d.x},${d.y})`);
    
    // Add node circles
    nodes.append('circle')
        .attr('r', 10)
        .attr('fill', d => d.data.gender === 'Male' ? '#007bff' : '#e83e8c');
    
    // Add node labels
    nodes.append('text')
        .attr('dy', '0.35em')
        .attr('x', d => d.children ? -12 : 12)
        .attr('text-anchor', d => d.children ? 'end' : 'start')
        .text(d => d.data.name);
}

/**
 * Print function for reports
 */
function printReport() {
    window.print();
}

/**
 * Export table to CSV
 * @param {string} tableId - ID of the table to export
 * @param {string} filename - Name of the file to download
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Replace any commas in the cell text with spaces to avoid CSV formatting issues
            let text = cols[j].innerText.replace(/,/g, ' ');
            // Wrap in quotes to handle any other special characters
            row.push(`"${text}"`);
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Helper function to download CSV data
 * @param {string} csv - CSV content
 * @param {string} filename - Name of the file to download
 */
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    // Create a download link
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    // Add link to the body
    document.body.appendChild(downloadLink);
    
    // Click the link
    downloadLink.click();
    
    // Clean up
    document.body.removeChild(downloadLink);
}