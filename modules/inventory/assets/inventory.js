/**
 * ============================================================================
 * VORTEX WMS - Inventory Management Core JavaScript (v3.5)
 * ============================================================================
 * Handlers: Live Filtering, Dynamic Bin Fetcher, Stock Recalculator, CSV Export
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize all event listeners
    initInventoryFilters();
    initAdjustmentCalculator();
    initWarehouseBinLoader();
});

/**
 * 1. MULTI-CONDITION INVENTORY TABLE FILTER
 * Handles real-time search by Keyword, Warehouse, and Stock Health Status
 */
function initInventoryFilters() {
    const searchInput = document.getElementById('searchInput');
    const warehouseSelect = document.getElementById('warehouseSelect');
    const statusSelect = document.getElementById('statusSelect');
    const table = document.getElementById('inventoryTable');

    if (!table) return;

    function applyFilter() {
        const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const warehouseVal = warehouseSelect ? warehouseSelect.value.toLowerCase().trim() : '';
        const statusVal = statusSelect ? statusSelect.value.toLowerCase().trim() : '';
        const rows = table.querySelectorAll('tbody tr');

        let visibleCount = 0;

        rows.forEach(row => {
            // Ignore placeholder / empty rows
            if (row.querySelector('td[colspan]')) return;

            const rowText = row.innerText.toLowerCase();
            const whCell = row.querySelector('.wh-cell');
            const whText = whCell ? whCell.innerText.toLowerCase() : '';
            
            const statusCell = row.querySelector('.status-cell');
            const statusText = statusCell ? (statusCell.getAttribute('data-status') || statusCell.innerText).toLowerCase() : '';

            const matchSearch = (searchVal === '' || rowText.includes(searchVal));
            const matchWarehouse = (warehouseVal === '' || whText.includes(warehouseVal));
            const matchStatus = (statusVal === '' || statusText.includes(statusVal));

            if (matchSearch && matchWarehouse && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Toggle No Records Found Message
        let emptyNotice = document.getElementById('inventoryEmptyNotice');
        if (visibleCount === 0) {
            if (!emptyNotice) {
                emptyNotice = document.createElement('tr');
                emptyNotice.id = 'inventoryEmptyNotice';
                emptyNotice.innerHTML = `<td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-magnifying-glass me-2"></i>No matching stock items found.</td>`;
                table.querySelector('tbody').appendChild(emptyNotice);
            }
            emptyNotice.style.display = '';
        } else if (emptyNotice) {
            emptyNotice.style.display = 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('keyup', applyFilter);
    if (warehouseSelect) warehouseSelect.addEventListener('change', applyFilter);
    if (statusSelect) statusSelect.addEventListener('change', applyFilter);
}

/**
 * 2. LIVE STOCK ADJUSTMENT REAL-TIME CALCULATOR
 * Validates stock deductions and previews live balance before form submission
 */
function initAdjustmentCalculator() {
    const inventorySelect   = document.getElementById('inventory_id');
    const typeSelect        = document.getElementById('adjustment_type');
    const quantityInput     = document.getElementById('quantity');
    const warehouseInput    = document.getElementById('warehouse');
    const binInput          = document.getElementById('bin_location');
    const availableQtyInput = document.getElementById('available_qty');
    const newBalancePreview = document.getElementById('newBalancePreview');
    const saveBtn           = document.getElementById('saveBtn');
    const qtyError          = document.getElementById('qtyError');

    if (!inventorySelect || !quantityInput) return;

    let currentStock = 0;

    function recalculateBalance() {
        const type = typeSelect ? typeSelect.value : 'Increase';
        const qty = parseInt(quantityInput.value) || 0;

        if (type === 'Decrease' && qty > currentStock) {
            quantityInput.classList.add('is-invalid');
            if (qtyError) qtyError.style.display = 'block';
            if (saveBtn) saveBtn.disabled = true;

            if (newBalancePreview) {
                newBalancePreview.innerText = 'Invalid (Below 0)';
                newBalancePreview.className = 'fs-4 fw-bold font-monospace text-danger';
            }
        } else {
            quantityInput.classList.remove('is-invalid');
            if (qtyError) qtyError.style.display = 'none';
            if (saveBtn) saveBtn.disabled = false;

            const finalBalance = (type === 'Increase') ? (currentStock + qty) : (currentStock - qty);
            if (newBalancePreview) {
                newBalancePreview.innerText = `${finalBalance} Units`;
                newBalancePreview.className = 'fs-4 fw-bold font-monospace text-primary';
            }
        }
    }

    inventorySelect.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (warehouseInput) warehouseInput.value = option.getAttribute('data-wh') || '-';
        if (binInput) binInput.value = option.getAttribute('data-bin') || '-';
        
        currentStock = parseInt(option.getAttribute('data-qty')) || 0;
        if (availableQtyInput) availableQtyInput.value = `${currentStock} Units`;

        recalculateBalance();
    });

    if (typeSelect) typeSelect.addEventListener('change', recalculateBalance);
    quantityInput.addEventListener('input', recalculateBalance);
}

/**
 * 3. DYNAMIC WAREHOUSE BIN AJAX LOADER
 * Fetches real-time available/empty bins for `add.php` and `edit.php`
 */
function initWarehouseBinLoader() {
    const warehouseSelect = document.getElementById('warehouseSelector') || document.getElementById('warehouseSelect');
    const binSelect = document.getElementById('binSelector') || document.getElementById('binSelect');
    const binHelp = document.getElementById('binStatusHelp');

    if (!warehouseSelect || !binSelect) return;

    window.loadWarehouseBins = function (warehouseId) {
        if (!warehouseId || warehouseId <= 0) {
            binSelect.innerHTML = '<option value="">-- First Select Warehouse Above --</option>';
            if (binHelp) binHelp.innerHTML = 'Free storage slots are auto-calculated';
            return;
        }

        binSelect.innerHTML = '<option value="">Loading available bins...</option>';

        fetch(`get_empty_bins.php?warehouse_id=${encodeURIComponent(warehouseId)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(bins => {
                binSelect.innerHTML = '';
                if (bins && bins.length > 0) {
                    bins.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.bin_code;
                        opt.textContent = `${b.bin_code} [${b.zone} | ${b.available_space} Units Left]`;
                        binSelect.appendChild(opt);
                    });
                    if (binHelp) binHelp.innerHTML = `<span class="text-success font-monospace">✔ ${bins.length} Storage Coordinates Available</span>`;
                } else {
                    binSelect.innerHTML = '<option value="">No Active Bins Available</option>';
                    if (binHelp) binHelp.innerHTML = `<span class="text-danger">⚠️ All bins full or no bins found in facility.</span>`;
                }
            })
            .catch(error => {
                console.error('Error fetching bins:', error);
                binSelect.innerHTML = '<option value="">Error Loading Coordinates</option>';
                if (binHelp) binHelp.innerHTML = `<span class="text-danger">Failed to load bins.</span>`;
            });
    };
}

/**
 * 4. UNIVERSAL INVENTORY CSV EXPORTER
 * Exports the currently visible table rows directly to a .csv spreadsheet
 */
function exportInventoryToCSV(filename = null) {
    const table = document.getElementById('inventoryTable') || document.getElementById('adjustmentTable');
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        // Skip hidden rows and empty notices
        if (row.style.display === 'none' || row.id === 'inventoryEmptyNotice') return;

        const cols = row.querySelectorAll('th, td');
        let rowData = [];

        cols.forEach((col, index) => {
            // Ignore Action buttons column
            if (index === cols.length - 1 && (col.innerText.includes('Actions') || col.querySelector('.btn'))) return;

            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
            text = text.replace(/"/g, '""'); // Escape double quotes
            rowData.push(`"${text}"`);
        });

        if (rowData.length > 0) {
            csv.push(rowData.join(','));
        }
    });

    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    const fileTitle = filename || `WMS_Inventory_Ledger_${new Date().toISOString().slice(0, 10)}.csv`;
    link.href = URL.createObjectURL(blob);
    link.download = fileTitle;
    link.style.display = 'none';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}