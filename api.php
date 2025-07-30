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
    $sql = "SELECT s.id, s.hitokoto, s.`from`, s.type, c.code as category_code, c.name as category_name 
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
        'type' => $randomSentence['type'],
        'category_code' => $randomSentence['category_code'],
        'category_name' => $randomSentence['category_name']
    ];

    // 添加自定义字段
    if (!empty($_GET['custom'])) {
        $response['custom_field'] = $_GET['custom'];
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