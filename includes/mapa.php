<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mapa LYNK25</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
  <style>
    html, body { height: 100%; margin: 0; padding: 0; }
    #map { width: 100%; height: 600px; }
  </style>
</head>
<body>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
  <script>
    // Crear mapa
    var map = L.map('map').setView([0,0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap'
    }).addTo(map);

    // Grupo de clusters
    var markers = L.markerClusterGroup();

    // Cargar datos desde radioid_cache.json
    fetch('../data/radioid_cache.json?ts=' + Date.now())
      .then(r => r.json())
      .then(data => {
        console.log("Usuarios cargados:", data);
        var bounds = [];

        Object.keys(data).forEach(cs => {
          var u = data[cs];
          if (u.lat && u.lon) {
            var marker = L.marker([u.lat, u.lon]);
            marker.bindPopup(
              "<b>"+cs+"</b><br/>" +
              (u.name ? u.name+"<br/>" : "") +
              (u.city && u.country ? u.city+", "+u.country : "")
            );
            markers.addLayer(marker);
            bounds.push([u.lat, u.lon]);
          }
        });

        map.addLayer(markers);

        if (bounds.length > 0) {
          map.fitBounds(bounds, {padding: [30, 30]});
        }
      })
      .catch(err => console.error("Error cargando radioid_cache.json:", err));
  </script>
</body>
</html>
