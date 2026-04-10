<?php
/**
 * MySQL 9.6 to MariaDB 10.6 Database Export Tool
 * Interactive UI for database migration
 */

// ===== KONFIGURASI DATABASE =====
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'port' => 3306
];

// ===== AJAX HANDLER =====
if (isset($_POST['action'])) {
    if ($_POST['action'] !== 'download') {
        header('Content-Type: application/json');
    }
    
    try {
        $mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], '', $db_config['port']);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        switch ($_POST['action']) {
            case 'get_databases':
                $result = $mysqli->query("SHOW DATABASES");
                $databases = [];
                while ($row = $result->fetch_row()) {
                    if (!in_array($row[0], ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                        $databases[] = $row[0];
                    }
                }
                echo json_encode(['success' => true, 'databases' => $databases]);
                break;
                
            case 'get_tables':
                $db_name = $mysqli->real_escape_string($_POST['database']);
                $mysqli->select_db($db_name);
                $result = $mysqli->query("SHOW TABLES");
                $tables = [];
                while ($row = $result->fetch_row()) {
                    $count_result = $mysqli->query("SELECT COUNT(*) as cnt FROM `{$row[0]}`");
                    $count = $count_result->fetch_assoc()['cnt'];
                    
                    $status_result = $mysqli->query("SHOW TABLE STATUS LIKE '{$row[0]}'");
                    $status = $status_result->fetch_assoc();
                    $size = $status['Data_length'] + $status['Index_length'];
                    
                    $tables[] = [
                        'name' => $row[0],
                        'rows' => number_format($count),
                        'size' => formatBytes($size)
                    ];
                }
                echo json_encode(['success' => true, 'tables' => $tables]);
                break;
                
            case 'download':
                $database = $_POST['database'];
                $tables = json_decode($_POST['tables'], true);
                $filename = $_POST['filename'];
                $include_data = $_POST['include_data'] === 'true';
                $include_drop = $_POST['include_drop'] === 'true';
                
                $sql_content = exportDatabase($mysqli, $database, $tables, $include_data, $include_drop);
                
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                header('Content-Length: ' . strlen($sql_content));
                echo $sql_content;
                exit; // Stop further execution to return only the file
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===== EXPORT FUNCTION =====
function exportDatabase($mysqli, $database, $tables, $include_data, $include_drop) {
    $mysqli->select_db($database);
    
    $sql = "-- =====================================================\n";
    $sql .= "-- MySQL to MariaDB Database Export\n";
    $sql .= "-- =====================================================\n";
    $sql .= "-- Database: $database\n";
    $sql .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- MySQL Version: " . $mysqli->server_info . "\n";
    $sql .= "-- Tables: " . count($tables) . "\n";
    $sql .= "-- =====================================================\n\n";
    
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "SET AUTOCOMMIT = 0;\n";
    $sql .= "START TRANSACTION;\n";
    $sql .= "SET time_zone = \"+00:00\";\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    // Hapus CREATE DATABASE & USE karena di hosting biasanya sudah ada DB dan nama DB berbeda
    
    foreach ($tables as $table) {
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- Table structure: `$table`\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        
        if ($include_drop) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        }
        
        $result = $mysqli->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $create_table = $row[1];
        
        // MariaDB compatibility fixes
        $create_table = preg_replace(
            '/COLLATE=utf8mb4_0900_ai_ci/',
            'COLLATE=utf8mb4_unicode_ci',
            $create_table
        );
        
        $sql .= $create_table . ";\n\n";
        
        if ($include_data) {
            $result = $mysqli->query("SELECT * FROM `$table`");
            if ($result && $result->num_rows > 0) {
                $sql .= "-- Data for table: `$table`\n";
                $sql .= "INSERT INTO `$table` VALUES\n";
                
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                        }
                    }
                    $rows[] = "(" . implode(", ", $values) . ")";
                }
                
                $sql .= implode(",\n", $rows) . ";\n\n";
            }
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $sql .= "COMMIT;\n";
    
    return $sql;
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL to MariaDB Exporter</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 30px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 6px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 13px;
        }
        
        .content {
            padding: 30px;
        }
        
        .step {
            margin-bottom: 24px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            background: #fafafa;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .step-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #555;
            font-size: 13px;
        }
        
        select, input[type="text"] {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 12px;
            background: white;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
        }
        
        .table-item {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .table-item:hover {
            border-color: #667eea;
            transform: translateY(-1px);
        }
        
        .table-item.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .table-name {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .table-info {
            font-size: 11px;
            opacity: 0.8;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 12px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 12px;
        }
        
        .stat-box {
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 MySQL to MariaDB Exporter</h1>
            <p>Tool migrasi database dari MySQL 9.6 ke MariaDB 10.6</p>
        </div>
        
        <div class="content">
            <div id="alertBox"></div>
            
            <!-- Step 1: Select Database -->
            <div class="step">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div class="step-title">Pilih Database</div>
                </div>
                <div class="form-group">
                    <label for="database">Database yang akan di-export:</label>
                    <select id="database">
                        <option value="">-- Pilih Database --</option>
                    </select>
                </div>
            </div>
            
            <!-- Step 2: Select Tables -->
            <div class="step" id="step2" style="display:none;">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div class="step-title">Pilih Tabel</div>
                </div>
                <div class="btn-group" style="margin-bottom: 12px;">
                    <button type="button" class="btn-secondary" onclick="selectAllTables()">Pilih Semua</button>
                    <button type="button" class="btn-secondary" onclick="deselectAllTables()">Hapus Semua</button>
                </div>
                <div id="tablesGrid" class="tables-grid"></div>
                <div class="stats" id="tableStats" style="display:none;"></div>
            </div>
            
            <!-- Step 3: Export Options -->
            <div class="step" id="step3" style="display:none;">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <div class="step-title">Konfigurasi Export</div>
                </div>
                
                <div class="form-group">
                    <label for="filename">Nama File SQL:</label>
                    <input type="text" id="filename" placeholder="backup_database.sql">
                </div>
                
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="includeData" checked>
                        <span>Include Data</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="includeDrop" checked>
                        <span>Include DROP TABLE</span>
                    </label>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn-primary" onclick="exportDatabase()">
                        🚀 Export Database
                    </button>
                </div>
            </div>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Sedang memproses...</p>
            </div>
        </div>
    </div>

    <script>
        let selectedTables = new Set();
        let allTables = [];
        
        // Load databases on page load
        window.onload = function() {
            loadDatabases();
        };
        
        function showAlert(message, type = 'info') {
            const alertBox = document.getElementById('alertBox');
            alertBox.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => alertBox.innerHTML = '', 5000);
        }
        
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }
        
        async function loadDatabases() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=get_databases'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('database');
                    data.databases.forEach(db => {
                        const option = document.createElement('option');
                        option.value = db;
                        option.textContent = db;
                        select.appendChild(option);
                    });
                } else {
                    showAlert(data.error, 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        }
        
        document.getElementById('database').addEventListener('change', async function() {
            const dbName = this.value;
            if (!dbName) return;
            
            showLoading(true);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=get_tables&database=${encodeURIComponent(dbName)}`
                });
                
                const data = await response.json();
                showLoading(false);
                
                if (data.success) {
                    allTables = data.tables;
                    renderTables(data.tables);
                    document.getElementById('step2').style.display = 'block';
                    
                    // Auto-generate filename
                    const timestamp = new Date().toISOString().slice(0,10).replace(/-/g, '');
                    document.getElementById('filename').value = `${dbName}_${timestamp}.sql`;
                } else {
                    showAlert(data.error, 'error');
                }
            } catch (error) {
                showLoading(false);
                showAlert('Error: ' + error.message, 'error');
            }
        });
        
        function renderTables(tables) {
            const grid = document.getElementById('tablesGrid');
            grid.innerHTML = '';
            
            tables.forEach(table => {
                const div = document.createElement('div');
                div.className = 'table-item';
                div.innerHTML = `
                    <div class="table-name">${table.name}</div>
                    <div class="table-info">${table.rows} rows • ${table.size}</div>
                `;
                div.onclick = () => toggleTable(table.name, div);
                grid.appendChild(div);
            });
        }
        
        function toggleTable(tableName, element) {
            if (selectedTables.has(tableName)) {
                selectedTables.delete(tableName);
                element.classList.remove('selected');
            } else {
                selectedTables.add(tableName);
                element.classList.add('selected');
            }
            
            updateStats();
            
            if (selectedTables.size > 0) {
                document.getElementById('step3').style.display = 'block';
            }
        }
        
        function selectAllTables() {
            allTables.forEach(table => selectedTables.add(table.name));
            document.querySelectorAll('.table-item').forEach(el => el.classList.add('selected'));
            updateStats();
            document.getElementById('step3').style.display = 'block';
        }
        
        function deselectAllTables() {
            selectedTables.clear();
            document.querySelectorAll('.table-item').forEach(el => el.classList.remove('selected'));
            updateStats();
        }
        
        function updateStats() {
            const statsDiv = document.getElementById('tableStats');
            if (selectedTables.size === 0) {
                statsDiv.style.display = 'none';
                return;
            }
            
            const selectedTablesData = allTables.filter(t => selectedTables.has(t.name));
            const totalRows = selectedTablesData.reduce((sum, t) => sum + parseInt(t.rows.replace(/,/g, '')), 0);
            
            statsDiv.style.display = 'grid';
            statsDiv.innerHTML = `
                <div class="stat-box">
                    <div class="stat-value">${selectedTables.size}</div>
                    <div class="stat-label">Tabel Dipilih</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${totalRows.toLocaleString()}</div>
                    <div class="stat-label">Total Rows</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">${allTables.length}</div>
                    <div class="stat-label">Total Tabel</div>
                </div>
            `;
        }
        
        async function exportDatabase() {
            if (selectedTables.size === 0) {
                showAlert('Pilih minimal 1 tabel untuk di-export!', 'error');
                return;
            }
            
            const database = document.getElementById('database').value;
            const filename = document.getElementById('filename').value;
            
            if (!filename) {
                showAlert('Nama file harus diisi!', 'error');
                return;
            }
            
            showLoading(true);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const params = {
                action: 'download',
                database: database,
                tables: JSON.stringify(Array.from(selectedTables)),
                filename: filename,
                include_data: document.getElementById('includeData').checked,
                include_drop: document.getElementById('includeDrop').checked
            };
            
            for (const key in params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            setTimeout(() => {
                showLoading(false);
                showAlert(`✅ File backup (${filename}) sedang didownload ke perangkat Anda.`, 'success');
            }, 1000);
        }
    </script>
</body>
</html>