<?php
/**
 * Lineage Report Page (Completion)
 */
// Continue from previous code...
?>
                    <td>
                        <span class="badge rounded-pill bg-<?= getStatusBadgeClass($animal['status']) ?>">
                            <?= htmlspecialchars($animal['status']) ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $offspringCount ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="report_lineage.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary" title="View Lineage">
                                <i class="bi bi-diagram-3"></i>
                            </a>
                            <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-outline-success" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($potentialStock)): ?>
<!-- Potential Breeding Stock -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h3 class="mb-0">Potential Breeding Stock</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            These animals are alive but have no recorded offspring. You can establish lineage connections by editing these animals or adding new animals and selecting them as parents.
        </div>
        
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Number</th>
                    <th>Type</th>
                    <th>Breed</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($potentialStock as $animal): 
                    // Calculate age
                    $age = "Unknown";
                    if (!empty($animal['dob'])) {
                        $dobDate = new DateTime($animal['dob']);
                        $now = new DateTime();
                        $interval = $now->diff($dobDate);
                        
                        if ($interval->y > 0) {
                            $age = $interval->y . " year" . ($interval->y > 1 ? "s" : "");
                        } else if ($interval->m > 0) {
                            $age = $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                        } else {
                            $age = $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                        }
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($animal['name']) ?></td>
                    <td><?= htmlspecialchars($animal['number']) ?></td>
                    <td><?= htmlspecialchars($animal['type']) ?></td>
                    <td><?= htmlspecialchars($animal['breed']) ?></td>
                    <td><?= htmlspecialchars($animal['gender']) ?></td>
                    <td><?= $age ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="report_lineage.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary" title="View Lineage">
                                <i class="bi bi-diagram-3"></i>
                            </a>
                            <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-outline-success" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="animal_edit.php?id=<?= $animal['id'] ?>" class="btn btn-outline-warning" title="Edit Animal">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lineage Help Modal -->
<div class="modal fade" id="lineageHelp" tabindex="-1" aria-labelledby="lineageHelpLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lineageHelpLabel">Lineage Management Help</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>Understanding Lineage in FarmApp</h5>
                <p>The lineage system helps you track family relationships and breeding history for your animals.</p>
                
                <h6>How to Establish Lineage Connections:</h6>
                <ol>
                    <li>When adding or editing an animal, select its dam (mother) and sire (father) from the dropdown menus.</li>
                    <li>Only animals of the appropriate gender will be shown as options (females as dams, males as sires).</li>
                    <li>Once connections are established, you can view family trees and track offspring.</li>
                </ol>
                
                <h6>Lineage Report Features:</h6>
                <ul>
                    <li><strong>Ancestry Tree:</strong> Shows parents and grandparents for a selected animal.</li>
                    <li><strong>Offspring List:</strong> Shows all recorded offspring for a selected animal.</li>
                    <li><strong>Breeding Stock:</strong> Animals that have offspring recorded in the system.</li>
                    <li><strong>Potential Breeding Stock:</strong> Animals that are alive but have no recorded offspring.</li>
                </ul>
                
                <h6>Lineage Best Practices:</h6>
                <ul>
                    <li>Record parent information when adding new animals to build your lineage database.</li>
                    <li>For purchased animals, record as much lineage information as available.</li>
                    <li>Use the lineage report to make breeding decisions and avoid inbreeding.</li>
                    <li>Track performance across generations to improve your breeding program.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

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