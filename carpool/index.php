<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в Carpool</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
</head>
<body class="auth-body">

<div class="container px-3">
    <div class="auth-card mx-auto">
        <div class="app-logo">CARPOOL</div>
        
        <div class="auth-toggle">
            <ul class="nav nav-pills nav-fill" id="authTabs">
                <li class="nav-item"><a class="nav-link active" onclick="setMode('login')">Вход</a></li>
                <li class="nav-item"><a class="nav-link" onclick="setMode('register')">Регистрация</a></li>
            </ul>
        </div>

        <form id="authForm">
            <input type="hidden" name="type" id="authType" value="login">
            
            <div id="nameBlock" style="display:none;">
                <input type="text" name="name" class="form-control" placeholder="Ваше Имя">
            </div>

            <input type="tel" id="phoneInput" name="phone" class="form-control" value="+375" maxlength="13" required>
            <input type="password" name="password" class="form-control" placeholder="Пароль" required>

            <button type="submit" class="btn btn-main">Поехали</button>
        </form>
        <div id="msg" class="mt-3 text-center text-danger small fw-bold"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    const phoneInput = document.getElementById('phoneInput');
    phoneInput.addEventListener('input', function(e) {
        if (!this.value.startsWith('+375')) {
            this.value = '+375' + this.value.replace('+375', '');
        }
    });

    function setMode(mode) {
        $('#authType').val(mode);
        $('.nav-link').removeClass('active');
        if (mode === 'login') {
            $('[onclick="setMode(\'login\')"]').addClass('active');
            $('#nameBlock').slideUp();
        } else {
            $('[onclick="setMode(\'register\')"]').addClass('active');
            $('#nameBlock').slideDown();
        }
    }

    $('#authForm').submit(function(e){
        e.preventDefault();
        $.post('api.php?action=auth', $(this).serialize(), function(res){
            if(res.status === 'success'){
                if(res.role === 'admin') window.location.href = 'admin.php';
                else if(res.role === 'driver') window.location.href = 'driver.php';
                else window.location.href = 'dashboard.php';
            } else {
                $('#msg').text(res.message);
            }
        }, 'json');
    });
</script>
</body>
</html>