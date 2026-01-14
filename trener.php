<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'trainer') {
    header('Location: login.php');
    exit;
}
// Далее код страницы тренера


// Подключение к базе данных
$host = 'localhost';
$db   = 'form';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

$result = $conn->query("SELECT * FROM form_two");
if (!$result) {
    die("Ошибка запроса: " . $conn->error);
}

$records_by_trainer = [];
while($row = $result->fetch_assoc()) {
    $height_m = $row['height'] / 100;
    $weight = $row['weight'];
    if ($height_m > 0) {
        $bmi = $weight / ($height_m * $height_m);
        $bmi = round($bmi, 2);
    } else {
        $bmi = 'N/A';
    }

    if (is_numeric($bmi)) {
        $stmt = $conn->prepare("
            SELECT c.category_name, t.name AS trainer_name, t.id AS trainer_id
            FROM bmi_categories c
            LEFT JOIN trainer_bmi_category c2 ON c.id = c2.bmi_category_id
            LEFT JOIN trainers t ON c2.trainer_id = t.id
            WHERE (? >= c.min_bmi AND (? < c.max_bmi OR c.max_bmi IS NULL))
        ");
        $stmt->bind_param("dd", $bmi, $bmi);
        $stmt->execute();
        $res_cat = $stmt->get_result();
        if ($row_category = $res_cat->fetch_assoc()) {
            $category_name = $row_category['category_name'];
            $trainer_id = $row_category['trainer_id'];
        } else {
            $category_name = 'Не определена';
            $trainer_id = null;
        }
        $stmt->close();
    } else {
        $category_name = 'Недоступна';
        $trainer_id = null;
    }

    if ($trainer_id !== null) {
        if (!isset($records_by_trainer[$trainer_id])) {
            $records_by_trainer[$trainer_id] = [];
        }
        $records_by_trainer[$trainer_id][] = [
            'row' => $row,
            'bmi' => $bmi,
            'category' => $category_name,
            'trainer_id' => $trainer_id
        ];
    }
}
ksort($records_by_trainer);
$trainer_names = [];
if (!empty($records_by_trainer)) {
    $ids_str = implode(',', array_map('intval', array_keys($records_by_trainer)));
    $trainers_result = $conn->query("SELECT id, name FROM trainers WHERE id IN ($ids_str)");
    while ($trainer_row = $trainers_result->fetch_assoc()) {
        $trainer_names[$trainer_row['id']] = $trainer_row['name'];
    }
    $trainers_result->free();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Заявки и BMI по тренерам</title>
    <style>
        /* ваш стиль, как ранее */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f9;
            margin: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .tabs {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
            justify-content: center;
        }
        .tab-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            margin: 2px;
            border-radius: 4px 4px 0 0;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .tab-button:hover {
            background-color: #45a049;
        }
        .tab-button.active {
            background-color: #357a38;
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        table {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto 30px auto;
            border-collapse: collapse;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
            background-color: #fff;
        }
        thead {
            background-color: #4CAF50;
            color: white;
        }
        th, td {
            padding: 15px;
            text-align: left;
        }
        th {
            font-weight: 600;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn {
            display: inline-block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .chart-container {
            width: 90%;
            max-width: 700px;
            margin: 20px auto;
        }
        canvas {
            width: 100% !important;
            height: auto !important;
        }
    </style>
</head>
<body>
<h1>Заявки и BMI по тренерам</h1>

<div class="tabs" id="trainerTabs">
    <?php
    $first = true;
    foreach ($records_by_trainer as $trainer_id => $records):
        $active_class = $first ? 'active' : '';
        $first = false;
        ?>
        <button class="tab-button <?= $active_class ?>" data-tab="tab<?= $trainer_id ?>">
            <?= htmlspecialchars(isset($trainer_names[$trainer_id]) ? $trainer_names[$trainer_id] : 'Неизвестный') ?>
        </button>
    <?php endforeach; ?>
</div>

<?php
$firstTab = true;
foreach ($records_by_trainer as $trainer_id => $records):
    $active_class = $firstTab ? 'active' : '';
    $firstTab = false;

    $trainer_counters[$trainer_id] = 0;
    ?>
    <div class="tab-content <?= $active_class ?>" id="tab<?= $trainer_id ?>">
        <!-- Таблица -->
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>ID</th>
                <th>Рост (cm)</th>
                <th>Вес (kg)</th>
                <th>Возраст</th>
                <th>Пол</th>
                <th>Телефон</th>
                <th>BMI</th>
                <th>Категория BMI</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($records as $record):
                $row = $record['row'];
                $bmi = $record['bmi'];
                $category = $record['category'];
                $trainer_counters[$trainer_id]++;
                $local_id = $trainer_counters[$trainer_id];
                ?>
                <tr>
                    <td><?= $local_id ?></td>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['height']) ?></td>
                    <td><?= htmlspecialchars($row['weight']) ?></td>
                    <td><?= htmlspecialchars($row['age']) ?></td>
                    <td><?= htmlspecialchars($row['gender']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars($bmi) ?></td>
                    <td><?= htmlspecialchars($category) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <!-- График распределения возрастов -->
        <div class="chart-container">
            <h3>Распределение возрастов</h3>
            <canvas id="ageChart<?= $trainer_id ?>"></canvas>
        </div>
    </div>
<?php endforeach; ?>

<a href="index.html" class="btn">На главную</a>

<script>
    // переключение вкладок
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                contents.forEach(c => c.classList.remove('active'));
                const target = tab.getAttribute('data-tab');
                document.getElementById(target).classList.add('active');
            });
        });
    });
</script>

<!-- Подключаем Chart.js через CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php
        foreach ($records_by_trainer as $trainer_id => $records):
        $ages = [];
        foreach ($records as $rec) {
            $row = $rec['row'];
            $ages[] = (int)$row['age'];
        }
        $ageLabels = array_unique($ages);
        sort($ageLabels);
        $ageCounts = array_count_values($ages);
        $ageLabelsJson = json_encode(array_map('strval', $ageLabels));
        $ageCountsJson = json_encode(array_map('intval', array_map(function($k) use ($ageCounts) { return $ageCounts[$k]; }, $ageLabels)));
        ?>
        const ctxAge<?= $trainer_id ?> = document.getElementById('ageChart<?= $trainer_id ?>').getContext('2d');
        new Chart(ctxAge<?= $trainer_id ?>, {
            type: 'bar',
            data: {
                labels: <?= $ageLabelsJson ?>,
                datasets: [{
                    label: 'Количество',
                    data: <?= $ageCountsJson ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero:true
                    }
                }
            }
        });
        <?php endforeach; ?>
    });
</script>

<?php
$result->free();
$conn->close();
?>
</body>
</html>