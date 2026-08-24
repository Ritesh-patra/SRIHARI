import 'dart:convert';

import 'file_download.dart';

/// Export pole pins as CSV + printable Leaflet HTML (works on Chrome web + APK).
class PoleMapExport {
  static String csv(List poles, {String dtrName = '', String dtrCode = ''}) {
    final buf = StringBuffer();
    buf.writeln('DTR,DTR Code,Pole No,Latitude,Longitude,Houses,Source,Static Map Link');
    for (final raw in poles) {
      final p = Map<String, dynamic>.from(raw as Map);
      final lat = _num(p['latitude']);
      final lng = _num(p['longitude']);
      final poleNo = '${p['pole_no'] ?? ''}'.replaceAll(',', ' ');
      final houses = p['houses_connected'] ?? 0;
      final source = p['source_type'] == 'previous_pole'
          ? 'previous_pole:${p['previous_pole']?['pole_no'] ?? p['previous_pole_id'] ?? ''}'
          : 'dtr';
      final mapLink = (lat != null && lng != null)
          ? 'https://www.openstreetmap.org/?mlat=$lat&mlon=$lng#map=18/$lat/$lng'
          : '';
      buf.writeln(
        '"$dtrName","$dtrCode","$poleNo",${lat ?? ''},${lng ?? ''},$houses,"$source","$mapLink"',
      );
    }
    return buf.toString();
  }

  static String geoJson(List poles, {String dtrName = '', String dtrCode = ''}) {
    final features = <Map<String, dynamic>>[];
    for (final raw in poles) {
      final p = Map<String, dynamic>.from(raw as Map);
      final lat = _num(p['latitude']);
      final lng = _num(p['longitude']);
      if (lat == null || lng == null) continue;
      features.add({
        'type': 'Feature',
        'geometry': {
          'type': 'Point',
          'coordinates': [lng, lat],
        },
        'properties': {
          'pole_no': p['pole_no'],
          'houses_connected': p['houses_connected'],
          'source_type': p['source_type'],
          'dtr_name': dtrName,
          'dtr_code': dtrCode,
        },
      });
    }
    return const JsonEncoder.withIndent('  ').convert({
      'type': 'FeatureCollection',
      'features': features,
    });
  }

  /// Self-contained HTML with Leaflet CDN — open offline-ish (needs net for tiles once).
  static String leafletHtml(
    List poles, {
    String title = 'SEAS Pole Map',
    String dtrName = '',
    String dtrCode = '',
  }) {
    final markers = <Map<String, dynamic>>[];
    for (final raw in poles) {
      final p = Map<String, dynamic>.from(raw as Map);
      final lat = _num(p['latitude']);
      final lng = _num(p['longitude']);
      if (lat == null || lng == null) continue;
      markers.add({
        'lat': lat,
        'lng': lng,
        'label': '${p['pole_no'] ?? ''}',
        'houses': p['houses_connected'] ?? 0,
      });
    }
    final markersJson = jsonEncode(markers);
    final safeTitle = _esc(title);
    final safeDtr = _esc('$dtrName ($dtrCode)');
    return '''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>$safeTitle</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  html,body{margin:0;height:100%;font-family:system-ui,sans-serif;background:#0a0a0a;color:#fff}
  #bar{padding:12px 16px;background:linear-gradient(90deg,#0a0a0a,#3a0a0a);border-bottom:2px solid #e10600}
  #bar h1{margin:0;font-size:16px;font-weight:800}
  #bar p{margin:4px 0 0;font-size:12px;opacity:.75}
  #map{height:calc(100% - 64px);width:100%}
  .pole-label{background:#e10600;color:#fff;border:none;border-radius:8px;padding:2px 8px;font-weight:800;font-size:12px;box-shadow:0 2px 8px rgba(225,6,0,.45)}
  @media print{#bar{display:none}#map{height:100vh}}
</style>
</head>
<body>
<div id="bar">
  <h1>$safeTitle</h1>
  <p>$safeDtr · ${markers.length} pins · Print / Save as PDF from browser</p>
</div>
<div id="map"></div>
<script>
const markers = $markersJson;
const map = L.map('map');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '&copy; OpenStreetMap'
}).addTo(map);
const group = L.featureGroup();
markers.forEach(m => {
  const marker = L.marker([m.lat, m.lng]);
  marker.bindTooltip(m.label, {permanent:true, direction:'top', className:'pole-label', offset:[0,-8]});
  marker.bindPopup('<b>Pole ' + m.label + '</b><br/>Houses: ' + m.houses + '<br/>' + m.lat + ', ' + m.lng);
  group.addLayer(marker);
});
if (markers.length) {
  group.addTo(map);
  map.fitBounds(group.getBounds().pad(0.25));
} else {
  map.setView([22.97, 78.65], 5);
}
</script>
</body>
</html>''';
  }

  static Future<String> downloadAll(
    List poles, {
    String dtrName = '',
    String dtrCode = '',
  }) async {
    final stamp = DateTime.now().toIso8601String().replaceAll(':', '-').split('.').first;
    final base = 'seas_poles_${dtrCode.isEmpty ? 'map' : dtrCode}_$stamp'
        .replaceAll(RegExp(r'[^a-zA-Z0-9_-]'), '_');

    final csvBytes = utf8.encode(csv(poles, dtrName: dtrName, dtrCode: dtrCode));
    final htmlBytes = utf8.encode(leafletHtml(
      poles,
      title: 'SEAS Pole Map — $dtrName',
      dtrName: dtrName,
      dtrCode: dtrCode,
    ));
    final geoBytes = utf8.encode(geoJson(poles, dtrName: dtrName, dtrCode: dtrCode));

    await saveDownloadBytes(csvBytes, '$base.csv');
    await saveDownloadBytes(htmlBytes, '$base.html');
    await saveDownloadBytes(geoBytes, '$base.geojson');
    return base;
  }

  static double? _num(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    return double.tryParse('$v');
  }

  static String _esc(String s) => s
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;');
}
