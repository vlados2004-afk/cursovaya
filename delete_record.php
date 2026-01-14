<?php
// delete_record.php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id= isset($_POST['id']) ? $_POST['id'] : '';

    if (!$id) {
        echo json_encode(['status'=>'error','message'=>'Нет ID']);
        exit;
    }

    $db=mysqli_connect('localhost','root','','form');
    if (!$db) { echo json_encode(['status'=>'error','message'=>'Ошибка соединения']); exit; }

    $stmt=mysqli_prepare($db,"DELETE FROM form WHERE id=?");
    mysqli_stmt_bind_param($stmt,'i',$id);
    $res=mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($db);
    if ($res) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Ошибка удаления']);
    }
}
?>