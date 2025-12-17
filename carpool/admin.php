<?php require 'db.php'; 
if($_SESSION['role'] != 'admin') header("Location: index.php");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Админ Панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🚕 Управление Водителями</h2>
            <a href="index.php" class="btn btn-outline-danger">Выход</a>
        </div>

        <div class="card p-4 shadow-sm mb-4">
            <h5>Добавить нового водителя</h5>
            <form id="addDriverForm">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="ФИО" required></div>
                    <div class="col-md-4"><input type="text" name="phone" class="form-control" placeholder="+375..." value="+375" required></div>
                    <div class="col-md-4"><input type="text" name="password" class="form-control" placeholder="Пароль" required></div>
                    <div class="col-md-4"><input type="text" name="car_model" class="form-control" placeholder="Марка (Kia Rio)" required></div>
                    <div class="col-md-4"><input type="text" name="car_color" class="form-control" placeholder="Цвет (Белый)" required></div>
                    <div class="col-md-4"><input type="text" name="car_plate" class="form-control" placeholder="Номер (1234 AB-7)" required></div>
                </div>
                <button type="submit" class="btn btn-success mt-3 w-100">Создать водителя</button>
            </form>
        </div>

        <div class="card p-4">
            <h5>Список водителей</h5>
            <table class="table table-striped align-middle">
                <thead><tr><th>Имя</th><th>Телефон</th><th>Авто</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM users WHERE role='driver'");
                    while($row = $res->fetch_assoc()){
                        echo "<tr>
                            <td>{$row['name']}</td>
                            <td>{$row['phone']}</td>
                            <td>{$row['car_color']} {$row['car_model']} ({$row['car_plate']})</td>
                            <td>
                                <button class='btn btn-sm btn-primary' onclick='editDriver({$row['id']})'>✏️</button>
                                <button class='btn btn-sm btn-danger' onclick='deleteDriver({$row['id']})'>🗑️</button>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

   
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Редактировать</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="text" name="name" id="edit_name" class="form-control mb-2" placeholder="Имя">
                        <input type="text" name="phone" id="edit_phone" class="form-control mb-2" placeholder="Телефон">
                        <input type="text" name="car_model" id="edit_model" class="form-control mb-2" placeholder="Модель">
                        <input type="text" name="car_color" id="edit_color" class="form-control mb-2" placeholder="Цвет">
                        <input type="text" name="car_plate" id="edit_plate" class="form-control mb-2" placeholder="Номер">
                        <button type="submit" class="btn btn-primary w-100">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $('#addDriverForm').submit(function(e){
            e.preventDefault();
            $.post('api.php?action=add_driver', $(this).serialize(), function(res){
                if(res.status==='success') location.reload(); else alert(res.message);
            }, 'json');
        });

        function deleteDriver(id) {
            if(confirm('Удалить водителя?')) {
                $.post('api.php?action=delete_driver', {id:id}, function(){ location.reload(); }, 'json');
            }
        }

        function editDriver(id) {
            $.getJSON('api.php?action=get_driver_info', {id:id}, function(data){
                $('#edit_id').val(data.id);
                $('#edit_name').val(data.name);
                $('#edit_phone').val(data.phone);
                $('#edit_model').val(data.car_model);
                $('#edit_color').val(data.car_color);
                $('#edit_plate').val(data.car_plate);
                new bootstrap.Modal(document.getElementById('editModal')).show();
            });
        }

        $('#editForm').submit(function(e){
            e.preventDefault();
            $.post('api.php?action=edit_driver', $(this).serialize(), function(){ location.reload(); }, 'json');
        });
    </script>
</body>
</html>