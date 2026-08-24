import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

class OfflineDraft {
  OfflineDraft({
    required this.localId,
    required this.fields,
    required this.action,
    this.serverId,
    this.dtrPhotoPath,
    this.meterPhotoPath,
    DateTime? updatedAt,
  }) : updatedAt = updatedAt ?? DateTime.now();

  final String localId;
  int? serverId;
  Map<String, String> fields;
  String action; // draft | submit
  String? dtrPhotoPath;
  String? meterPhotoPath;
  DateTime updatedAt;

  Map<String, dynamic> toJson() => {
        'local_id': localId,
        'server_id': serverId,
        'fields': fields,
        'action': action,
        'dtr_photo_path': dtrPhotoPath,
        'meter_photo_path': meterPhotoPath,
        'updated_at': updatedAt.toIso8601String(),
      };

  factory OfflineDraft.fromJson(Map<String, dynamic> j) => OfflineDraft(
        localId: j['local_id'] as String,
        serverId: j['server_id'] as int?,
        fields: Map<String, String>.from(j['fields'] as Map),
        action: j['action'] as String? ?? 'draft',
        dtrPhotoPath: j['dtr_photo_path'] as String?,
        meterPhotoPath: j['meter_photo_path'] as String?,
        updatedAt: DateTime.tryParse(j['updated_at'] as String? ?? '') ?? DateTime.now(),
      );
}

class OfflineQueue {
  static const _key = 'seas_offline_surveys';
  final _uuid = const Uuid();

  Future<List<OfflineDraft>> all() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return [];
    final list = jsonDecode(raw) as List;
    return list.map((e) => OfflineDraft.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<void> _save(List<OfflineDraft> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(items.map((e) => e.toJson()).toList()));
  }

  Future<OfflineDraft> upsert({
    String? localId,
    int? serverId,
    required Map<String, String> fields,
    required String action,
    String? dtrPhotoPath,
    String? meterPhotoPath,
  }) async {
    final items = await all();
    final id = localId ?? _uuid.v4();
    final idx = items.indexWhere((e) => e.localId == id);
    final draft = OfflineDraft(
      localId: id,
      serverId: serverId ?? (idx >= 0 ? items[idx].serverId : null),
      fields: fields,
      action: action,
      dtrPhotoPath: dtrPhotoPath,
      meterPhotoPath: meterPhotoPath,
    );
    if (idx >= 0) {
      items[idx] = draft;
    } else {
      items.insert(0, draft);
    }
    await _save(items);
    return draft;
  }

  Future<void> remove(String localId) async {
    final items = await all();
    items.removeWhere((e) => e.localId == localId);
    await _save(items);
  }

  Future<int> pendingCount() async => (await all()).length;
}

final offlineQueue = OfflineQueue();
