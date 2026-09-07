let map, userMarker, technicianMarkers = [];
let currentLat = null;
let currentLng = null;

function initMap() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      currentLat = pos.coords.latitude;
      currentLng = pos.coords.longitude;
      console.log("พิกัดจาก navigator.geolocation:", currentLat, currentLng);

      if (!map) {
        map = L.map('map').setView([currentLat, currentLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        userMarker = L.marker([currentLat, currentLng], {
          icon: L.icon({
            iconUrl: 'https://maps.gstatic.com/mapfiles/ms2/micons/blue-dot.png',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
          })
        }).addTo(map).bindPopup("ตำแหน่งของคุณ").openPopup();
      } else {
        map.setView([currentLat, currentLng], 14);
        userMarker.setLatLng([currentLat, currentLng]).openPopup();
      }

      loadTechnicians(currentLat, currentLng);
    }, () => {
      alert("ไม่สามารถระบุตำแหน่งของคุณได้");
    });
  } else {
    alert("Browser ไม่รองรับ GPS");
  }
}

function loadTechnicians(lat, lng) {
  console.log("โหลดช่าง ด้วยพิกัด:", lat, lng);
  const dist = document.getElementById("distance").value;

  technicianMarkers.forEach(m => map.removeLayer(m));
  technicianMarkers = [];

  fetch(`load_tech.php?lat=${lat}&lng=${lng}&dist=${dist}`)
    .then(res => res.json())
    .then(data => {
      data.forEach(t => {
        const marker = L.marker([parseFloat(t.lat), parseFloat(t.lng)]).addTo(map);
        technicianMarkers.push(marker);

        const content = `
          <strong>${t.name}</strong><br>
          ประเภท: ${t.specialty}<br>
          เบอร์: ${t.phone}<br>
          <a href="reviews.php?tid=${t.id}" class="btn btn-sm btn-primary mt-1">รีวิว</a>
          <a href="create_or_get_conversation.php?technician_id=${t.id}" class="btn btn-sm btn-success mt-1 ms-1">แชทกับช่าง</a>
        `;

        marker.bindPopup(content);
      });
    });
}

document.getElementById("distance").addEventListener("change", () => {
  if (currentLat !== null && currentLng !== null) {
    loadTechnicians(currentLat, currentLng);
  } else if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      currentLat = pos.coords.latitude;
      currentLng = pos.coords.longitude;
      loadTechnicians(currentLat, currentLng);
    });
  }
});

window.addEventListener("load", initMap);
