
var map = L.map('map').setView([53.9, 27.56], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'OSM' }).addTo(map);


if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        map.setView([position.coords.latitude, position.coords.longitude], 14);
    }, function() {
        console.log("GPS недоступен, используйте ручной выбор");
    });
}

var startMarker, endMarker;
var startCoords = {}, endCoords = {};
var currentTripId = null;
var pollInterval = null;
var routingControl = null;
var mode = 'none';


$('#btnStart').click(function() { mode = 'picking_start'; alert('Поставьте точку СТАРТА на карте'); });
$('#btnEnd').click(function() { mode = 'picking_end'; alert('Поставьте точку ФИНИША на карте'); });


map.on('click', function(e) {
    if (mode === 'picking_start') {
        if (startMarker) map.removeLayer(startMarker);
        startMarker = L.marker(e.latlng).addTo(map).bindPopup("Старт").openPopup();
        startCoords = { lat: e.latlng.lat, lng: e.latlng.lng };
        
        $('#btnStart').removeClass('btn-outline-primary').addClass('btn-primary').html('✅ Старт выбран');
        $('#btnEnd').prop('disabled', false);
        mode = 'none';
        
    } else if (mode === 'picking_end') {
        if (endMarker) map.removeLayer(endMarker);
        endMarker = L.marker(e.latlng, {icon: L.icon({iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png', iconSize:[25,41], iconAnchor:[12,41]})}).addTo(map);
        endCoords = { lat: e.latlng.lat, lng: e.latlng.lng };
        
        $('#btnEnd').removeClass('btn-outline-danger').addClass('btn-danger').html('✅ Финиш выбран');
        $('#btnGo').prop('disabled', false).removeClass('btn-dark').addClass('btn-success');
        mode = 'none';
    }
});


$('#btnGo').click(function() {
    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Обработка...');
    $('#statusBox').fadeIn();
    
    $.post('api.php?action=create_trip', {
        start_lat: startCoords.lat, start_lng: startCoords.lng,
        end_lat: endCoords.lat, end_lng: endCoords.lng
    }, function(res) {
       
        startPolling(); 
    }, 'json'); 
});


function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(checkStatus, 2000);
}

function checkStatus() {
    $.getJSON('api.php?action=check_status', function(res) {
        if (res.status === 'success') {
            updateUI(res.data);
            currentTripId = res.data.id;
        } else if (res.status === 'no_trip') {
             if(currentTripId) {
                 alert("Поездка завершена и удалена из истории.");
                 location.reload(); 
             }
        }
    });
}


function updateUI(trip) {
    $('#priceVal').text(trip.price);
    $('#modalPrice').text(trip.price);
    $('#timeTag').text(trip.duration_min + ' мин');

    if (trip.status === 'pending') {
        $('#statusTitle').text('Поиск попутчиков...');
        $('#statusText').text('Ищем кого-то, кому с вами по пути...');
    } 
    else if (trip.status === 'matched') {
        $('#statusTitle').text('Попутчик найден!').addClass('text-success');
        
        if (trip.route_json) {
          
            let pts = JSON.parse(trip.route_json);
            
            let html = '<ul class="text-start mb-0 ps-3 small">';
            pts.forEach((p, idx) => {
                let label = (idx === 0) ? 'Посадка' : 'Остановка';
                html += `<li><b>${label}:</b> ${p.name}</li>`;
            });
            html += '</ul>';
            $('#statusText').html(html);
            
            if (!routingControl) {
                let wps = pts.map(p => L.latLng(p.lat, p.lng));
                routingControl = L.Routing.control({
                    waypoints: wps, 
                    show: false, 
                    draggableWaypoints: false, 
                    addWaypoints: false,
                    lineOptions: { styles: [{color: 'blue', opacity: 0.6, weight: 6}] },
                    createMarker: function() { return null; } 
                }).addTo(map);
            }
        } else {
            $('#statusText').text('Простой совместный маршрут');
        }
        
        $('#btnOpenPay').show();
    }
    else if (trip.status === 'paid') {
        $('#statusTitle').text('Успешно оплачено').removeClass('text-success').addClass('text-primary');
        $('#statusText').html('<b>Водитель в пути!</b><br>Приятной поездки.');
        $('#btnOpenPay').hide();
        $('#btnFinish').show(); 
        
        if(pollInterval) clearInterval(pollInterval); 
    }
}



$('#btnOpenPay').click(function() {
    var myModal = new bootstrap.Modal(document.getElementById('payModal'));
    myModal.show();
});

$('#paymentForm').submit(function(e) {
    e.preventDefault();
    
    let btn = $(this).find('button');
    btn.prop('disabled', true).text('Обработка транзакции...');
    
    setTimeout(function() {
        $.post('api.php?action=pay', { trip_id: currentTripId }, function() {
            $('#payModal').modal('hide'); 
            $('.modal-backdrop').remove(); 
            checkStatus(); 
        }, 'json'); 
    }, 1500);
});

$('#btnFinish').click(function() {
    if(!confirm('Вы действительно доехали до места назначения?')) return;
    
    $.post('api.php?action=finish', { trip_id: currentTripId }, function() {
        alert('Спасибо за использование сервиса!');
        location.reload(); 
    }, 'json');
});