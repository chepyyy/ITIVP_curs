<?php require 'db.php'; 
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver') header("Location: index.php");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <div id="map"></div>

    <div class="top-bar">
        <span class="badge bg-white text-dark shadow fw-bold py-2 px-3">🚖 <?php echo $_SESSION['name']; ?></span>
        <a href="index.php" class="btn btn-sm btn-danger shadow" style="border-radius: 20px;">Выход</a>
    </div>

    <div id="ordersPanel" class="orders-panel">
        <div class="panel-header" id="panelToggle">
            <span class="fw-bold small">🔍 ЗАКАЗЫ (<span id="ordersCount">0</span>)</span><span class="toggle-icon">▼</span>
        </div>
        <div class="panel-body" id="ordersList"></div>
    </div>

    <div id="activeTrip" class="active-trip-panel">
        <div class="at-header" id="atToggle">
            <div class="d-flex align-items-center"><span class="badge bg-success me-2">В РАБОТЕ</span></div>
            <div class="d-flex align-items-center"><span class="fw-bold text-primary me-3" id="tripPrice">0 BYN</span><span class="toggle-icon">▼</span></div>
        </div>
        <div class="at-body" id="atBody">
            <div class="small text-muted fw-bold mb-2">ПАССАЖИРЫ:</div>
            <div id="passengersList"></div> 
            <hr>
            <div class="small text-muted fw-bold mb-2">МАРШРУТ:</div>
            <ul class="route-stops mb-3" id="routeStopsList"></ul>
            <button id="btnFinish" class="btn btn-custom btn-finish shadow">ЗАВЕРШИТЬ ЗАКАЗ</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script>
        var map = L.map('map', {zoomControl: false}).setView([53.9, 27.56], 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        if (navigator.geolocation) { navigator.geolocation.getCurrentPosition(function(p) { map.setView([p.coords.latitude, p.coords.longitude], 13); }); }
        setTimeout(() => map.invalidateSize(), 500);

        var routingControl = null;
        var lastOrdersJson = "";
        var ordersData = {};

        $('#panelToggle').click(function() { $('#ordersList').slideToggle(200); $(this).toggleClass('rounded-all').toggleClass('collapsed'); });
        $('#atToggle').click(function() { $('#atBody').slideToggle(200); $(this).toggleClass('collapsed'); });

        function drawRouteOnMap(jsonPoints) {
            if(!jsonPoints) return;
            let wps = JSON.parse(jsonPoints).map(p => L.latLng(p.lat, p.lng));
            if(routingControl) { map.removeControl(routingControl); routingControl = null; }
            map.eachLayer((layer) => { if(layer instanceof L.Marker) map.removeLayer(layer); });
            routingControl = L.Routing.control({
                waypoints: wps, show: false, addWaypoints: false, draggableWaypoints: false, fitSelectedRoutes: true,
                lineOptions: { styles: [{color: '#2563eb', opacity: 0.8, weight: 6}] },
                createMarker: function(i, wp) {
                    let url = (i === 0) ? 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png' : 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png';
                    return L.marker(wp.latLng, { icon: new L.Icon({iconUrl: url, iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34]}) });
                }
            }).addTo(map);
        }

        window.previewOrder = function(id) {
            let order = ordersData[id];
            if(!order) return;
            $('.order-card').removeClass('selected'); $(`#card-${id}`).addClass('selected');
            if(order.route_json) drawRouteOnMap(order.route_json);
        };

        function checkStatus() {
            $.getJSON('api.php?action=check_status', function(res){
                if(res.status === 'success') {
                    $('#ordersPanel').hide(); $('#activeTrip').fadeIn();
                    let t = res.data;
                    $('#tripPrice').text(t.total_price.toFixed(2) + ' BYN');

                    let passHtml = '';
                    if(t.passengers && t.passengers.length > 0) {
                        t.passengers.forEach((p, idx) => {
                            passHtml += `
                            <div class="passenger-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">Попутчик ${idx+1}</div>
                                    <div class="text-primary">${p.name}</div>
                                </div>
                                <a href="tel:${p.phone}" class="btn btn-sm btn-outline-success fw-bold">${p.phone}</a>
                            </div>`;
                        });
                    }
                    $('#passengersList').html(passHtml);

                    if(t.route_json && !routingControl) {
                        drawRouteOnMap(t.route_json);
                        let pts = JSON.parse(t.route_json);
                        let stopsHtml = '';
                        pts.forEach(p => { stopsHtml += `<li>${p.name}</li>`; });
                        $('#routeStopsList').html(stopsHtml);
                    }

                    $('#btnFinish').off('click').click(function(){
                        if(!confirm('Завершить заказ?')) return;
                        $.post('api.php?action=finish_trip', {trip_id: t.id}, function(){ location.reload(); }, 'json');
                    });
                } else {
                    $('#activeTrip').hide(); $('#ordersPanel').show();
                    loadOrders();
                }
            });
        }

        function loadOrders() {
            $.getJSON('api.php?action=get_driver_orders', function(res){
                let currentJson = JSON.stringify(res.orders);
                if (currentJson === lastOrdersJson) return;
                lastOrdersJson = currentJson;
                $('#ordersCount').text(res.orders.length);
                let html = ''; ordersData = {};
                if(res.orders.length === 0) {
                    html = '<div class="text-center text-muted py-3 small">Нет заказов...</div>';
                    if(routingControl) { map.removeControl(routingControl); routingControl = null; map.eachLayer(l => {if(l instanceof L.Marker) map.removeLayer(l)}); }
                } else {
                    res.orders.forEach(o => {
                        ordersData[o.id] = o;
                        html += `
                        <div class="order-card" id="card-${o.id}" onclick="previewOrder(${o.id})">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-success">ГОТОВ</span>
                                <span class="fs-5 fw-bold text-primary">${o.total_price} BYN</span>
                            </div>
                            <div class="text-muted small mb-2">~${o.duration_min} мин</div>
                            <button class="btn btn-custom btn-accept shadow-sm" onclick="event.stopPropagation(); acceptTrip(${o.id})">ПРИНЯТЬ</button>
                        </div>`;
                    });
                }
                $('#ordersList').html(html);
            });
        }

        window.acceptTrip = function(id) {
            $.post('api.php?action=accept_trip', {trip_id: id}, function(){ lastOrdersJson = ""; routingControl = null; checkStatus(); }, 'json');
        }

        setInterval(checkStatus, 3000);
        checkStatus();
    </script>
</body>
</html>