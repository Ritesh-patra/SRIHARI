import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_client.dart';

/// Offline-capable hierarchy: Region → … → Feeder.
/// DTRs are lazy-loaded per feeder (bundle no longer embeds 160k DTRs).
class HierarchyCache {
  static const _key = 'seas_hierarchy_bundle';
  List<Map<String, dynamic>> regions = [];
  List<int> assignedZoneIds = [];
  List<int> assignedFeederIds = [];

  /// feederId → DTR list (from API or local cache)
  final Map<int, List<Map<String, dynamic>>> _dtrsByFeeder = {};

  Future<void> loadLocal() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null) return;
    final map = jsonDecode(raw) as Map<String, dynamic>;
    regions = ((map['regions'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    assignedZoneIds = ((map['assigned_zone_ids'] as List?) ?? []).map((e) => (e as num).toInt()).toList();
    assignedFeederIds = ((map['assigned_feeder_ids'] as List?) ?? []).map((e) => (e as num).toInt()).toList();
    _hydrateDtrsFromTree();
  }

  void _hydrateDtrsFromTree() {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            for (final s in _list(z['substations'])) {
              for (final f in _list(s['feeders'])) {
                final id = (f['id'] as num?)?.toInt();
                if (id == null) continue;
                final dtrs = _list(f['dtrs']);
                if (dtrs.isNotEmpty) {
                  _dtrsByFeeder[id] = dtrs;
                }
              }
            }
          }
        }
      }
    }
  }

  Future<void> refreshFromApi() async {
    final res = await api.get('/hierarchy/bundle');
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(res));
    regions = ((res['regions'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    assignedZoneIds = ((res['assigned_zone_ids'] as List?) ?? []).map((e) => (e as num).toInt()).toList();
    assignedFeederIds = ((res['assigned_feeder_ids'] as List?) ?? []).map((e) => (e as num).toInt()).toList();
    // Keep previously fetched DTRs; bundle no longer includes them.
    _hydrateDtrsFromTree();
  }

  Future<void> ensureLoaded() async {
    await loadLocal();
    if (regions.isEmpty) {
      try {
        await refreshFromApi();
      } catch (_) {}
    } else {
      try {
        await refreshFromApi();
      } catch (_) {}
    }
  }

  List<Map<String, dynamic>> circles(int? regionId) {
    final matches = regions.where((e) => e['id'] == regionId);
    if (matches.isEmpty) return [];
    return _list(matches.first['circles']);
  }

  List<Map<String, dynamic>> divisions(int? circleId) {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        if (c['id'] == circleId) return _list(c['divisions']);
      }
    }
    return [];
  }

  List<Map<String, dynamic>> zones(int? divisionId) {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          if (d['id'] == divisionId) return _list(d['zones']);
        }
      }
    }
    return [];
  }

  List<Map<String, dynamic>> substations(int? zoneId) {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            if (z['id'] == zoneId) return _list(z['substations']);
          }
        }
      }
    }
    return [];
  }

  List<Map<String, dynamic>> feeders(int? substationId) {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            for (final s in _list(z['substations'])) {
              if (s['id'] == substationId) {
                final list = _list(s['feeders']);
                if (assignedFeederIds.isEmpty) return list;
                return list.where((f) => assignedFeederIds.contains(f['id'])).toList();
              }
            }
          }
        }
      }
    }
    return [];
  }

  bool get hasAssignedFeeders => assignedFeederIds.isNotEmpty || (regions.isNotEmpty && _anyFeeder());

  bool _anyFeeder() {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            for (final s in _list(z['substations'])) {
              if (_list(s['feeders']).isNotEmpty) return true;
            }
          }
        }
      }
    }
    return false;
  }

  /// Cached / sync view — may be empty until [ensureDtrs] is awaited.
  List<Map<String, dynamic>> dtrs(int? feederId) {
    if (feederId == null) return [];
    return List<Map<String, dynamic>>.from(_dtrsByFeeder[feederId] ?? const []);
  }

  /// Lazy-load DTRs for a feeder from API (and cache in memory + prefs tree).
  ///
  /// [excludeSurveyed] hides DTRs already surveyed (draft/pending/approved) so
  /// active pickers shrink as work completes. Prefer this over [excludeStandalone].
  Future<List<Map<String, dynamic>>> ensureDtrs(
    int? feederId, {
    bool excludeStandalone = false,
    bool excludeSurveyed = false,
  }) async {
    if (feederId == null) return [];
    final filtered = excludeSurveyed || excludeStandalone;
    if (!filtered && _dtrsByFeeder.containsKey(feederId) && _dtrsByFeeder[feederId]!.isNotEmpty) {
      return dtrs(feederId);
    }
    try {
      final qs = excludeSurveyed
          ? '&exclude_surveyed=1'
          : (excludeStandalone ? '&exclude_standalone=1' : '');
      final res = await api.get('/hierarchy/dtrs?feeder_id=$feederId$qs');
      final raw = res['data'];
      final list = (raw is List)
          ? raw.map((e) => Map<String, dynamic>.from(e as Map)).toList()
          : <Map<String, dynamic>>[];
      // Only cache the unfiltered feeder DTR set for hierarchy reuse.
      if (!filtered) {
        _dtrsByFeeder[feederId] = list;
        await _persistDtrsIntoTree(feederId, list);
      }
      return list;
    } catch (_) {
      return dtrs(feederId);
    }
  }

  Future<void> _persistDtrsIntoTree(int feederId, List<Map<String, dynamic>> list) async {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            for (final s in _list(z['substations'])) {
              for (final f in _list(s['feeders'])) {
                if (f['id'] == feederId) {
                  f['dtrs'] = list;
                  final prefs = await SharedPreferences.getInstance();
                  await prefs.setString(
                    _key,
                    jsonEncode({
                      'regions': regions,
                      'assigned_zone_ids': assignedZoneIds,
                      'assigned_feeder_ids': assignedFeederIds,
                      'includes_dtrs': false,
                      'cached_at': DateTime.now().toIso8601String(),
                    }),
                  );
                  return;
                }
              }
            }
          }
        }
      }
    }
  }

  /// Flatten all zones in the (already scoped) tree.
  List<Map<String, dynamic>> allZones() {
    final out = <Map<String, dynamic>>[];
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            out.add({
              ...z,
              'region_id': r['id'],
              'circle_id': c['id'],
              'division_id': d['id'],
              'region_name': r['name'],
              'circle_name': c['name'],
              'division_name': d['name'],
            });
          }
        }
      }
    }
    return out;
  }

  /// Cascade upward: zone → division → circle → region.
  Map<String, int?>? ancestryForZone(int zoneId) {
    for (final r in regions) {
      for (final c in _list(r['circles'])) {
        for (final d in _list(c['divisions'])) {
          for (final z in _list(d['zones'])) {
            if (z['id'] == zoneId) {
              return {
                'region_id': r['id'] as int?,
                'circle_id': c['id'] as int?,
                'division_id': d['id'] as int?,
                'zone_id': zoneId,
              };
            }
          }
        }
      }
    }
    return null;
  }

  List<Map<String, dynamic>> _list(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  /// Insert a newly created DTR into the in-memory + persisted cache.
  Future<void> addDtr(int feederId, Map<String, dynamic> dtr) async {
    final list = List<Map<String, dynamic>>.from(_dtrsByFeeder[feederId] ?? const []);
    list.removeWhere((e) => e['id'] == dtr['id']);
    list.insert(0, dtr);
    _dtrsByFeeder[feederId] = list;
    await _persistDtrsIntoTree(feederId, list);
  }
}

final hierarchyCache = HierarchyCache();
