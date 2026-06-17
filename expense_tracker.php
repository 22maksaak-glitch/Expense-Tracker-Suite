<?php
// expense_tracker.php - Трекер расходов на PHP (CLI + веб)
// CLI: php expense_tracker.php --cmd=add --category=Food --amount=12.5 --date=2024-01-15

$dataFile = 'expenses.json';

function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['expenses' => [], 'next_id' => 1];
    }
    $json = file_get_contents($dataFile);
    $data = json_decode($json, true);
    if (!$data) $data = ['expenses' => [], 'next_id' => 1];
    return $data;
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addExpense(&$data, $category, $amount, $date, $description) {
    $id = $data['next_id']++;
    $expense = ['id' => $id, 'category' => $category, 'amount' => $amount, 'date' => $date, 'description' => $description];
    $data['expenses'][] = $expense;
    saveData($data);
    return $expense;
}

function editExpense(&$data, $id, $category, $amount, $date, $description) {
    foreach ($data['expenses'] as &$e) {
        if ($e['id'] == $id) {
            if ($category !== null) $e['category'] = $category;
            if ($amount !== null) $e['amount'] = $amount;
            if ($date !== null) $e['date'] = $date;
            if ($description !== null) $e['description'] = $description;
            saveData($data);
            return true;
        }
    }
    return false;
}

function deleteExpense(&$data, $id) {
    $filtered = array_filter($data['expenses'], function($e) use ($id) { return $e['id'] != $id; });
    if (count($filtered) < count($data['expenses'])) {
        $data['expenses'] = array_values($filtered);
        saveData($data);
        return true;
    }
    return false;
}

function getExpenses($data, $category = null, $dateFrom = null, $dateTo = null, $minAmount = null, $maxAmount = null) {
    $result = $data['expenses'];
    if ($category) $result = array_filter($result, fn($e) => $e['category'] == $category);
    if ($dateFrom) $result = array_filter($result, fn($e) => $e['date'] >= $dateFrom);
    if ($dateTo) $result = array_filter($result, fn($e) => $e['date'] <= $dateTo);
    if ($minAmount !== null) $result = array_filter($result, fn($e) => $e['amount'] >= $minAmount);
    if ($maxAmount !== null) $result = array_filter($result, fn($e) => $e['amount'] <= $maxAmount);
    usort($result, fn($a, $b) => strcmp($a['date'], $b['date']));
    return $result;
}

function getStatistics($data, $category = null, $dateFrom = null, $dateTo = null) {
    $expenses = getExpenses($data, $category, $dateFrom, $dateTo);
    $total = array_sum(array_column($expenses, 'amount'));
    $count = count($expenses);
    $byCategory = [];
    foreach ($expenses as $e) {
        $byCategory[$e['category']] = ($byCategory[$e['category']] ?? 0) + $e['amount'];
    }
    $avg = $count > 0 ? $total / $count : 0;
    return ['total' => $total, 'count' => $count, 'by_category' => $byCategory, 'average' => $avg];
}

function getCategories($data) {
    $cats = array_unique(array_column($data['expenses'], 'category'));
    sort($cats);
    return $cats;
}

// ========== CLI ==========
if (php_sapi_name() === 'cli') {
    $options = getopt("", ["cmd:", "category:", "amount:", "date:", "description:", "id:", "from:", "to:", "min:", "max:"]);
    $cmd = $options['cmd'] ?? null;
    $data = loadData();

    switch ($cmd) {
        case 'add':
            $cat = $options['category'] ?? '';
            $amount = isset($options['amount']) ? (float)$options['amount'] : 0;
            $date = $options['date'] ?? date('Y-m-d');
            $desc = $options['description'] ?? '';
            if ($cat && $amount > 0) {
                $exp = addExpense($data, $cat, $amount, $date, $desc);
                echo "✅ Добавлен расход #{$exp['id']}: {$exp['amount']} {$exp['category']} ({$exp['date']})\n";
            } else {
                echo "Укажите --category и --amount\n";
            }
            break;
        case 'list':
            $category = $options['category'] ?? null;
            $from = $options['from'] ?? null;
            $to = $options['to'] ?? null;
            $min = isset($options['min']) ? (float)$options['min'] : null;
            $max = isset($options['max']) ? (float)$options['max'] : null;
            $list = getExpenses($data, $category, $from, $to, $min, $max);
            if (empty($list)) {
                echo "Нет записей.\n";
            } else {
                $total = array_sum(array_column($list, 'amount'));
                printf("%-4s %-12s %-15s %-10s %s\n", "ID", "Дата", "Категория", "Сумма", "Описание");
                foreach ($list as $e) {
                    printf("%-4d %-12s %-15s %-10.2f %s\n", $e['id'], $e['date'], $e['category'], $e['amount'], $e['description']);
                }
                printf("\nИтого: %.2f\n", $total);
            }
            break;
        case 'stats':
            $category = $options['category'] ?? null;
            $from = $options['from'] ?? null;
            $to = $options['to'] ?? null;
            $stats = getStatistics($data, $category, $from, $to);
            echo "📊 Статистика\n";
            echo "Всего записей: {$stats['count']}\n";
            echo "Общая сумма: " . number_format($stats['total'], 2) . "\n";
            echo "Средний расход: "
