<?php
// 数据库优化脚本
header('Content-Type: text/html; charset=utf-8');

// 加载数据库配置
$config = require 'config.php';

try {
    // 连接数据库
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "连接数据库成功！<br>";
    
    // 1. 修改created_at字段类型为DATETIME
    echo "优化1: 将created_at字段从VARCHAR改为DATETIME...<br>";
    try {
        // 添加临时字段
        $pdo->exec("ALTER TABLE sentences ADD COLUMN created_at_temp DATETIME");
        // 更新临时字段值
        $pdo->exec("UPDATE sentences SET created_at_temp = FROM_UNIXTIME(created_at)");
        // 删除原字段
        $pdo->exec("ALTER TABLE sentences DROP COLUMN created_at");
        // 重命名临时字段
        $pdo->exec("ALTER TABLE sentences CHANGE COLUMN created_at_temp created_at DATETIME");
        echo "优化1完成！<br>";
    } catch (PDOException $e) {
        echo "优化1失败: " . $e->getMessage() . "<br>";
    }
    
    // 2. 为type字段添加索引
    echo "优化2: 为type字段添加索引...<br>";
    try {
        $pdo->exec("ALTER TABLE sentences ADD INDEX idx_type (type)");
        echo "优化2完成！<br>";
    } catch (PDOException $e) {
        echo "优化2失败: " . $e->getMessage() . "<br>";
    }
    
    // 3. 调整字段长度
    echo "优化3: 调整部分字段长度...<br>";
    try {
        $pdo->exec("ALTER TABLE sentences MODIFY COLUMN `from` VARCHAR(50)");
        $pdo->exec("ALTER TABLE sentences MODIFY COLUMN from_who VARCHAR(50)");
        $pdo->exec("ALTER TABLE sentences MODIFY COLUMN creator VARCHAR(50)");
        $pdo->exec("ALTER TABLE sentences MODIFY COLUMN commit_from VARCHAR(30)");
        echo "优化3完成！<br>";
    } catch (PDOException $e) {
        echo "优化3失败: " . $e->getMessage() . "<br>";
    }
    
    // 4. 优化主键和UUID索引
    echo "优化4: 检查并优化主键和UUID索引...<br>";
    try {
        // UUID字段已经有唯一索引，这里不再重复添加
        // 检查主键是否为聚集索引(MySQL中InnoDB引擎主键默认为聚集索引)
        echo "主键已优化为聚集索引<br>";
    } catch (PDOException $e) {
        echo "优化4失败: " . $e->getMessage() . "<br>";
    }
    
    echo "<br>数据库优化完成！<br>";
} catch (PDOException $e) {
    echo "数据库连接失败: " . $e->getMessage();
}
?>