<?php
/**
 * Financial Report Page - FIXED VERSION
 * 
 * This page displays financial information for farm animals,
 * including purchases, sales, and overall farm profitability.
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Process date range
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 year'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$selectedType = isset($_GET['type']) ? $_GET['type'] : null;

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

try {
    // Get all financial transactions (purchases and sales)
    $transactionsQuery = "
        SELECT 
            id, 
            type, 
            name, 
            number, 
            purch_cost, 
            date_purchased, 
            sell_price, 
            date_sold, 
            'purchase' as transaction_type 
        FROM animals 
        WHERE user_id = :user_id 
        AND date_purchased IS NOT NULL 
        AND date_purchased BETWEEN :start_date AND :end_date ";

    if ($selectedType) {
        $transactionsQuery .= "AND type = :type1 ";
    }
    
    $transactionsQuery .= "UNION ALL
        
        SELECT 
            id, 
            type, 
            name, 
            number, 
            purch_cost, 
            date_purchased, 
            sell_price, 
            date_sold, 
            'sale' as transaction_type 
        FROM animals 
        WHERE user_id = :user_id 
        AND date_sold IS NOT NULL 
        AND date_sold BETWEEN :start_date AND :end_date ";
    
    if ($selectedType) {
        $transactionsQuery .= "AND type = :type2 ";
    }
    
    $transactionsQuery .= "ORDER BY date_purchased, date_sold";

    $transactionsStmt = $db->prepare($transactionsQuery);
    $transactionsStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $transactionsStmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
    $transactionsStmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);

    if ($selectedType) {
        $transactionsStmt->bindParam(':type1', $selectedType, PDO::PARAM_STR);
        $transactionsStmt->bindParam(':type2', $selectedType, PDO::PARAM_STR);
    }

    $transactionsStmt->execute();
    $transactions = $transactionsStmt->fetchAll();

    // Prepare monthly data array with all months in range
    $startMonth = new DateTime($startDate);
    $endMonth = new DateTime($endDate);
    $interval = new DateInterval('P1M'); // 1 month interval
    $period = new DatePeriod($startMonth, $interval, $endMonth);

    foreach ($period as $date) {
        $monthKey = $date->format('Y-m');
        $monthlyData[$monthKey] = [
            'purchases' => 0,
            'sales' => 0,
            'profit' => 0,
            'month_label' => $date->format('M Y')
        ];
    }

    // Add current month if not already included
    $currentMonthKey = date('Y-m');
    if (!isset($monthlyData[$currentMonthKey])) {
        $monthlyData[$currentMonthKey] = [
            'purchases' => 0,
            'sales' => 0,
            'profit' => 0,
            'month_label' => date('M Y')
        ];
    }

    // Process transactions to calculate financial metrics
    foreach ($transactions as $transaction) {
        $animalType = $transaction['type'];
        
        // Initialize type data if not exists
        if (!isset($purchasesByType[$animalType])) {
            $purchasesByType[$animalType] = 0;
            $salesByType[$animalType] = 0;
            $profitByType[$animalType] = 0;
        }
        
        if ($transaction['transaction_type'] === 'purchase' && !empty($transaction['purch_cost'])) {
            $cost = floatval($transaction['purch_cost']);
            $totalPurchases += $cost;
            $purchasesByType[$animalType] += $cost;
            
            // Add to monthly data
            $month = date('Y-m', strtotime($transaction['date_purchased']));
            if (isset($monthlyData[$month])) {
                $monthlyData[$month]['purchases'] += $cost;
                $monthlyData[$month]['profit'] -= $cost;
            }
        }
        
        if ($transaction['transaction_type'] === 'sale' && !empty($transaction['sell_price'])) {
            $price = floatval($transaction['sell_price']);
            $totalSales += $price;
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
                $monthlyData[$month]['profit'] += $profit;
            }
        }
    }

    // Sort monthly data by date
    ksort($monthlyData);

    // Get available animal types for filter dropdown
    $typesStmt = $db->prepare("
        SELECT DISTINCT type FROM animals 
        WHERE user_id = :user_id 
        ORDER BY type
    ");
    $typesStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $typesStmt->execute();
    $availableTypes = $typesStmt->fetchAll();

    // Current inventory with costs
    $inventoryQuery = "
        SELECT 
            type, 
            COUNT(*) as count, 
            SUM(CASE WHEN purch_cost IS NOT NULL THEN purch_cost ELSE 0 END) as total_cost,
            SUM(CASE WHEN for_sale = 'Yes' AND sell_price IS NOT NULL THEN sell_price ELSE 0 END) as total_listed
        FROM animals 
        WHERE user_id = :user_id 
        AND status = 'Alive'";
        
    if ($selectedType) {
        $inventoryQuery .= " AND type = :type";
    }
    
    $inventoryQuery .= " GROUP BY type";
    
    $inventoryStmt = $db->prepare($inventoryQuery);
    $inventoryStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    
    if ($selectedType) {
        $inventoryStmt->bindParam(':type', $selectedType, PDO::PARAM_STR);
    }
    
    $inventoryStmt->execute();
    $inventory = $inventoryStmt->fetchAll();

    foreach ($inventory as $item) {
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
    // Log error for debugging
    error_log("Financial Report Error: " . $e->getMessage());
    $_SESSION['alert_message'] = "An error occurred while generating the financial report. Please try again later.";
    $_SESSION['alert_type'] = "danger";
    
    // Uncomment for detailed error display during development
    // echo "<div class='alert alert-danger'>" . $e->getMessage() . "</div>";
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
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="financialChart"></canvas>
                </div>
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
                                <?php foreach ($purchasesByType as $type => $purchases): 
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
    
    // Type Performance Chart
    const typeCtx = document.getElementById('typePerformanceChart');
    
    const typeLabels = [<?php 
        $labels = [];
        foreach ($purchasesByType as $type => $value) {
            $labels[] = "'" . $type . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const typePurchasesData = [<?php 
        $values = [];
        foreach ($purchasesByType as $value) {
            $values[] = $value;
        }
        echo implode(', ', $values);
    ?>];
    
    const typeSalesData = [<?php 
        $values = [];
        foreach ($purchasesByType as $type => $value) {
            $values[] = isset($salesByType[$type]) ? $salesByType[$type] : 0;
        }
        echo implode(', ', $values);
    ?>];
    
    new Chart(typeCtx, {
        type: 'bar',
        data: {
            labels: typeLabels,
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
    
    // Inventory Chart
    const inventoryCtx = document.getElementById('inventoryChart');
    
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
        'rgba(108, 117, 125, 0.7)'   // Gray
    ];
    
    new Chart(inventoryCtx, {
        type: 'pie',
        data: {
            labels: inventoryLabels,
            datasets: [{
                data: inventoryValues,
                backgroundColor: inventoryColors,
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
    
    // Export to CSV functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
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
        
        // Add performance by type
        csv.push(['Performance by Animal Type']);
        csv.push(['Animal Type', 'Purchases', 'Sales', 'Profit']);
        
        <?php foreach ($purchasesByType as $type => $purchases): 
            $sales = isset($salesByType[$type]) ? $salesByType[$type] : 0;
            $profit = isset($profitByType[$type]) ? $profitByType[$type] : 0;
        ?>
        csv.push(['<?= $type ?>', '$<?= number_format($purchases, 2) ?>', '$<?= number_format($sales, 2) ?>', '$<?= number_format($profit, 2) ?>']);
        <?php endforeach; ?>
        
        csv.push(['Total', '$<?= number_format($totalPurchases, 2) ?>', '$<?= number_format($totalSales, 2) ?>', '$<?= number_format($totalProfit, 2) ?>']);
        csv.push([]);
        
        // Add inventory value
        csv.push(['Current Inventory Value']);
        csv.push(['Animal Type', 'Count', 'Value']);
        
        <?php foreach ($inventoryByType as $type => $data): ?>
        csv.push(['<?= $type ?>', '<?= $data['count'] ?>', '$<?= number_format($data['value'], 2) ?>']);
        <?php endforeach; ?>
        
        csv.push(['Total', '<?= array_sum(array_column($inventoryByType, 'count')) ?>', '$<?= number_format($inventoryValue, 2) ?>']);
        csv.push([]);
        
        // Add transactions
        csv.push(['Transaction History']);
        csv.push(['Date', 'Transaction', 'Animal', 'Type', 'Amount']);
        
        <?php 
        foreach ($transactions as $transaction): 
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
            
            $formattedDate = date('m/d/Y', strtotime($date));
            $animalName = addslashes($transaction['name']);
            $animalNumber = addslashes($transaction['number']);
            $animalType = addslashes($transaction['type']);
            $formattedAmount = number_format(abs($amount), 2);
        ?>
        csv.push(['<?= $formattedDate ?>', '<?= $transactionType ?>', '<?= $animalName ?> (<?= $animalNumber ?>)', '<?= $animalType ?>', '$<?= $formattedAmount ?>']);
        <?php endforeach; ?>
        
        // Convert array to CSV string
        let csvContent = '';
        csv.forEach(function(row) {
            csvContent += row.join(',') + '\n';
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