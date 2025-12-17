<?php
require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

function createRouteJson($points) { return json_encode($points, JSON_UNESCAPED_UNICODE); }

function getDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}


if ($action == 'auth') {
    $type = $_POST['type']; $phone = trim($_POST['phone']); $password = $_POST['password'];
    if ($type == 'register') {
        $name = trim($_POST['name']);
        $check = $conn->query("SELECT id FROM users WHERE phone='$phone'");
        if ($check->num_rows > 0) { echo json_encode(['status'=>'error', 'message'=>'Номер занят']); exit; }
        $stmt = $conn->prepare("INSERT INTO users (phone, password, name, role) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param("sss", $phone, $password, $name);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id; $_SESSION['role'] = 'user'; $_SESSION['name'] = $name;
            echo json_encode(['status'=>'success', 'role'=>'user']);
        }
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE phone=?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if ($row['password'] == $password) {
                $_SESSION['user_id'] = $row['id']; $_SESSION['role'] = $row['role']; $_SESSION['name'] = $row['name'];
                echo json_encode(['status'=>'success', 'role'=>$row['role']]);
            } else echo json_encode(['status'=>'error', 'message'=>'Неверный пароль']);
        } else echo json_encode(['status'=>'error', 'message'=>'Пользователь не найден']);
    }
}


if ($action == 'add_driver') {
    if ($_SESSION['role'] != 'admin') die();
    $stmt = $conn->prepare("INSERT INTO users (phone, password, name, role, car_model, car_color, car_plate) VALUES (?, ?, ?, 'driver', ?, ?, ?)");
    $stmt->bind_param("ssssss", $_POST['phone'], $_POST['password'], $_POST['name'], $_POST['car_model'], $_POST['car_color'], $_POST['car_plate']);
    if($stmt->execute()) echo json_encode(['status'=>'success']); else echo json_encode(['status'=>'error']);
}


if ($action == 'create_trip') {
    $uid = $_SESSION['user_id'];
    $slat = $_POST['start_lat']; $slng = $_POST['start_lng']; $elat = $_POST['end_lat']; $elng = $_POST['end_lng'];
    $dist = getDistance($slat, $slng, $elat, $elng);
    $price = round(3.00 + ($dist * 0.60), 2);
    $dur = max(5, round(($dist / 40) * 60));

    $stmt = $conn->prepare("INSERT INTO trips (user_id, start_lat, start_lng, end_lat, end_lng, price, duration_min, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'searching')");
    $stmt->bind_param("idddddi", $uid, $slat, $slng, $elat, $elng, $price, $dur);
    $stmt->execute();
    $trip_id = $conn->insert_id;

    $sql = "SELECT * FROM trips WHERE status='searching' AND id != $trip_id";
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) {
        $dist_s = getDistance($slat, $slng, $row['start_lat'], $row['start_lng']);
        $dist_e = getDistance($elat, $elng, $row['end_lat'], $row['end_lng']);
        if ($dist_s <= 2 && $dist_e <= 7) {
            $match_id = uniqid('m');
            $route = [
                ['lat'=>$slat, 'lng'=>$slng, 'name'=>'Точка сбора'],
                ['lat'=>$row['end_lat'], 'lng'=>$row['end_lng'], 'name'=>'Остановка 1'],
                ['lat'=>$elat, 'lng'=>$elng, 'name'=>'Остановка 2']
            ];
            $route_json = createRouteJson($route);
            $new_price = round($price * 0.7, 2); $new_price2 = round($row['price'] * 0.7, 2);
            
            $conn->query("UPDATE trips SET status='waiting_payment', match_id='$match_id', price=$new_price, route_json='$route_json' WHERE id=$trip_id");
            $conn->query("UPDATE trips SET status='waiting_payment', match_id='$match_id', price=$new_price2, route_json='$route_json' WHERE id={$row['id']}");
            break;
        }
    }
    echo json_encode(['status' => 'success']);
}

if ($action == 'go_alone') {
    $uid = $_SESSION['user_id'];
    $res = $conn->query("SELECT * FROM trips WHERE user_id=$uid AND status='searching' ORDER BY id DESC LIMIT 1");
    if($row = $res->fetch_assoc()) {
        $route = [['lat'=>$row['start_lat'], 'lng'=>$row['start_lng'], 'name'=>'Старт'], ['lat'=>$row['end_lat'], 'lng'=>$row['end_lng'], 'name'=>'Финиш']];
        $route_json = createRouteJson($route);
        $conn->query("UPDATE trips SET status='waiting_payment', route_json='$route_json' WHERE id={$row['id']}");
        echo json_encode(['status'=>'success']);
    } else echo json_encode(['status'=>'error']);
}


if ($action == 'pay') {
    $trip_id = intval($_POST['trip_id']);
    $conn->query("UPDATE trips SET payment_status='paid' WHERE id=$trip_id");
    
    $trip = $conn->query("SELECT match_id FROM trips WHERE id=$trip_id")->fetch_assoc();
    if ($trip['match_id']) {
        $mid = $trip['match_id'];
        $check = $conn->query("SELECT count(*) as cnt FROM trips WHERE match_id='$mid' AND payment_status='unpaid'");
        if ($check->fetch_assoc()['cnt'] == 0) {
            $conn->query("UPDATE trips SET status='paid' WHERE match_id='$mid'");
        }
    } else {
        $conn->query("UPDATE trips SET status='paid' WHERE id=$trip_id");
    }
    echo json_encode(['status' => 'success']);
}


if ($action == 'get_driver_orders') {
   
    $sql = "SELECT id, match_id, status, duration_min, route_json, SUM(price) as total_price 
            FROM trips WHERE status='paid' 
            GROUP BY IFNULL(match_id, id)";
    
    $res = $conn->query($sql);
    $orders = [];
    while($row = $res->fetch_assoc()) $orders[] = $row;
    echo json_encode(['status'=>'success', 'orders'=>$orders]);
}

if ($action == 'accept_trip') {
    $driver_id = $_SESSION['user_id'];
    $trip_id = $_POST['trip_id'];
    $trip = $conn->query("SELECT match_id FROM trips WHERE id=$trip_id")->fetch_assoc();
    if ($trip['match_id']) {
        $mid = $trip['match_id'];
        $conn->query("UPDATE trips SET status='assigned', driver_id=$driver_id WHERE match_id='$mid'");
    } else {
        $conn->query("UPDATE trips SET status='assigned', driver_id=$driver_id WHERE id=$trip_id");
    }
    echo json_encode(['status'=>'success']);
}


if ($action == 'check_status') {
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    if ($role == 'driver') {
        
        $sql = "SELECT id, match_id, start_lat, start_lng, route_json FROM trips WHERE driver_id=$uid AND status != 'completed' LIMIT 1";
        $res = $conn->query($sql);
        
        if ($trip = $res->fetch_assoc()) {
            $passengers = [];
            $total_price = 0; 

            if ($trip['match_id']) {
                $mid = $trip['match_id'];
                
                $users_res = $conn->query("SELECT u.name, u.phone, t.price FROM trips t JOIN users u ON t.user_id = u.id WHERE t.match_id='$mid'");
                while($u = $users_res->fetch_assoc()) {
                    $total_price += $u['price']; 
                    $passengers[] = $u;
                }
            } else {
                $tid = $trip['id'];
                $users_res = $conn->query("SELECT u.name, u.phone, t.price FROM trips t JOIN users u ON t.user_id = u.id WHERE t.id=$tid");
                while($u = $users_res->fetch_assoc()) {
                    $total_price += $u['price'];
                    $passengers[] = $u;
                }
            }
            
            $trip['passengers'] = $passengers;
            $trip['total_price'] = $total_price; 
            echo json_encode(['status'=>'success', 'data'=>$trip]);
        } else {
            echo json_encode(['status'=>'no_trip']);
        }

    } else {
        // Пассажир
        $sql = "SELECT t.*, d.name as d_name, d.car_model, d.car_color, d.car_plate, d.phone as d_phone
                FROM trips t LEFT JOIN users d ON t.driver_id = d.id
                WHERE t.user_id=$uid AND t.status != 'completed' ORDER BY id DESC LIMIT 1";
        $res = $conn->query($sql);
        
        if ($row = $res->fetch_assoc()) {
            if ($row['status'] == 'waiting_payment' && $row['payment_status'] == 'paid') {
                $row['custom_message'] = "Ожидаем оплату попутчика...";
            }
            echo json_encode(['status'=>'success', 'data'=>$row]);
        } else {
            echo json_encode(['status'=>'no_trip']);
        }
    }
}

if ($action == 'finish_trip') {
    if ($_SESSION['role'] != 'driver') die();
    $trip_id = intval($_POST['trip_id']);
    $trip = $conn->query("SELECT match_id FROM trips WHERE id=$trip_id")->fetch_assoc();
    if($trip['match_id']) {
        $mid = $trip['match_id'];
        $conn->query("DELETE FROM trips WHERE match_id='$mid'");
    } else {
        $conn->query("DELETE FROM trips WHERE id=$trip_id");
    }
    echo json_encode(['status'=>'success']);
}
?>