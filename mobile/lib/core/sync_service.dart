import 'package:connectivity_plus/connectivity_plus.dart';
import 'api_client.dart';
import 'offline_queue.dart';

class SyncService {
  bool _running = false;

  Future<bool> get isOnline async {
    final result = await Connectivity().checkConnectivity();
    return !result.contains(ConnectivityResult.none);
  }

  /// Push local drafts/submits when internet returns.
  Future<int> syncPending() async {
    if (_running) return 0;
    if (!await isOnline) return 0;
    _running = true;
    var synced = 0;
    try {
      final items = await offlineQueue.all();
      for (final item in items) {
        try {
          final path = item.serverId == null ? '/surveys' : '/surveys/${item.serverId}';
          final fields = Map<String, String>.from(item.fields)..['action'] = item.action;
          final res = await api.postSurveyMultipart(
            path: path,
            fields: fields,
            dtrPhotoPath: item.dtrPhotoPath,
            meterPhotoPath: item.meterPhotoPath,
          );
          final survey = res['survey'];
          if (survey is Map && survey['id'] != null) {
            // Successfully synced — remove from offline queue
            await offlineQueue.remove(item.localId);
            synced++;
          }
        } catch (_) {
          // Keep in queue for next attempt
        }
      }
    } finally {
      _running = false;
    }
    return synced;
  }
}

final syncService = SyncService();
