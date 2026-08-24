import 'package:flutter/foundation.dart';

/// Release APK uses production. Debug builds use LAN / localhost for local Laravel.
/// Phone debug APK must use your PC's Wi‑Fi IP (not 127.0.0.1).
/// If debug login fails after Wi‑Fi change, update [_lanHost] and rebuild.
class ApiConfig {
  static const String _productionOrigin = 'https://mrhari.co.in';
  static const String _lanHost = '192.168.29.247';
  static const String _localHost = '127.0.0.1';
  static const int _port = 8000;

  static String get host => kIsWeb ? _localHost : _lanHost;

  static String get origin =>
      kReleaseMode ? _productionOrigin : 'http://$host:$_port';

  static String get baseUrl => '$origin/api';

  /// Absolute URL for public disk files.
  /// Prefers `/api/media/...` so Flutter web gets CORS headers under artisan serve.
  /// Always rewrites media hosts to [origin] so APP_URL / LAN mismatches still load.
  static String? mediaUrl(String? pathOrUrl) {
    if (pathOrUrl == null || pathOrUrl.isEmpty) return null;
    if (pathOrUrl.startsWith('http://') || pathOrUrl.startsWith('https://')) {
      final uri = Uri.tryParse(pathOrUrl);
      if (uri != null) {
        // Legacy /storage/ → CORS-safe /api/media/
        if (uri.path.startsWith('/storage/')) {
          final rel = uri.path.substring('/storage/'.length);
          return '$origin/api/media/$rel';
        }
        // Keep /api/media on the configured API origin (fixes localhost vs LAN host).
        if (uri.path.startsWith('/api/media/')) {
          return '$origin${uri.path}${uri.hasQuery ? '?${uri.query}' : ''}';
        }
      }
      return pathOrUrl;
    }
    var p = pathOrUrl.startsWith('/') ? pathOrUrl : '/$pathOrUrl';
    if (p.startsWith('/api/media/')) return '$origin$p';
    if (p.startsWith('/storage/')) {
      p = p.substring('/storage/'.length);
      return '$origin/api/media/$p';
    }
    return '$origin/api/media${p.startsWith('/') ? p : '/$p'}';
  }
}
