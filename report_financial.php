<?php
/**
 * Financial Report Page - COMPREHENSIVE FIX
 * 
 * This page displays financial information for farm animals,
 * including purchases, sales, and overall farm profitability.
 */

// Include the configuration file
require_once 'config.php';

// Initialize the session
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$current_user = $_SESSION["username"];

// Get database connection
$db = getDbConnection();

// Set page variables
$page_title = "Financial Report";
$page_header = "Farm Financial Report";
$page_subheader = "Track and analyze your farm's financial performance";

// Process date range with appropriate defaults and validation
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 year', strtotime($endDate)));
$selectedType = isset($_GET['type']) && !empty($_GET['type']) ? $_GET['type'] : null;

// Initialize variables
$transactions = [];
$totalPurchases = 0;
$totalSales = 0;
$totalProfit = 0;
$purchasesByType = [];
$salesByType = [];
$profitByType = [];
$monthlyData = [];
$inventoryValue = 0;
$inventoryByType = [];
$availableTypes = [];

// Prepare monthly data array with all months in range
$startMonth = new DateTime($startDate);
$endMonth = new DateTime($endDate);
$interval = new DateInterval('P1M'); // 1 month interval
$period = new DatePeriod($startMonth, $interval, $endMonth->modify('+1 month')); // Include end month

foreach ($period as $date) {
    $monthKey = $date->format('Y-m');
    $monthlyData[$monthKey] = [
        'purchases' => 0,
        'sales' => 0,
        'profit' => 0,
        'month_label' => $date->format('M Y')
    ];
}

// Get available animal types for filter dropdown
try {
    $typesStmt = $db->prepare("
        SELECT DISTINCT type FROM animals 
        WHERE user_id = :user_id 
        ORDER BY type
    ");
    $typesStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $typesStmt->execute();
    $availableTypes = $typesStmt->fetchAll();
} catch (Exception $e) {
    // Just log the error and continue - no need to show error to user for this part
    error_log("Error fetching animal types: " . $e->getMessage());
}

// Get purchase transactions
try {
    $purchaseQuery = "
        SELECT 
            id, 
            type, 
            name, 
            number, 
            purch_cost, 
            date_purchased
        FROM animals 
        WHERE user_id = :user_id 
        AND date_purchased IS NOT NULL 
        AND purch_cost IS NOT NULL
        AND date_purchased BETWEEN :start_date AND :end_date
    ";
    
    if ($selectedType) {
        $purchaseQuery .= " AND type = :type";
    }
    
    $purchaseStmt = $db->prepare($purchaseQuery);
    $purchaseStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $purchaseStmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
    $purchaseStmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
    
    if ($selectedType) {
        $purchaseStmt->bindParam(':type', $selectedType, PDO::PARAM_STR);
    }
    
    $purchaseStmt->execute();
    
    // Process purchase transactions
    while ($transaction = $purchaseStmt->fetch(PDO::FETCH_ASSOC)) {
        $animalType = $transaction['type'];
        $cost = floatval($transaction['purch_cost']);
        
        // Skip if cost is zero
        if ($cost <= 0) continue;
        
        // Add to transactions array with transaction type
        $transaction['transaction_type'] = 'purchase';
        $transactions[] = $transaction;
        
        // Update totals
        $totalPurchases += $cost;
        
        // Initialize type data if not exists
        if (!isset($purchasesByType[$animalType])) {
            $purchasesByType[$animalType] = 0;
            $salesByType[$animalType] = 0;
            $profitByType[$animalType] = 0;
        }
        
        $purchasesByType[$animalType] += $cost;
        
        // Add to monthly data
        $month = date('Y-m', strtotime($transaction['date_purchased']));
        if (isset($monthlyData[$month])) {
            $monthlyData[$month]['purchases'] += $cost;
            $monthlyData[$month]['profit'] -= $cost;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching purchase data: " . $e->getMessage());
}

// Get sales transactions (separate query to avoid potential issues)
try {
    $saleQuery = "
        SELECT 
            id, 
            type, 
            name, 
            number, 
            purch_cost,
            date_purchased,
            sell_price, 
            date_sold
        FROM animals 
        WHERE user_id = :user_id 
        AND date_sold IS NOT NULL 
        AND sell_price IS NOT NULL
        AND date_sold BETWEEN :start_date AND :end_date
    ";
    
    if ($selectedType) {
        $saleQuery .= " AND type = :type";
    }
    
    $saleStmt = $db->prepare($saleQuery);
    $saleStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $saleStmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
    $saleStmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
    
    if ($selectedType) {
        $saleStmt->bindParam(':type', $selectedType, PDO::PARAM_STR);
    }
    
    $saleStmt->execute();
    
    // Process sale transactions
    while ($transaction = $saleStmt->fetch(PDO::FETCH_ASSOC)) {
        $animalType = $transaction['type'];
        $price = floatval($transaction['sell_price']);
        
        // Skip if price is zero
        if ($price <= 0) continue;
        
        // Add to transactions array with transaction type
        $transaction['transaction_type'] = 'sale';
        $transactions[] = $transaction;
        
        // Update totals
        $totalSales += $price;
        
        // Initialize type data if not exists
        if (!isset($purchasesByType[$animalType])) {
            $purchasesByType[$animalType] = 0;
            $salesByType[$animalType] = 0;
            $profitByType[$animalType] = 0;
        }
        
        $salesByType[$animalType] += $price;
        
        // Calculate profit for this animal
        $cost = !empty($transaction['purch_cost']) ? floatval($transaction['purch_cost']) : 0;
        $profit = $price - $cost;
        $totalProfit += $profit;
        $profitByType[$animalType] += $profit;
        
        // Add to monthly data
        $month = date('Y-m', strtotime($transaction['date_sold']));
        if (isset($monthlyData[$month])) {
            $monthlyData[$month]['sales'] += $price;
            $monthlyData[$month]['profit'] += $price; // For sold animals, add the full sale price to profit
            
            // If this animal was also purchased within the date range, we've already subtracted its cost
            // Otherwise, subtract the purchase cost here to properly calculate profit
            if (empty($transaction['date_purchased']) || 
                strtotime($transaction['date_purchased']) < strtotime($startDate) || 
                strtotime($transaction['date_purchased']) > strtotime($endDate)) {
                $monthlyData[$month]['profit'] -= $cost;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching sales data: " . $e->getMessage());
}

// Sort transactions by date
usort($transactions, function($a, $b) {
    $dateA = ($a['transaction_type'] === 'purchase') ? $a['date_purchased'] : $a['date_sold'];
    $dateB = ($b['transaction_type'] === 'purchase') ? $b['date_purchased'] : $b['date_sold'];
    return strtotime($dateA) - strtotime($dateB);
});

// Sort monthly data by date
ksort($monthlyData);

// Get current inventory value
try {
    $inventoryQuery = "
        SELECT 
            type, 
            COUNT(*) as count, 
            SUM(CASE WHEN purch_cost IS NOT NULL AND purch_cost > 0 THEN purch_cost ELSE 0 END) as total_cost,
            SUM(CASE WHEN for_sale = 'Yes' AND sell_price IS NOT NULL AND sell_price > 0 THEN sell_price ELSE 0 END) as total_listed
        FROM animals 
        WHERE user_id = :user_id 
        AND status = 'Alive'
    ";
    
    if ($selectedType) {
        $inventoryQuery .= " AND type = :type";
    }
    
    $inventoryQuery .= " GROUP BY type ORDER BY type";
    
    $inventoryStmt = $db->prepare($inventoryQuery);
    $inventoryStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    
    if ($selectedType) {
        $inventoryStmt->bindParam(':type', $selectedType, PDO::PARAM_STR);
    }
    
    $inventoryStmt->execute();
    
    while ($item = $inventoryStmt->fetch(PDO::FETCH_ASSOC)) {
        $cost = !empty($item['total_cost']) ? floatval($item['total_cost']) : 0;
        $listedValue = !empty($item['total_listed']) ? floatval($item['total_listed']) : 0;
        
        // Use listed value if available, otherwise use the cost
        $value = ($listedValue > 0) ? $listedValue : $cost;
        $inventoryValue += $value;
        $inventoryByType[$item['type']] = [
            'count' => $item['count'],
            'value' => $value
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching inventory data: " . $e->getMessage());
}

// Include header
include_once 'includes/header.php';
?>

<!-- Filter and Action Controls -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Filter Report</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Animal Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($availableTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type['type']) ?>" <?= $selectedType === $type['type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['type']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" onclick="window.print();" class="btn btn-outline-dark">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                    <button type="button" id="exportCSV" class="btn btn-success">
                        <i class="bi bi-download"></i> Export to CSV
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary -->
<div class="row mb-4">
    <div class="col-md-8">
        <!-- Chart -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Financial Performance</h5>
            </div>
            <div class="card-body">
                <?php if (empty($monthlyData)): ?>
                <div class="alert alert-info">
                    No financial data available for the selected date range and filters.
                </div>
                <?php else: ?>
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="financialChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Summary Cards -->
        <div class="card shadow-sm mb-3">
            <div class="card-body bg-success text-white">
                <h5 class="card-title">Total Sales</h5>
                <h2 class="mb-0">$<?= number_format($totalSales, 2) ?></h2>
            </div>
        </div>
        
        <div class="card shadow-sm mb-3">
            <div class="card-body bg-danger text-white">
                <h5 class="card-title">Total Purchases</h5>
                <h2 class="mb-0">$<?= number_format($totalPurchases, 2) ?></h2>
            </div>
        </div>
        
        <div class="card shadow-sm mb-3">
            <div class="card-body <?= $totalProfit >= 0 ? 'bg-primary' : 'bg-secondary' ?> text-white">
                <h5 class="card-title">Net Profit</h5>
                <h2 class="mb-0">$<?= number_format($totalProfit, 2) ?></h2>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-body bg-info text-white">
                <h5 class="card-title">Inventory Value</h5>
                <h2 class="mb-0">$<?= number_format($inventoryValue, 2) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Performance by Animal Type -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Performance by Animal Type</h3>
        <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#typePerformance" aria-expanded="true">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse show" id="typePerformance">
        <div class="card-body">
            <?php if (empty($purchasesByType) && empty($salesByType)): ?>
            <div class="alert alert-info">
                No financial performance data available for the selected date range and filters.
            </div>
            <?php else: ?>
            <div class="row">
                <div class="col-md-7">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="typePerformanceChart"></canvas>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Animal Type</th>
                                    <th>Purchases</th>
                                    <th>Sales</th>
                                    <th>Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Combine purchases and sales to ensure all types are included
                                $allTypes = array_unique(array_merge(array_keys($purchasesByType), array_keys($salesByType)));
                                sort($allTypes);
                                
                                foreach ($allTypes as $type): 
                                    $purchases = isset($purchasesByType[$type]) ? $purchasesByType[$type] : 0;
                                    $sales = isset($salesByType[$type]) ? $salesByType[$type] : 0;
                                    $profit = isset($profitByType[$type]) ? $profitByType[$type] : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($type) ?></td>
                                    <td>$<?= number_format($purchases, 2) ?></td>
                                    <td>$<?= number_format($sales, 2) ?></td>
                                    <td class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                        $<?= number_format($profit, 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td>Total</td>
                                    <td>$<?= number_format($totalPurchases, 2) ?></td>
                                    <td>$<?= number_format($totalSales, 2) ?></td>
                                    <td class="<?= $totalProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                                        $<?= number_format($totalProfit, 2) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Current Inventory Value -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Current Inventory Value</h3>
        <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#currentInventory" aria-expanded="true">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse show" id="currentInventory">
        <div class="card-body">
            <?php if (empty($inventoryByType)): ?>
            <div class="alert alert-info">
                No inventory data available for the selected filters.
            </div>
            <?php else: ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="inventoryChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Animal Type</th>
                                <th>Count</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventoryByType as $type => $data): ?>
                            <tr>
                                <td><?= htmlspecialchars($type) ?></td>
                                <td><?= $data['count'] ?></td>
                                <td>$<?= number_format($data['value'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td>Total</td>
                                <td><?= array_sum(array_column($inventoryByType, 'count')) ?></td>
                                <td>$<?= number_format($inventoryValue, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Transaction History</h3>
        <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#transactionHistory" aria-expanded="true">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse show" id="transactionHistory">
        <div class="card-body">
            <?php if (empty($transactions)): ?>
            <div class="alert alert-info">
                No transactions found for the selected date range and filters.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="transactions-table">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Transaction</th>
                            <th>Animal</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): 
                            $date = null;
                            $amount = 0;
                            $transactionType = '';
                            
                            if ($transaction['transaction_type'] === 'purchase' && !empty($transaction['date_purchased']) && !empty($transaction['purch_cost'])) {
                                $date = $transaction['date_purchased'];
                                $amount = -floatval($transaction['purch_cost']);
                                $transactionType = 'Purchase';
                            } elseif ($transaction['transaction_type'] === 'sale' && !empty($transaction['date_sold']) && !empty($transaction['sell_price'])) {
                                $date = $transaction['date_sold'];
                                $amount = floatval($transaction['sell_price']);
                                $transactionType = 'Sale';
                            } else {
                                continue; // Skip incomplete transactions
                            }
                        ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($date)) ?></td>
                            <td><?= $transactionType ?></td>
                            <td><?= htmlspecialchars($transaction['name']) ?> (<?= htmlspecialchars($transaction['number']) ?>)</td>
                            <td><?= htmlspecialchars($transaction['type']) ?></td>
                            <td class="<?= $amount >= 0 ? 'text-success' : 'text-danger' ?>">
                                $<?= number_format(abs($amount), 2) ?>
                            </td>
                            <td>
                                <a href="animal_view.php?id=<?= $transaction['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Financial Performance Chart
    const financialCtx = document.getElementById('financialChart');
    
    <?php if (!empty($monthlyData)): ?>
    const monthLabels = [<?php 
        $labels = [];
        foreach ($monthlyData as $month) {
            $labels[] = "'" . $month['month_label'] . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const purchasesData = [<?php 
        $values = [];
        foreach ($monthlyData as $month) {
            $values[] = $month['purchases'];
        }
        echo implode(', ', $values);
    ?>];
    
    const salesData = [<?php 
        $values = [];
        foreach ($monthlyData as $month) {
            $values[] = $month['sales'];
        }
        echo implode(', ', $values);
    ?>];
    
    const profitData = [<?php 
        $values = [];
        foreach ($monthlyData as $month) {
            $values[] = $month['profit'];
        }
        echo implode(', ', $values);
    ?>];
    
    if (financialCtx) {
        new Chart(financialCtx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Sales',
                        data: salesData,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Purchases',
                        data: purchasesData,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Profit',
                        data: profitData,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(13, 110, 253, 1)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount ($)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Type Performance Chart
    const typeCtx = document.getElementById('typePerformanceChart');
    
    <?php if (!empty($purchasesByType) || !empty($salesByType)): ?>
    // Combine all types for display
    const allTypes = [
        <?php 
        $allTypeKeys = array_unique(array_merge(array_keys($purchasesByType), array_keys($salesByType)));
        sort($allTypeKeys);
        foreach ($allTypeKeys as $type) {
            echo "'" . $type . "', ";
        }
        ?>
    ];
    
    const typePurchasesData = [
        <?php 
        foreach ($allTypeKeys as $type) {
            echo isset($purchasesByType[$type]) ? $purchasesByType[$type] : 0;
            echo ", ";
        }
        ?>
    ];
    
    const typeSalesData = [
        <?php 
        foreach ($allTypeKeys as $type) {
            echo isset($salesByType[$type]) ? $salesByType[$type] : 0;
            echo ", ";
        }
        ?>
    ];
    
    if (typeCtx) {
        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: allTypes,
                datasets: [
                    {
                        label: 'Sales',
                        data: typeSalesData,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Purchases',
                        data: typePurchasesData,
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
                        title: {
                            display: true,
                            text: 'Amount ($)'
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Inventory Chart
    const inventoryCtx = document.getElementById('inventoryChart');
    
    <?php if (!empty($inventoryByType)): ?>
    const inventoryLabels = [<?php 
        $labels = [];
        foreach ($inventoryByType as $type => $data) {
            $labels[] = "'" . $type . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const inventoryValues = [<?php 
        $values = [];
        foreach ($inventoryByType as $data) {
            $values[] = $data['value'];
        }
        echo implode(', ', $values);
    ?>];
    
    const inventoryColors = [
        'rgba(25, 135, 84, 0.7)',    // Green
        'rgba(13, 110, 253, 0.7)',   // Blue
        'rgba(255, 193, 7, 0.7)',    // Yellow
        'rgba(220, 53, 69, 0.7)',    // Red
        'rgba(108, 117, 125, 0.7)',  // Gray
        'rgba(111, 66, 193, 0.7)',   // Purple
        'rgba(32, 201, 151, 0.7)',   // Teal
        'rgba(253, 126, 20, 0.7)'    // Orange
    ];
    
    if (inventoryCtx) {
        new Chart(inventoryCtx, {
            type: 'pie',
            data: {
                labels: inventoryLabels,
                datasets: [{
                    data: inventoryValues,
                    backgroundColor: inventoryColors.slice(0, inventoryLabels.length),
                    borderColor: 'white',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Export to CSV functionality
    const exportButton = document.getElementById('exportCSV');
    if (exportButton) {
        exportButton.addEventListener('click', function() {
            // Create CSV content
            let csv = [];
            
            // Add report title and date range
            csv.push(['Farm Financial Report']);
            csv.push(['Date Range: <?= date('M j, Y', strtotime($startDate)) ?> to <?= date('M j, Y', strtotime($endDate)) ?>']);
            csv.push([]);
            
            // Add summary
            csv.push(['Financial Summary']);
            csv.push(['Total Sales', '$<?= number_format($totalSales, 2) ?>']);
            csv.push(['Total Purchases', '$<?= number_format($totalPurchases, 2) ?>']);
            csv.push(['Net Profit', '$<?= number_format($totalProfit, 2) ?>']);
            csv.push(['Inventory Value', '$<?= number_format($inventoryValue, 2) ?>']);
            csv.push([]);
            
            <?php if (!empty($purchasesByType) || !empty($salesByType)): ?>
            // Add performance by type
            csv.push(['Performance by Animal Type']);
            csv.push(['Animal Type', 'Purchases', 'Sales', 'Profit']);
            
            <?php 
            $allTypeKeys = array_unique(array_merge(array_keys($purchasesByType), array_keys($salesByType)));
            sort($allTypeKeys);
            foreach ($allTypeKeys as $type): 
                $purchases = isset($purchasesByType[$type]) ? $purchasesByType[$type] : 0;
                $sales = isset($salesByType[$type]) ? $salesByType[$type] : 0;
                $profit = isset($profitByType[$type]) ? $profitByType[$type] : 0;
            ?>
            csv.push(['<?= $type ?>', '$<?= number_format($purchases, 2) ?>', '$<?= number_format($sales, 2) ?>', '$<?= number_format($profit, 2) ?>']);
            <?php endforeach; ?>
            
            csv.push(['Total', '$<?= number_format($totalPurchases, 2) ?>', '$<?= number_format($totalSales, 2) ?>', '$<?= number_format($totalProfit, 2) ?>']);
            <?php endif; ?>
            csv.push([]);
            
            <?php if (!empty($inventoryByType)): ?>
            // Add inventory value
            csv.push(['Current Inventory Value']);
            csv.push(['Animal Type', 'Count', 'Value']);
            
            <?php foreach ($inventoryByType as $type => $data): ?>
            csv.push(['<?= $type ?>', '<?= $data['count'] ?>', '$<?= number_format($data['value'], 2) ?>']);
            <?php endforeach; ?>
            
            csv.push(['Total', '<?= array_sum(array_column($inventoryByType, 'count')) ?>', '$<?= number_format($inventoryValue, 2) ?>']);
            <?php endif; ?>
            csv.push([]);
            
            <?php if (!empty($transactions)): ?>
            // Add transactions
            csv.push(['Transaction History']);
            csv.push(['Date', 'Transaction', 'Animal', 'Type', 'Amount']);
            
            <?php foreach ($transactions as $transaction): 
                if ($transaction['transaction_type'] === 'purchase' && !empty($transaction['date_purchased']) && !empty($transaction['purch_cost'])) {
                    $date = date('m/d/Y', strtotime($transaction['date_purchased']));
                    $transactionType = 'Purchase';
                    $amount = number_format(floatval($transaction['purch_cost']), 2);
                } elseif ($transaction['transaction_type'] === 'sale' && !empty($transaction['date_sold']) && !empty($transaction['sell_price'])) {
                    $date = date('m/d/Y', strtotime($transaction['date_sold']));
                    $transactionType = 'Sale';
                    $amount = number_format(floatval($transaction['sell_price']), 2);
                } else {
                    continue;
                }
                $animalName = addslashes($transaction['name']);
                $animalNumber = addslashes($transaction['number']);
                $animalType = addslashes($transaction['type']);
            ?>
            csv.push(['<?= $date ?>', '<?= $transactionType ?>', '<?= $animalName ?> (<?= $animalNumber ?>)', '<?= $animalType ?>', '$<?= $amount ?>']);
            <?php endforeach; ?>
            <?php endif; ?>
            
            // Convert array to CSV string
            let csvContent = '';
            csv.forEach(function(row) {
                // Properly escape values with quotes if they contain commas
                const processedRow = row.map(value => {
                    // Check if value contains commas or quotes
                    if (value && (value.includes(',') || value.includes('"'))) {
                        // Escape quotes by doubling them and wrap in quotes
                        return '"' + value.replace(/"/g, '""') + '"';
                    }
                    return value;
                });
                csvContent += processedRow.join(',') + '\n';
            });
            
            // Create download link
            const encodedUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'farm_financial_report_<?= date('Y-m-d') ?>.csv');
            document.body.appendChild(link);
            
            // Trigger download
            link.click();
            
            // Clean up
            document.body.removeChild(link);
        });
    }
});
</script>

<style>
@media print {
    .btn, button, .card-header button, .no-print {
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
    
    .bg-success, .bg-danger, .bg-primary, .bg-info, .bg-secondary {
        background-color: #fff !important;
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
<?php include_once 'includes/mobile_tab_bar.php'; ?>
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