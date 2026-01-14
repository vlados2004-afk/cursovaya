if ($a)
{ <?php
// update_record.php
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
        $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
        $email = isset($_POST['email']) ? $_POST['email'] : '';

        if (!$id || !$first_name || !$last_name || !$email) {
            echo json_encode(['status' => 'error', 'message' => 'Недостаточно данных']);
            exit;
        }

        $db = mysqli_connect('localhost', 'root', '', 'form');
        if (!$db) {
            echo json_encode(['status' => 'error', 'message' => 'Ошибка соединения']);
            exit;
        }

        $stmt = mysqli_prepare($db, "UPDATE form SET first_name=?, last_name=?, email=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssi', $first_name, $last_name, $email, $id);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($db);
        if ($res) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ошибка обновления']);
        }
    }
    ?>}