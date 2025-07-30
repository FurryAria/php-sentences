<?php
header('Content-Type: application/json; charset=utf-8');

// 加载数据库配置
$config = require 'config.php';

try {
    // 连接数据库
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 随机获取一条句子
    $stmt = $pdo->query('SELECT id, hitokoto, `from`, type FROM sentences ORDER BY RAND() LIMIT 1');
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
        'type' => $randomSentence['type']
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>