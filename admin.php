<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Подключение к базе данных
$dbc = mysqli_connect('localhost', 'root', '', 'form');
if (!$dbc) {
    die("Ошибка соединения с базой данных");
}

// Получение всех данных из таблицы 'form'
$result = mysqli_query($dbc, "SELECT * FROM form");
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}
mysqli_close($dbc);
$host = 'localhost';
$db   = 'form';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

$result_tr = $conn->query("SELECT * FROM form_two");
$records_by_trainer = [];
if ($result_tr) {
    while($row_tr = $result_tr->fetch_assoc()) {
        $height_m = $row_tr['height'] / 100;
        $weight = $row_tr['weight'];
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
                'row' => $row_tr,
                'bmi' => $bmi,
                'category' => $category_name,
                'trainer_id' => $trainer_id
            ];
        }
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
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Таблица данных и графики</title>
    <!-- Шрифты -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Ваши стили без изменений */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f4f8;
            margin: 0;
            padding: 30px;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 2em;
            color: #2c3e50;
        }
        #chartContainer, #domainChartContainer, #monthlyChartContainer {
            width: 90%;
            max-width: 800px;
            margin: 30px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-top: 40px;
        }
        .filters, .controls {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        select, #search {
            padding: 10px 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1em;
        }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s;
        }
        button:hover {
            opacity: 0.9;
        }
        button#sortAsc { background-color: #3498db; color: #fff; }
        button#sortDesc { background-color: #2980b9; color: #fff; }
        button#reset { background-color: #95a5a6; color: #fff; }
        button#exportTxt { background-color: #27ae60; color: #fff; }
        button#exportCsv { background-color: #16a085; color: #fff; }
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        thead {
            background-color: #2980b9;
            color: #fff;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        tbody tr {
            transition: background-color 0.3s, transform 0.2s;
        }
        tbody tr:hover {
            background-color: #ecf0f1;
            transform: translateY(-2px);
        }
        button.edit-btn {
            background-color: #f39c12;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        button.delete-btn {
            background-color: #e74c3c;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h1>Данные из таблицы и графики заявок по дням</h1>

<!-- Фильтр по времени -->
<div class="filters">
    <label for="timeFilter">Фильтр по времени:</label>
    <select id="timeFilter">
        <option value="all">Все</option>
        <option value="today">Сегодня</option>
        <option value="week">Эта неделя</option>
        <option value="month">Этот месяц</option>
    </select>
</div>

<!-- График заявок по дням -->
<div id="chartContainer">
    <canvas id="applicationsChart" height="200"></canvas>
</div>

<!-- Распределение по email-доменам -->
<h2 style="text-align:center;">Распределение по email-доменам</h2>
<div id="domainChartContainer">
    <canvas id="domainChart" height="200"></canvas>
</div>

<!-- Заявки по месяцам за последний год -->
<h2 style="text-align:center;">Заявки по месяцам за последний год</h2>
<div id="monthlyChartContainer">
    <canvas id="monthlyChart" height="200"></canvas>
</div>

<!-- Контрольные кнопки и поиск -->
<div class="controls">
    <button id="sortAsc">По возрастанию</button>
    <button id="sortDesc">По убыванию</button>
    <button id="reset">Сброс</button>
    <input type="text" id="search" placeholder="Поиск..." />
    <button id="exportTxt">Экспорт в TXT</button>
    <button id="exportCsv">Экспорт в CSV</button>
</div>

<!-- Таблица данных -->
<div class="table-container">
    <table>
        <thead>
        <tr>
            <th>Имя</th>
            <th>Фамилия</th>
            <th>Email</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody id="table-body"></tbody>
    </table>
</div>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Заявки и BMI по тренерам и таблицы</title>
    <style>
        /* Общие стили */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f9;
            margin: 20px;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        /* Вкладки тренеров */
        .tabs {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 20px;
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
        /* Таблица тренеров */
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
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: #f1f1f1;
        }
        /* Графики */
        .chart-container {
            width: 90%;
            max-width: 700px;
            margin: 20px auto;
        }
        canvas {
            width: 100% !important;
            height: auto !important;
        }
        /* Общие стили для раздела заявок */
        /* Можно оставить так или добавить по необходимости */
    </style>
</head>
<body>
<h1>Заявки и BMI по тренерам и таблицы</h1>

<!-- Вкладки тренеров -->
<div class="tabs" id="trainerTabs">
    <?php
    $firstTrainerTab = true;
    foreach ($records_by_trainer as $trainer_id => $records):
        $active_class = $firstTrainerTab ? 'active' : '';
        $firstTrainerTab = false;
        ?>
        <button class="tab-button <?= $active_class ?>" data-tab="tab<?= $trainer_id ?>">
            <?= htmlspecialchars(isset($trainer_names[$trainer_id]) ? $trainer_names[$trainer_id] : 'Неизвестный') ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Контейнеры с таблицами и графиками для тренеров -->
<?php
$firstTrainerContent = true;
foreach ($records_by_trainer as $trainer_id => $records):
    $active_class = $firstTrainerContent ? 'active' : '';
    $firstTrainerContent = false;
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

<!-- Вернуться на главную -->
<div style="margin-top:20px; text-align:center;">
    <a href="index.html" style="
        display:inline-block;
        padding:10px 20px;
        background-color:#3498db;
        color:#fff;
        text-decoration:none;
        border-radius:5px;
        font-weight:600;
        transition:background-color 0.3s;
    " onmouseover="this.style.backgroundColor='#2980b9'" onmouseout="this.style.backgroundColor='#3498db'">
        Вернуться на главную
    </a>
</div>

<!-- Скрипты -->
<script>

    // Переданные из PHP данные
    const data = <?php echo json_encode($data); ?>;
    let filteredData = [...data];

    const tableBody = document.getElementById('table-body');
    const searchInput = document.getElementById('search');
    const timeFilter = document.getElementById('timeFilter');

    let ctxApplications = document.getElementById('applicationsChart').getContext('2d');
    let ctxDomain = document.getElementById('domainChart').getContext('2d');
    let ctxMonthly = document.getElementById('monthlyChart').getContext('2d');

    let applicationsChart, domainChart, monthlyChart;

    // Вспомогательные функции для дат
    function isToday(dateStr) {
        const date = new Date(dateStr);
        const today = new Date();
        return date.getFullYear() === today.getFullYear() &&
            date.getMonth() === today.getMonth() &&
            date.getDate() === today.getDate();
    }
    function isThisWeek(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay());
        const lastDay = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay() + 6);
        return date >= firstDay && date <= lastDay;
    }
    function isThisMonth(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
    }
    function getDateCounts(dataArray) {
        const counts = {};
        dataArray.forEach(row => {
            if (row.created_at) {
                const date = new Date(row.created_at);
                const dayStr = date.getFullYear() + '-' +
                    String(date.getMonth() + 1).padStart(2, '0') + '-' +
                    String(date.getDate()).padStart(2, '0');
                counts[dayStr] = (counts[dayStr] || 0) + 1;
            }
        });
        return counts;
    }

    // Обновление графика заявок по дням
    function updateApplicationsChart() {
        const counts = getDateCounts(filteredData);
        const labels = Object.keys(counts).sort();
        const dataPoints = Object.values(counts);

        if (applicationsChart) applicationsChart.destroy();

        applicationsChart = new Chart(ctxApplications, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: 'Заявок', data: dataPoints, backgroundColor: 'rgba(52, 152, 219, 0.7)', borderColor: 'rgba(41, 128, 185, 1)', borderWidth: 1 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision:0 } }
                }
            }
        });
    }

    // Восстановление данных по фильтрам
    function applyFilters() {
        let tempData = [...data];

        // Фильтр по времени
        const filterVal = timeFilter.value;
        if (filterVal !== 'all') {
            tempData = tempData.filter(row => {
                if (!row.created_at) return false;
                if (filterVal === 'today') return isToday(row.created_at);
                if (filterVal === 'week') return isThisWeek(row.created_at);
                if (filterVal === 'month') return isThisMonth(row.created_at);
                return true;
            });
        }

        // Поиск
        const query = searchInput.value.toLowerCase();
        if (query) {
            tempData = tempData.filter(row =>
                (row.first_name && row.first_name.toLowerCase().includes(query)) ||
                (row.last_name && row.last_name.toLowerCase().includes(query)) ||
                (row.email && row.email.toLowerCase().includes(query))
            );
        }

        filteredData = tempData;
        renderTable();
        updateApplicationsChart();
        renderDomainChart();
        renderMonthlyChart();
    }

    // Отрисовка таблицы
    function renderTable() {
        tableBody.innerHTML = '';
        filteredData.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.dataset.index = index;
            tr.dataset.id = row.id;

            tr.innerHTML = `
            <td class="first_name">${row.first_name || ''}</td>
            <td class="last_name">${row.last_name || ''}</td>
            <td class="email">${row.email || ''}</td>
            <td>${row.created_at || ''}</td>
            <td>
                <button class="edit-btn">Редактировать</button>
                <button class="delete-btn">Удалить</button>
            </td>
        `;
            tableBody.appendChild(tr);
        });
        attachHandlers();
    }

    // Назначение событий для кнопок редактирования и удаления
    function attachHandlers() {
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.onclick = () => {
                const tr = btn.closest('tr');
                const id = tr.dataset.id;
                const tds = tr.querySelectorAll('td.first_name, td.last_name, td.email');

                if (tds[0].isContentEditable) {
                    // Сохраняем
                    const newFirstName = tds[0].textContent.trim();
                    const newLastName = tds[1].textContent.trim();
                    const newEmail = tds[2].textContent.trim();

                    fetch('update_record.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${encodeURIComponent(id)}&first_name=${encodeURIComponent(newFirstName)}&last_name=${encodeURIComponent(newLastName)}&email=${encodeURIComponent(newEmail)}`
                    }).then(res => {
                        if (res.ok) {
                            data.forEach(d => {
                                if (d.id == id) {
                                    d.first_name = newFirstName;
                                    d.last_name = newLastName;
                                    d.email = newEmail;
                                }
                            });
                            tds.forEach(td => td.contentEditable = false);
                            applyFilters();
                        } else {
                            alert('Ошибка при сохранении');
                        }
                    });
                } else {
                    tds.forEach(td => td.contentEditable = true);
                    btn.textContent = 'Сохранить';
                }
            };
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.onclick = () => {
                const tr = btn.closest('tr');
                const id = tr.dataset.id;
                fetch('delete_record.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(id)}`
                }).then(res => {
                    if (res.ok) {
                        // Удаляем из данных
                        data.forEach((d, i) => {
                            if (d.id == id) data.splice(i,1);
                        });
                        applyFilters();
                    } else {
                        alert('Ошибка при удалении');
                    }
                });
            };
        });
    }

    // График по email-доменам
    function renderDomainChart() {
        const domainCounts = {};
        filteredData.forEach(row => {
            if (row.email) {
                const domain = row.email.split('@')[1]?.toLowerCase() || 'неизвестен';
                domainCounts[domain] = (domainCounts[domain] || 0) + 1;
            }
        });
        const labels = Object.keys(domainCounts);
        const dataPoints = Object.values(domainCounts);

        if (domainChart) domainChart.destroy();

        domainChart = new Chart(ctxDomain, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{ data: dataPoints, backgroundColor: labels.map(() => `hsl(${Math.random()*360},70%,70%)`) }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // Заявки по месяцам за последний год
    function renderMonthlyChart() {
        const now = new Date();
        const currentYear = now.getFullYear();
        const counts = {};
        for (let m=0; m<12; m++) {
            const key = `${currentYear}-${(m+1).toString().padStart(2,'0')}`;
            counts[key] = 0;
        }
        filteredData.forEach(row => {
            if (row.created_at) {
                const date = new Date(row.created_at);
                if (date.getFullYear() === currentYear) {
                    const key = `${date.getFullYear()}-${(date.getMonth()+1).toString().padStart(2,'0')}`;
                    if (counts.hasOwnProperty(key)) {
                        counts[key]++;
                    }
                }
            }
        });
        const labels = Object.keys(counts);
        const dataPoints = Object.values(counts);

        if (monthlyChart) monthlyChart.destroy();

        monthlyChart = new Chart(ctxMonthly, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{ label: 'Заявки', data: dataPoints, fill: true, backgroundColor: 'rgba(52, 152, 219, 0.2)', borderColor: 'rgba(41, 128, 185, 1)', tension: 0.4 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision:0 } } }
            }
        });
    }

    // Изначально отображаем таблицу и графики
    renderTable();
    updateApplicationsChart();
    renderDomainChart();
    renderMonthlyChart();

    // Обработчики фильтров
    searchInput.addEventListener('input', () => { applyFilters(); });
    document.getElementById('timeFilter').addEventListener('change', () => { applyFilters(); });
    document.getElementById('sortAsc').addEventListener('click', () => {
        filteredData.sort((a, b) => (a.first_name || '').toLowerCase().localeCompare((b.first_name || '').toLowerCase()));
        renderTable();
    });
    document.getElementById('sortDesc').addEventListener('click', () => {
        filteredData.sort((a, b) => (b.first_name || '').toLowerCase().localeCompare((a.first_name || '').toLowerCase()));
        renderTable();
    });
    document.getElementById('reset').addEventListener('click', () => {
        document.getElementById('search').value = '';
        document.getElementById('timeFilter').value = 'all';
        filteredData = [...data];
        renderTable();
        updateApplicationsChart();
        renderDomainChart();
        renderMonthlyChart();
    });

    // Экспорт в TXT
    document.getElementById('exportTxt').onclick = () => {
        let txt = 'Имя\tФамилия\tEmail\tДата\n';
        filteredData.forEach(r => {
            txt += `${r.first_name || ''}\t${r.last_name || ''}\t${r.email || ''}\t${r.created_at || ''}\n`;
        });
        const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'данные.txt';
        a.click();
        URL.revokeObjectURL(url);
    };

    // Экспорт в CSV с BOM для корректного отображения русских букв в Excel
    document.getElementById('exportCsv').onclick = () => {
        const BOM = '\uFEFF'; // BOM для UTF-8
        const rows = [];
        // Заголовки с русскими именами
        rows.push(['Имя', 'Фамилия', 'Email', 'Дата'].map(f => `"${f}"`).join(';'));
        filteredData.forEach(r => {
            const f = [
                r.first_name || '',
                r.last_name || '',
                r.email || '',
                r.created_at || ''
            ];
            rows.push(f.map(f => `"${f.replace(/"/g, '""')}"`).join(';'));
        });
        const csvContent = BOM + rows.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'данные.csv'; // Название файла
        a.click();
        URL.revokeObjectURL(url);
    };

    // Остальные функции для редактирования и удаления остались без изменений
    function handleEditDelete() {
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.onclick = () => {
                const tr = btn.closest('tr');
                const id = tr.dataset.id;
                const tds = tr.querySelectorAll('td.first_name, td.last_name, td.email');

                if (tds[0].isContentEditable) {
                    const newFirstName = tds[0].textContent.trim();
                    const newLastName = tds[1].textContent.trim();
                    const newEmail = tds[2].textContent.trim();

                    fetch('update_record.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${encodeURIComponent(id)}&first_name=${encodeURIComponent(newFirstName)}&last_name=${encodeURIComponent(newLastName)}&email=${encodeURIComponent(newEmail)}`
                    }).then(res => {
                        if (res.ok) {
                            data.forEach(d => {
                                if (d.id == id) {
                                    d.first_name = newFirstName;
                                    d.last_name = newLastName;
                                    d.email = newEmail;
                                }
                            });
                            tds.forEach(td => td.contentEditable = false);
                            handleEditDelete();
                            applyFilters();
                        } else {
                            alert('Ошибка при сохранении');
                        }
                    });
                } else {
                    tds.forEach(td => td.contentEditable = true);
                    btn.textContent = 'Сохранить';
                }
            };
        });
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.onclick = () => {
                const tr = btn.closest('tr');
                const id = tr.dataset.id;
                fetch('delete_record.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(id)}`
                }).then(res => {
                    if (res.ok) {
                        data.forEach((d, i) => {
                            if (d.id == id) data.splice(i, 1);
                        });
                        applyFilters();
                    } else {
                        alert('Ошибка при удалении');
                    }
                });
            };
        });
    }

    function initHandlers() {
        handleEditDelete();
    }
    initHandlers();

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
        $ages_unique = array_unique($ages);
        sort($ages_unique);
        $ageCounts = array_count_values($ages);
        $agesLabelsJson = json_encode(array_map('strval', $ages_unique));
        $agesCountsJson = json_encode(array_map('intval', array_map(function($k) use ($ageCounts) { return $ageCounts[$k]; }, $ages_unique)));
        ?>
        const ctxAge<?= $trainer_id ?> = document.getElementById('ageChart<?= $trainer_id ?>').getContext('2d');
        new Chart(ctxAge<?= $trainer_id ?>, {
            type: 'bar',
            data: {
                labels: <?= $agesLabelsJson ?>,
                datasets: [{
                    label: 'Количество',
                    data: <?= $agesCountsJson ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero:true }
                }
            }
        });
        <?php endforeach; ?>
    });

</script>
</body>
</html>

