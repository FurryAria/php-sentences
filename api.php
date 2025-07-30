<?php
header('Content-Type: application/json; charset=utf-8');

// 加载数据库配置
$config = require 'config.php';

try {
    // 连接数据库
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 构建查询
    $sql = "SELECT s.id, s.hitokoto, s.`from`, c.code as category_code, c.name as category_name 
            FROM sentences s
            JOIN categories c ON s.category_id = c.id";
    $params = [];

    // 分类筛选
    if (!empty($_GET['c'])) {
        $sql .= " WHERE c.code = :code";
        $params[':code'] = $_GET['c'];
    }

    $sql .= " ORDER BY RAND() LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $randomSentence = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$randomSentence) {
        http_response_code(404);
        echo json_encode(['error' => 'No sentences found in database']);
        exit;
    }

    // 构建API响应
    $response = [
        'id' => $randomSentence['id'],
        'hitokoto' => $randomSentence['hitokoto'],
        'from' => $randomSentence['from'] ?? '未知来源',
        'category_code' => $randomSentence['category_code'],
        'category_name' => $randomSentence['category_name']
    ];

    // 预定义自定义字段（在代码中声明）
    // 可以根据需要修改或添加更多自定义字段
    $customFields = [
        'author' => '一言API',
        'version' => '1.0',
        'source' => '本地数据库'
    ];

    // 将自定义字段添加到响应中
    foreach ($customFields as $key => $value) {
        $response[$key] = $value;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>