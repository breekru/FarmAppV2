Labels[0] : '' ?>';
    
    // Default datasets - will be populated with the most common type's breeds
    let breedLabels = [];
    let breedValues = [];
    
    if (breedData[mostCommonType]) {
        breedLabels = breedData[mostCommonType].labels;
        breedValues = breedData[mostCommonType].data;
    }
    
    const breedChart = new Chart(breedCtx, {
        type: 'bar',
        data: {
            labels: breedLabels,
            datasets: [{
                label: 'Number of Animals',
                data: breedValues,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Breeds for ' + mostCommonType
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Type filter change should update breed chart
    document.getElementById('type').addEventListener('change', function() {
        const selectedType = this.value || mostCommonType;
        
        if (breedData[selectedType]) {
            breedChart.data.labels = breedData[selectedType].labels;
            breedChart.data.datasets[0].data = breedData[selectedType].data;
            breedChart.options.plugins.title.text = 'Breeds for ' + selectedType;
            breedChart.update();
        }
    });
    
    // Export to CSV functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
        // Get table data
        const table = document.getElementById('inventory-table');
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length - 1; j++) { // Skip "Actions" column
                // Get the text content, removing any non-breaking spaces
                let data = cols[j].textContent.replace(/\u00A0/g, ' ').trim();
                
                // Escape quotes and wrap in quotes if contains comma
                if (data.includes(',')) {
                    data = '"' + data.replace(/"/g, '""') + '"';
                }
                row.push(data);
            }
            csv.push(row.join(','));
        }
        
        // Create and download the CSV file
        const csvString = csv.join('\n');
        const filename = 'farm_inventory_' + new Date().toISOString().slice(0, 10) + '.csv';
        
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        
        // Create a download link and trigger it
        const link = document.createElement('a');
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });
});
</script>

<?php
/**
 * Helper function to get appropriate badge class based on animal status
 * 
 * @param string $status Animal status
 * @return string CSS class name for the badge
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Alive':
            return 'success';
        case 'Dead':
            return 'danger';
        case 'Sold':
            return 'info';
        case 'For Sale':
            return 'warning';
        case 'Harvested':
            return 'secondary';
        default:
            return 'primary';
    }
}

// Include footer
include_once 'includes/footer.php';
?>