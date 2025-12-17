<?php require 'db.php'; 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') header("Location: index.php");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Карпулинг</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

    
    <div id="statusBox" class="status-card">
        <div class="status-header">
            <h5 id="statusTitle" class="fw-bold m-0">Поиск...</h5>
            <button class="toggle-btn" id="toggleStatus">▲</button>
        </div>
        
        <div class="status-content" id="statusContent">
            <div id="statusText" class="text-muted small mb-2">Ожидайте...</div>
            
            <div class="d-flex justify-content-center align-items-baseline mb-2">
                <span class="price-big me-2"><span id="priceVal">0</span></span>
                <span class="text-muted fw-bold">BYN</span>
            </div>

            <div id="searchingButtons" style="display:none;">
                <button id="btnGoAlone" class="btn btn-sm btn-outline-dark w-100 mb-2">Поехать одному (Дороже)</button>
                <div class="text-center small text-muted spin-loader">⏳ Ищем попутчика...</div>
            </div>

            <div id="driverInfo" class="driver-box text-start">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-dark">ВОДИТЕЛЬ</span>
                    <a id="dPhone" href="#" class="fw-bold text-success text-decoration-none">📞 Позвонить</a>
                </div>
                <div class="fw-bold" id="dName">Имя</div>
                <div class="small text-muted" id="dCar">Авто</div>
                <div class="plate mt-1" id="dPlate">NUM</div>
            </div>

            <button id="btnOpenPay" class="btn btn-custom btn-action w-100 mt-3 shadow" style="display:none;">ОПЛАТИТЬ</button>
            <div id="waitMsg" class="alert alert-info mt-3 mb-0 small fw-bold" style="display:none;"></div>
        </div>
    </div>

    
    <div id="map"></div>

   
    <div class="controls-area" id="controlsBlock">
        <div class="row g-3">
            <div class="col-6"><button id="btnStart" class="btn btn-custom btn-select w-100">📍 Откуда</button></div>
            <div class="col-6"><button id="btnEnd" class="btn btn-custom btn-select w-100" disabled>🏁 Куда</button></div>
            <div class="col-12"><button id="btnGo" class="btn btn-custom btn-dark-custom w-100" disabled>ЗАКАЗАТЬ</button></div>
        </div>
        <div class="text-center mt-3 d-flex justify-content-between px-2 align-items-center">
            <span class="fw-bold text-dark small"><?php echo $_SESSION['name']; ?></span>
            <a href="index.php" class="text-danger fw-bold text-decoration-none small">Выйти</a>
        </div>
    </div>

    
    <div class="modal fade" id="payModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3 border-0 shadow-lg" style="border-radius:25px;">
                <div class="modal-body">
                    <h5 class="fw-bold text-center mb-3">Оплата поездки</h5>
                    <form id="paymentForm">
                        <div class="mb-3"><input type="text" class="form-control form-control-custom" placeholder="0000 0000 0000 0000" maxlength="19" required></div>
                        <div class="row g-2">
                            <div class="col-6"><input type="text" class="form-control form-control-custom" placeholder="MM/YY" maxlength="5" required></div>
                            <div class="col-6"><input type="password" class="form-control form-control-custom" placeholder="CVC" maxlength="3" required></div>
                        </div>
                        <button type="submit" class="btn btn-custom btn-action w-100 mt-3">Списать <span id="modalPrice"></span> BYN</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    
    <script>
        $(document).ready(function() {
            var map = L.map('map', {zoomControl:false}).setView([53.9, 27.56], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            setTimeout(function(){ map.invalidateSize(); }, 500);

            if(navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(p){ 
                    map.setView([p.coords.latitude, p.coords.longitude], 14); 
                });
            }

            $('#toggleStatus').click(function(){
                $('#statusContent').slideToggle();
                $(this).toggleClass('rotated');
            });

            var startMarker, endMarker, startCoords, endCoords;
            var currentTripId=null, pollInterval=null, routingControl=null, mode='none';
            var greenIcon = new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png', iconSize:[25,41], iconAnchor:[12,41], popupAnchor:[1,-34]});
            var redIcon = new L.Icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png', iconSize:[25,41], iconAnchor:[12,41], popupAnchor:[1,-34]});

            $('#btnStart').click(function(){ mode='start'; $(this).addClass('active'); $('#btnEnd').removeClass('active'); });
            $('#btnEnd').click(function(){ mode='end'; $(this).addClass('active'); $('#btnStart').removeClass('active'); });

            map.on('click', function(e){
                if(mode==='start') {
                    if(startMarker) map.removeLayer(startMarker);
                    startMarker = L.marker(e.latlng, {icon:greenIcon}).addTo(map);
                    startCoords = {lat:e.latlng.lat, lng:e.latlng.lng};
                    $('#btnStart').html('✔ Откуда').removeClass('active');
                    $('#btnEnd').prop('disabled',false).click();
                } else if(mode==='end') {
                    if(endMarker) map.removeLayer(endMarker);
                    endMarker = L.marker(e.latlng, {icon:redIcon}).addTo(map);
                    endCoords = {lat:e.latlng.lat, lng:e.latlng.lng};
                    $('#btnEnd').html('✔ Куда').removeClass('active');
                    $('#btnGo').prop('disabled',false).addClass('btn-action');
                    mode='none';
                }
            });

            $('#btnGo').click(function(){
                $(this).prop('disabled',true).text('...');
                $('#controlsBlock').fadeOut();
                $('#statusBox').fadeIn();
                $.post('api.php?action=create_trip', {
                    start_lat: startCoords.lat, start_lng: startCoords.lng,
                    end_lat: endCoords.lat, end_lng: endCoords.lng
                }, function(){ startPolling(); }, 'json');
            });

            $('#btnGoAlone').click(function(){
                if(confirm('Точно едете один? Цена будет пересчитана.')) {
                    $.post('api.php?action=go_alone', {}, function(res){
                        if(res.status==='success') checkStatus();
                    }, 'json');
                }
            });

            function startPolling(){
                if(pollInterval) clearInterval(pollInterval);
                pollInterval = setInterval(checkStatus, 2000);
            }

            function drawRoute(json) {
                if(!json) return;
                let pts = JSON.parse(json);
                if(!routingControl) {
                    let wps = pts.map(p => L.latLng(p.lat, p.lng));
                    if(startMarker) map.removeLayer(startMarker);
                    if(endMarker) map.removeLayer(endMarker);
                    routingControl = L.Routing.control({
                        waypoints: wps, show:false, draggableWaypoints:false, addWaypoints:false,
                        lineOptions: {styles:[{color:'#10b981', opacity:0.8, weight:6}]},
                        createMarker: function(i, wp) {
                            let url = (i === 0) ? 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png' : 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png';
                            return L.marker(wp.latLng, {
                                icon: new L.Icon({iconUrl: url, iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34]})
                            });
                        }
                    }).addTo(map);
                    map.fitBounds(L.latLngBounds(wps).pad(0.2));
                }
            }

            function checkStatus(){
                $.getJSON('api.php?action=check_status', function(res){
                    if(res.status==='success'){
                        let t = res.data;
                        currentTripId = t.id;
                        $('#priceVal').text(t.price);
                        $('#modalPrice').text(t.price);

                        $('#searchingButtons').hide();
                        $('#btnOpenPay').hide();
                        $('#waitMsg').hide();
                        $('#driverInfo').hide();

                        if(t.status==='searching') {
                            $('#statusTitle').text('Поиск попутчиков');
                            $('#statusText').text('Ищем совпадения маршрутов...');
                            $('#searchingButtons').show();
                        }
                        else if(t.status==='waiting_payment') {
                            $('#statusTitle').text('Заявка создана');
                            if (t.custom_message) {
                                $('#statusText').text(t.custom_message).addClass('text-danger fw-bold');
                                $('#btnOpenPay').hide();
                            } else {
                                $('#statusText').text('Маршрут построен. Оплатите поездку.');
                                $('#statusText').removeClass('text-danger fw-bold');
                                $('#btnOpenPay').show();
                            }
                            drawRoute(t.route_json);
                        }
                        else if(t.status==='paid') {
                            $('#statusTitle').text('Оплачено');
                            $('#statusText').text('Ищем водителя...');
                            $('#waitMsg').show().text('Все пассажиры оплатили. Ждем назначения машины.');
                            drawRoute(t.route_json);
                        }
                        else if(t.status==='assigned') {
                            $('#statusTitle').text('Машина едет!');
                            $('#statusText').text('Водитель принял заказ.');
                            $('#driverInfo').show();
                            $('#dName').text(t.d_name);
                            $('#dCar').text(t.car_color+' '+t.car_model);
                            $('#dPlate').text(t.car_plate);
                            $('#dPhone').attr('href', 'tel:'+t.d_phone).text(t.d_phone);
                            drawRoute(t.route_json);
                        }
                    } else {
                         if(currentTripId) { alert('Поездка завершена!'); location.reload(); }
                    }
                });
            }

            $('#btnOpenPay').click(function(){ new bootstrap.Modal(document.getElementById('payModal')).show(); });
            
            $('#paymentForm').submit(function(e){
                e.preventDefault();
                $(this).find('button').prop('disabled',true).text('...');
                setTimeout(function(){
                    $.post('api.php?action=pay', {trip_id:currentTripId}, function(){
                        $('#payModal').modal('hide'); 
                        $('.modal-backdrop').remove();
                        checkStatus();
                    }, 'json');
                }, 1000);
            });
        });
    </script>
</body>
</html>