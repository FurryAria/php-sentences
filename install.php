<?php
// 安装锁定机制
$lockFile = __DIR__ . '/installed.lock';

// 检查是否已安装
if (file_exists($lockFile)) {
    die('系统已安装，如需重新安装，请删除installed.lock文件');
}

// 处理表单提交
$message = '';
$config = require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单提交的数据库信息
    $host = $_POST['host'] ?? $config['host'];
    $dbname = $_POST['dbname'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 更新配置文件
    $configContent = "<?php\nreturn [\n    'host' => '{$host}',\n    'dbname' => '{$dbname}',\n    'username' => '{$username}',\n    'password' => '{$password}',\n    'charset' => 'utf8mb4'\n];\n?>";
    file_put_contents('config.php', $configContent);
    
    try {
        // 连接数据库
        $dsn = "mysql:host={$host};charset={$config['charset']}";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建数据库（如果不存在）
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbname}");
        $pdo->exec("USE {$dbname}");
        
        // 创建表结构（优化版）
        $sql = "CREATE TABLE IF NOT EXISTS sentences (
            id INT PRIMARY KEY AUTO_INCREMENT,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            hitokoto TEXT NOT NULL,
            type VARCHAR(10) NOT NULL,
            `from` VARCHAR(50),
            from_who VARCHAR(50),
            creator VARCHAR(50),
            creator_uid INT,
            reviewer INT,
            commit_from VARCHAR(30),
            created_at DATETIME,
            length INT,
            UNIQUE KEY idx_uuid (uuid),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
        
        // 导入JSON数据
        $sentenceFiles = glob(__DIR__ . '/sentences/*.json');
        $imported = 0;
        $skipped = 0;
        
        // 计算总句子数
        $totalSentences = 0;
        foreach ($sentenceFiles as $file) {
            $jsonContent = file_get_contents($file);
            $tempSentences = json_decode($jsonContent, true);
            if (is_array($tempSentences)) {
                $totalSentences += count($tempSentences);
            }
        }
        
        // 输出进度容器
        echo '<div id="progress-container" style="width: 100%; background-color: #f3f3f3; margin: 20px 0; border-radius: 4px;">
            <div id="progress-bar" style="width: 0%; height: 30px; background-color: #4CAF50; border-radius: 4px; transition: width 0.3s ease;"></div>
        </div>
        <div id="progress-text" style="text-align: center; margin: 10px 0;">准备开始...</div>
        <script>
            function updateProgress(percent, text) {
                document.getElementById("progress-bar").style.width = percent + "%";
                document.getElementById("progress-text").textContent = text;
            }
        </script>';
        ob_flush();
        flush();
        
        foreach ($sentenceFiles as $file) {
            $jsonContent = file_get_contents($file);
            $sentences = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($sentences)) {
                $message .= "无法解析文件: {$file}<br>";
                continue;
            }
            
            foreach ($sentences as $sentence) {
                // 检查是否已存在该uuid
                $stmt = $pdo->prepare("SELECT id FROM sentences WHERE uuid = :uuid");
                $stmt->execute([':uuid' => $sentence['uuid']]);
                if ($stmt->fetch()) {
                    $skipped++;
                    
                    // 更新进度
                    $current = $imported + $skipped;
                    $percent = $totalSentences > 0 ? round(($current / $totalSentences) * 100, 2) : 0;
                    echo "<script>updateProgress($percent, '已处理 $current/$totalSentences 条记录 (导入: $imported, 跳过: $skipped)');</script>";
                    ob_flush();
                    flush();
                    continue;
                }
                
                // 插入数据
                $stmt = $pdo->prepare("INSERT INTO sentences (
                    uuid, hitokoto, type, `from`, from_who, creator, creator_uid,
                    reviewer, commit_from, created_at, length
                ) VALUES (
                    :uuid, :hitokoto, :type, :from, :from_who, :creator, :creator_uid,
                    :reviewer, :commit_from, :created_at, :length
                )");
                
                $stmt->execute([
                    ':uuid' => $sentence['uuid'],
                    ':hitokoto' => $sentence['hitokoto'],
                    ':type' => $sentence['type'],
                    ':from' => $sentence['from'] ?? null,
                    ':from_who' => $sentence['from_who'] ?? null,
                    ':creator' => $sentence['creator'] ?? null,
                    ':creator_uid' => $sentence['creator_uid'] ?? 0,
                    ':reviewer' => $sentence['reviewer'] ?? 0,
                    ':commit_from' => $sentence['commit_from'] ?? null,
                    ':created_at' => ($sentence['created_at'] && is_numeric($sentence['created_at']) && $sentence['created_at'] > 0 && $sentence['created_at'] < 2147483647) ? date('Y-m-d H:i:s', $sentence['created_at']) : '1970-01-01 00:00:00',
                    ':length' => $sentence['length'] ?? 0
                ]);
                
                $imported++;
                    
                    // 更新进度
                    $current = $imported + $skipped;
                    $percent = $totalSentences > 0 ? round(($current / $totalSentences) * 100, 2) : 0;
                    echo "<script>updateProgress($percent, '已处理 $current/$totalSentences 条记录 (导入: $imported, 跳过: $skipped)');</script>";
                    ob_flush();
                    flush();
            }
        }
        
        $message = "安装成功！导入了 {$imported} 条记录，跳过了 {$skipped} 条已存在记录。";
        
        // 更新最终进度
        echo "<script>updateProgress(100, '导入完成！共处理 {$totalSentences} 条记录 (导入: {$imported}, 跳过: {$skipped})');</script>";
        ob_flush();
        flush();
        
        // 创建安装锁定文件
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
    } catch (PDOException $e) {
        $message = "数据库错误: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>一言API - 安装</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .container { background: #f5f5f5; padding: 20px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .message { margin-top: 20px; padding: 10px; border-radius: 4px; }
        .success { background: #dff0d8; color: #3c763d; }
        .error { background: #f2dede; color: #a94442; }
    </style>
</head>
<body>
    <div class="container">
        <h1>一言API 安装</h1>
        <form method="post">
            <div class="form-group">
                <label for="host">数据库主机</label>
                <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($config['host']); ?>" required>
            </div>
            <div class="form-group">
                <label for="dbname">数据库名</label>
                <input type="text" id="dbname" name="dbname" value="<?php echo htmlspecialchars($config['dbname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="username">数据库用户名</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($config['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">数据库密码</label>
                <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($config['password']); ?>">
            </div>
            <button type="submit">开始安装</button>
        </form>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>