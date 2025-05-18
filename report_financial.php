// Escape quotes and wrap in quotes if contains comma
                if (data.includes(',') || data.includes('"')) {
                    data = '"' + data.replace(/"/g, '""') + '"';
                }
                row.push(data);
            }
            csv.push(row.join(','));
        }
        
        // Create and download the CSV file
        const csvString = csv.join('\n');
        
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
    }
});
</script>

<style>
@media print {
    .btn, .card-header button, .no-print {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #fff !important;
        border-bottom: 1px solid #000 !important;
        color: #000 !important;
    }
    
    .table {
        border-collapse: collapse !important;
    }
    
    .table td, .table th {
        border: 1px solid #ddd !important;
    }
}
</style>

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