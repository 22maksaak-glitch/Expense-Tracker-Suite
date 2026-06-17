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
                  if (!empty($stats['by_category'])) {
                echo "По категориям:\n";
                arsort($stats['by_category']);
                foreach ($stats['by_category'] as $cat => $amount) {
                    $percent = $stats['total'] > 0 ? ($amount / $stats['total']) * 100 : 0;
                    echo "  $cat: " . number_format($amount, 2) . " (" . number_format($percent, 1) . "%)\n";
                }
            }
            break;
        case 'delete':
            $id = isset($options['id']) ? (int)$options['id'] : 0;
            if ($id && deleteExpense($data, $id)) {
                echo "✅ Расход #$id удалён\n";
            } else {
                echo "❌ Расход #$id не найден\n";
            }
            break;
        case 'edit':
            $id = isset($options['id']) ? (int)$options['id'] : 0;
            $category = $options['category'] ?? null;
            $amount = isset($options['amount']) ? (float)$options['amount'] : null;
            $date = $options['date'] ?? null;
            $desc = $options['description'] ?? null;
            if ($id && editExpense($data, $id, $category, $amount, $date, $desc)) {
                echo "✅ Расход #$id обновлён\n";
            } else {
                echo "❌ Расход #$id не найден\n";
            }
            break;
        case 'categories':
            $cats = getCategories($data);
            if (empty($cats)) echo "Нет категорий\n";
            else echo "Категории: " . implode(", ", $cats) . "\n";
            break;
        default:
            // интерактивный режим
            interactiveMode($data);
            break;
    }
    exit;
}

// ========== ИНТЕРАКТИВНЫЙ РЕЖИМ ==========
function interactiveMode(&$data) {
    while (true) {
        echo "\n💰 Трекер расходов (интерактивный)\n";
        echo "1. Добавить расход\n";
        echo "2. Показать расходы (с фильтром)\n";
        echo "3. Статистика\n";
        echo "4. Редактировать\n";
        echo "5. Удалить\n";
        echo "6. Список категорий\n";
        echo "0. Выход\n";
        echo "Выберите действие: ";
        $choice = trim(fgets(STDIN));
        switch ($choice) {
            case '0': return;
            case '1':
                echo "Категория: ";
                $cat = trim(fgets(STDIN));
                if (!$cat) { echo "Категория обязательна\n"; break; }
                echo "Сумма: ";
                $amt = (float)trim(fgets(STDIN));
                if ($amt <= 0) { echo "Неверная сумма\n"; break; }
                echo "Дата (ГГГГ-ММ-ДД, Enter сегодня): ";
                $date = trim(fgets(STDIN));
                if (!$date) $date = date('Y-m-d');
                echo "Описание (необязательно): ";
                $desc = trim(fgets(STDIN));
                $exp = addExpense($data, $cat, $amt, $date, $desc);
                echo "✅ Добавлен расход #{$exp['id']}\n";
                break;
            case '2':
                echo "Категория (Enter пропустить): ";
                $cat = trim(fgets(STDIN));
                if ($cat === '') $cat = null;
                echo "Дата от (Enter пропустить): ";
                $from = trim(fgets(STDIN));
                if ($from === '') $from = null;
                echo "Дата до (Enter пропустить): ";
                $to = trim(fgets(STDIN));
                if ($to === '') $to = null;
                $list = getExpenses($data, $cat, $from, $to);
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
            case '3':
                echo "Категория (Enter пропустить): ";
                $cat = trim(fgets(STDIN));
                if ($cat === '') $cat = null;
                echo "Дата от (Enter пропустить): ";
                $from = trim(fgets(STDIN));
                if ($from === '') $from = null;
                echo "Дата до (Enter пропустить): ";
                $to = trim(fgets(STDIN));
                if ($to === '') $to = null;
                $stats = getStatistics($data, $cat, $from, $to);
                echo "📊 Статистика\n";
                echo "Всего записей: {$stats['count']}\n";
                echo "Общая сумма: " . number_format($stats['total'], 2) . "\n";
                echo "Средний расход: " . number_format($stats['average'], 2) . "\n";
                if (!empty($stats['by_category'])) {
                    echo "По категориям:\n";
                    arsort($stats['by_category']);
                    foreach ($stats['by_category'] as $cat => $amount) {
                        $percent = $stats['total'] > 0 ? ($amount / $stats['total']) * 100 : 0;
                        echo "  $cat: " . number_format($amount, 2) . " (" . number_format($percent, 1) . "%)\n";
                    }
                }
                break;
            case '4':
                echo "ID расхода: ";
                $id = (int)trim(fgets(STDIN));
                $found = null;
                foreach ($data['expenses'] as $e) {
                    if ($e['id'] == $id) { $found = $e; break; }
                }
                if (!$found) { echo "Запись не найдена\n"; break; }
                echo "Оставьте пустым, чтобы не менять.\n";
                echo "Категория ({$found['category']}): ";
                $newCat = trim(fgets(STDIN));
                if ($newCat === '') $newCat = null;
                echo "Сумма ({$found['amount']}): ";
                $newAmtStr = trim(fgets(STDIN));
                $newAmt = ($newAmtStr === '') ? null : (float)$newAmtStr;
                echo "Дата ({$found['date']}): ";
                $newDate = trim(fgets(STDIN));
                if ($newDate === '') $newDate = null;
                echo "Описание ({$found['description']}): ";
                $newDesc = trim(fgets(STDIN));
                if ($newDesc === '') $newDesc = null;
                if (editExpense($data, $id, $newCat, $newAmt, $newDate, $newDesc)) {
                    echo "✅ Обновлено\n";
                } else {
                    echo "❌ Ошибка обновления\n";
                }
                break;
            case '5':
                echo "ID для удаления: ";
                $id = (int)trim(fgets(STDIN));
                if (deleteExpense($data, $id)) {
                    echo "✅ Удалено\n";
                } else {
                    echo "❌ Не найдено\n";
                }
                break;
            case '6':
                $cats = getCategories($data);
                if (empty($cats)) echo "Нет категорий\n";
                else echo "Категории: " . implode(", ", $cats) . "\n";
                break;
            default:
                echo "Неверный выбор\n";
        }
    }
}

// ========== ВЕБ-ИНТЕРФЕЙС ==========
if (php_sapi_name() !== 'cli') {
    $data = loadData();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>💰 Трекер расходов (PHP)</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; background: #f4f7fb; margin: 20px; }
            .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #2c3e50; color: white; }
            .form-row { margin: 8px 0; }
            .form-row label { display: inline-block; width: 100px; }
            input, select, button { padding: 6px; margin: 2px; }
            button { background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; }
            .result { background: #e8f5e9; padding: 10px; border-radius: 8px; margin-top: 10px; }
            .stats { margin-top: 20px; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>💰 Трекер расходов</h1>
        <h3>Добавить расход</h3>
        <form method="GET" action="">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <label>Категория:</label>
                <input type="text" name="category" required>
            </div>
            <div class="form-row">
                <label>Сумма:</label>
                <input type="number" step="0.01" name="amount" required>
            </div>
            <div class="form-row">
                <label>Дата:</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-row">
                <label>Описание:</label>
                <input type="text" name="description">
            </div>
            <button type="submit">➕ Добавить</button>
        </form>

        <h3>Фильтр</h3>
        <form method="GET" action="">
            <div class="form-row">
                <label>Категория:</label>
                <input type="text" name="filter_cat" value="<?= $_GET['filter_cat'] ?? '' ?>">
            </div>
            <div class="form-row">
                <label>Дата от:</label>
                <input type="date" name="filter_from" value="<?= $_GET['filter_from'] ?? '' ?>">
            </div>
            <div class="form-row">
                <label>до:</label>
                <input type="date" name="filter_to" value="<?= $_GET['filter_to'] ?? '' ?>">
            </div>
            <button type="submit" name="action" value="list">Применить</button>
            <a href="?">Сбросить</a>
        </form>

        <?php
        // Обработка веб-запросов
        $action = $_GET['action'] ?? null;
        if ($action === 'add' && isset($_GET['category']) && isset($_GET['amount'])) {
            $cat = $_GET['category'];
            $amount = (float)$_GET['amount'];
            $date = $_GET['date'] ?? date('Y-m-d');
            $desc = $_GET['description'] ?? '';
            if ($cat && $amount > 0) {
                addExpense($data, $cat, $amount, $date, $desc);
                echo "<div class='result'>✅ Добавлен расход</div>";
            }
        }

        // Отображение списка
        $filterCat = $_GET['filter_cat'] ?? null;
        $filterFrom = $_GET['filter_from'] ?? null;
        $filterTo = $_GET['filter_to'] ?? null;
        if ($filterCat === '') $filterCat = null;
        if ($filterFrom === '') $filterFrom = null;
        if ($filterTo === '') $filterTo = null;
        $list = getExpenses($data, $filterCat, $filterFrom, $filterTo);
        if (!empty($list)) {
            $total = array_sum(array_column($list, 'amount'));
            echo "<h3>Расходы</h3>";
            echo "<table><tr><th>ID</th><th>Дата</th><th>Категория</th><th>Сумма</th><th>Описание</th><th>Действия</th></tr>";
            foreach ($list as $e) {
                echo "<tr>";
                echo "<td>{$e['id']}</td>";
                echo "<td>{$e['date']}</td>";
                echo "<td>{$e['category']}</td>";
                echo "<td>" . number_format($e['amount'], 2) . "</td>";
                echo "<td>{$e['description']}</td>";
                echo "<td><a href='?action=delete&id={$e['id']}'>🗑</a></td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p><strong>Итого: " . number_format($total, 2) . "</strong></p>";
        } else {
            echo "<p>Нет записей.</p>";
        }

        // Статистика
        $stats = getStatistics($data);
        echo "<div class='stats'>";
        echo "<h3>📊 Статистика</h3>";
        echo "<p>Всего записей: {$stats['count']}</p>";
        echo "<p>Общая сумма: " . number_format($stats['total'], 2) . "</p>";
        echo "<p>Средний расход: " . number_format($stats['average'], 2) . "</p>";
        if (!empty($stats['by_category'])) {
            echo "<p>По категориям:</p><ul>";
            arsort($stats['by_category']);
            foreach ($stats['by_category'] as $cat => $amount) {
                $percent = $stats['total'] > 0 ? ($amount / $stats['total']) * 100 : 0;
                echo "<li>$cat: " . number_format($amount, 2) . " (" . number_format($percent, 1) . "%)</li>";
            }
            echo "</ul>";
        }
        echo "</div>";

        // Обработка удаления
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            if (deleteExpense($data, $id)) {
                echo "<div class='result'>✅ Удалено</div>";
                // Перенаправляем, чтобы избежать повторного удаления
                header("Location: ?");
                exit;
            }
        }
        ?>
    </div>
    </body>
    </html>
    <?php
}

