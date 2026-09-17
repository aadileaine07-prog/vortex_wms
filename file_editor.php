<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Root directory where vortex_wms is hosted
$baseDir = __DIR__;

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

require_once $baseDir . "/config/database.php";

$currentFile = isset($_GET['file']) ? $_GET['file'] : '';
$fullPath = realpath($baseDir . '/' . $currentFile);

// Security Check: Prevent directory traversal attacks
if ($fullPath && strpos($fullPath, $baseDir) === 0 && is_file($fullPath)) {
    $fileContent = file_get_contents($fullPath);
} else {
    $fullPath = '';
    $fileContent = '';
}

// Handle File Save Request
$saveMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_path'])) {
    $targetFile = realpath($baseDir . '/' . $_POST['file_path']);
    if ($targetFile && strpos($targetFile, $baseDir) === 0 && is_file($targetFile)) {
        $newContent = $_POST['file_content'];
        if (file_put_contents($targetFile, $newContent) !== false) {
            $saveMessage = "File saved successfully!";
            $fileContent = $newContent;
            $fullPath = $targetFile;
            $currentFile = $_POST['file_path'];
        } else {
            $saveMessage = "Error: Failed to save file.";
        }
    }
}

// Recursive function to scan all folders and files across the entire project
function getDirectoryTree($dir, $baseDir) {
    $result = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.git' || $item === 'node_modules' || $item === '.DS_Store') continue;
        $path = $dir . '/' . $item;
        $relativePath = str_replace($baseDir . '/', '', $path);
        if (is_dir($path)) {
            $result[$item] = getDirectoryTree($path, $baseDir);
        } else {
            $result[] = $relativePath;
        }
    }
    return $result;
}

include $baseDir . "/includes/header.php";
?>

<div class="container-fluid py-4">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-code text-warning me-2"></i>Global Vortex WMS Code Editor
            </h2>
            <p class="text-muted mb-0">Browse and modify any file across all project modules instantly</p>
        </div>
        <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if (!empty($saveMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i><?= $saveMessage; ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Sidebar: Complete Project Directory Tree -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-light border-0 py-3 px-4 rounded-top-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Project Directory</h5>
                </div>
                <div class="card-body p-3 overflow-auto" style="max-height: 650px; font-size: 13px;">
                    <ul class="list-unstyled ps-0 mb-0">
                        <?php 
                        function renderTree($dirArray, $baseDir, $currentSelected) {
                            foreach ($dirArray as $key => $value) {
                                if (is_array($value)) {
                                    echo '<li class="mb-1 fw-bold text-secondary"><i class="fa-solid fa-folder text-warning me-1"></i> ' . htmlspecialchars($key) . '<ul class="list-unstyled ps-3 border-start ms-2 mt-1">';
                                    renderTree($value, $baseDir, $currentSelected);
                                    echo '</ul></li>';
                                } else {
                                    $isSel = ($value === $currentSelected) ? 'bg-primary text-white fw-bold rounded px-2 py-1' : 'text-dark text-decoration-none d-block py-1 px-2 rounded hover-bg';
                                    echo '<li><a href="file_editor.php?file=' . urlencode($value) . '" class="' . $isSel . '"><i class="fa-solid fa-file-code text-muted me-1"></i> ' . htmlspecialchars(basename($value)) . '</a></li>';
                                }
                            }
                        }
                        renderTree(getDirectoryTree($baseDir, $baseDir), $baseDir, $currentFile);
                        ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Side: Advanced Code Editor Area -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-light border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark text-truncate" style="max-width: 450px;">
                        <i class="fa-solid fa-file-pen me-2 text-success"></i><?= $currentFile ? htmlspecialchars($currentFile) : 'Select any project file to edit'; ?>
                    </h5>
                    <?php if ($fullPath): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">Active File Writable</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <?php if ($fullPath): ?>
                        <form method="POST">
                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($currentFile); ?>">
                            <div class="mb-3">
                                <textarea name="file_content" class="form-control font-monospace border-2 bg-dark text-light p-3 rounded-4" rows="24" style="font-size: 13px; line-height: 1.5;" required><?= htmlspecialchars($fileContent); ?></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success px-5 fw-bold rounded-pill shadow-sm">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes to Server
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted my-5">
                            <i class="fa-solid fa-laptop-code fs-1 d-block mb-3 text-secondary opacity-50"></i>
                            <h5 class="fw-bold text-dark">No File Selected</h5>
                            <p class="small mb-0">Choose any folder (Modules, Config, Includes, etc.) from the left tree to inspect or modify its code instantly.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include $baseDir . "/includes/footer.php"; ?>