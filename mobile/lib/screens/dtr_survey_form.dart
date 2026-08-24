import 'dart:async';
import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import '../core/api_client.dart';
import '../core/hierarchy_cache.dart';
import '../core/offline_queue.dart';
import '../core/sync_service.dart';
import '../main.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';
import '../widgets/confirm_dialog.dart';
import 'dtr_feeder_success_screen.dart';

class DtrSurveyFormScreen extends StatefulWidget {
  const DtrSurveyFormScreen({
    super.key,
    this.localId,
    this.serverId,
    this.prefill,
    this.fromFeederFlow = false,
    this.feederSurveyId,
    this.autofetch = true,
  });
  final String? localId;
  final int? serverId;
  /// Optional hierarchy seed after Feeder Survey submit.
  final Map<String, dynamic>? prefill;
  /// When true, success → next DTR / dashboard (Finish DTR lives on success page).
  final bool fromFeederFlow;
  final int? feederSurveyId;
  /// When false (standalone DTR→Consumer path): empty hierarchy, no feeder status fetch.
  final bool autofetch;

  @override
  State<DtrSurveyFormScreen> createState() => _DtrSurveyFormScreenState();
}

class _DtrSurveyFormScreenState extends State<DtrSurveyFormScreen> {
  final _picker = ImagePicker();
  bool booting = true;
  bool saving = false;
  bool gpsLoading = false;
  String? error;
  String? localId;
  int? serverId;
  Timer? _clockTimer;
  // live clock lives only in header widget — avoids full-form rebuild every second

  Map<String, dynamic>? user;
  DateTime surveyedAt = DateTime.now();

  int? regionId, circleId, divisionId, zoneId, substationId, feederId, dtrId;
  String feederCode = '';
  String feederName = '';
  String dtrCode = '';
  String dtrName = '';
  String? capacity;

  /// Field-reported remapping: DTR code exists under another master feeder.
  bool isMappingCorrection = false;
  int? masterFeederId;
  int? reportedFeederId;
  String? fieldDtrName;
  String? mappedFeederLabel;
  String? mappedSubstationLabel;
  String? mappedCapacityLabel;

  /// null = unknown / not loaded yet; true = feeder surveyed; false = not surveyed
  bool? feederSurveyDone;
  bool feederSurveyStatusLoading = false;
  int? resolvedFeederSurveyId;
  int dtrsExpected = 0;
  int dtrsCompleted = 0;
  /// DTR ids already submitted via standalone path — excluded from Feeder→DTR picker.
  Set<int> standaloneSurveyedDtrIds = {};
  Set<int> surveyedDtrIds = {};
  List<Map<String, dynamic>> _activeDtrOptions = [];
  bool _activeDtrOptionsLoaded = false;

  final latCtrl = TextEditingController();
  final lngCtrl = TextEditingController();
  final gpsCtrl = TextEditingController();
  final oldMsnCtrl = TextEditingController();
  final newMsnCtrl = TextEditingController();
  final ctCtrl = TextEditingController();
  final mfCtrl = TextEditingController();
  final obsCtrl = TextEditingController();

  String dtrCondition = 'Normal';
  String? ltLineType;
  String smartMeterStatus = 'Installed';
  String? oldMeterCondition;
  String? oldMeterMake;
  String? newMeterMake;

  String? dtrPhotoPath;
  String? meterPhotoPath;
  String? ctPhotoPath;
  Uint8List? dtrPhotoBytes;
  Uint8List? meterPhotoBytes;
  Uint8List? ctPhotoBytes;
  XFile? dtrPhotoWeb;
  XFile? meterPhotoWeb;
  XFile? ctPhotoWeb;
  /// Existing photos already stored on server (edit / resubmit).
  bool serverHasDtrPhoto = false;
  bool serverHasMeterPhoto = false;

  static const dtrConditions = ['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'];
  static const ltLineTypes = ['Under Ground', 'Over Ground'];

  /// Map legacy OH/OG (and aliases) to Under Ground / Over Ground for the form.
  String? _normalizeLtLineType(String? raw) {
    if (raw == null) return null;
    final t = raw.trim();
    if (t.isEmpty) return null;
    if (ltLineTypes.contains(t)) return t;
    final u = t.toUpperCase().replaceAll(RegExp(r'\s+'), ' ');
    if (const {
      'UNDER GROUND', 'UNDERGROUND', 'UNDER-GROUND',
      'UG', 'UG LINE', 'U.G. LINE',
      'OG', 'OG LINE', 'O.G. LINE',
    }.contains(u)) {
      return 'Under Ground';
    }
    if (const {
      'OVER GROUND', 'OVERGROUND', 'OVER-GROUND',
      'OH', 'OH LINE', 'O.H. LINE',
      'OVERHEAD', 'OVERHEAD LINE',
    }.contains(u)) {
      return 'Over Ground';
    }
    return t;
  }
  static const meterStatuses = ['Installed', 'Not Installed', 'Meter Missing'];
  static const oldConditions = ['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'];
  static const meterMakes = ['L&T Schneider', 'HPL', 'Visiontek'];
  static const oldMakes = ['SECURE', 'Secure', 'HPL', 'Visiontek', 'Other'];
  static const capacities = ['25', '63', '100', '200', '315', '500', '630'];

  /// External CT Ratio + Meter MF presets (stored as separate `new_meter_ct_ratio` / `new_meter_mf`).
  static const externalCtChoices = <({String label, String ratio, String mf})>[
    (label: '100/5 MF', ratio: '100/5', mf: '20'),
    (label: '200/5 MF', ratio: '200/5', mf: '40'),
    (label: '300/5 MF', ratio: '300/5', mf: '60'),
    (label: '500/5 MF', ratio: '500/5', mf: '100'),
    (label: '600/5 MF', ratio: '600/5', mf: '120'),
  ];
  String? externalCtLabel;

  @override
  void initState() {
    super.initState();
    localId = widget.localId;
    serverId = widget.serverId;
    resolvedFeederSurveyId = widget.feederSurveyId;
    if (widget.prefill != null) {
      final fid = widget.prefill!['feeder_survey_id'];
      resolvedFeederSurveyId ??= fid is int ? fid : int.tryParse('$fid');
    }
    _boot();
  }

  @override
  void dispose() {
    _clockTimer?.cancel();
    latCtrl.dispose();
    lngCtrl.dispose();
    gpsCtrl.dispose();
    oldMsnCtrl.dispose();
    newMsnCtrl.dispose();
    ctCtrl.dispose();
    mfCtrl.dispose();
    obsCtrl.dispose();
    super.dispose();
  }

  List<SeasSelectOption> _mapOpts(List<Map<String, dynamic>> rows, {String Function(Map<String, dynamic>)? label, String Function(Map<String, dynamic>)? subtitle}) {
    return rows
        .map((e) => SeasSelectOption(
              value: e['id'],
              label: label?.call(e) ?? '${e['name']}',
              subtitle: subtitle?.call(e),
            ))
        .toList();
  }

  List<SeasSelectOption> _stringOpts(List<String> rows) => rows.map((e) => SeasSelectOption(value: e, label: e)).toList();

  void _applyZone(int? id) {
    zoneId = id;
    if (id == null) return;
    final ancestry = hierarchyCache.ancestryForZone(id);
    if (ancestry == null) return;
    regionId = ancestry['region_id'];
    circleId = ancestry['circle_id'];
    divisionId = ancestry['division_id'];
  }

  void _maybeAutofillSingleZone() {
    final zones = hierarchyCache.allZones();
    if (zones.length != 1) return;
    final id = zones.first['id'];
    final zid = id is int ? id : int.tryParse('$id');
    if (zid == null) return;
    _applyZone(zid);
  }

  Future<void> _boot() async {
    try {
      user = await loadSavedUser();
      await hierarchyCache.ensureLoaded();
      if (localId != null) {
        final drafts = await offlineQueue.all();
        final match = drafts.where((e) => e.localId == localId);
        if (match.isNotEmpty) _hydrateFromDraft(match.first);
      } else if (serverId != null) {
        await _hydrateFromServer(serverId!);
      } else if (widget.autofetch && widget.prefill != null) {
        final p = widget.prefill!;
        int? asInt(dynamic v) => v is int ? v : int.tryParse('$v');
        regionId = asInt(p['region_id']);
        circleId = asInt(p['circle_id']);
        divisionId = asInt(p['division_id']);
        zoneId = asInt(p['zone_id']);
        substationId = asInt(p['substation_id']);
        feederId = asInt(p['feeder_id']);
        feederCode = '${p['feeder_code'] ?? ''}';
        feederName = '${p['feeder_name'] ?? ''}';
      } else {
        _maybeAutofillSingleZone();
      }
      await _captureGps();
      syncService.syncPending();
      if (widget.autofetch && feederId != null) {
        await _refreshFeederSurveyStatus();
      }
      if (feederId != null) {
        await _loadActiveDtrOptions(feederId);
      }
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => booting = false);
    }
  }

  Future<void> _refreshFeederSurveyStatus() async {
    final id = feederId;
    if (id == null) {
      if (mounted) {
        setState(() {
          feederSurveyDone = null;
          feederSurveyStatusLoading = false;
          standaloneSurveyedDtrIds = {};
          surveyedDtrIds = {};
        });
      }
      return;
    }
    if (mounted) setState(() => feederSurveyStatusLoading = true);
    try {
      final res = await api.get('/feeder-surveys/status?feeder_id=$id');
      final surveyed = res['surveyed'] == true;
      final rawStandalone = (res['standalone_surveyed_dtr_ids'] as List?) ?? [];
      final rawSurveyed = (res['surveyed_dtr_ids'] as List?) ?? rawStandalone;
      final standaloneIds =
          rawStandalone.map((e) => e is int ? e : int.tryParse('$e')).whereType<int>().toSet();
      final surveyedIds =
          rawSurveyed.map((e) => e is int ? e : int.tryParse('$e')).whereType<int>().toSet();
      if (mounted) {
        setState(() {
          feederSurveyDone = surveyed;
          feederSurveyStatusLoading = false;
          resolvedFeederSurveyId ??= (res['survey_id'] as num?)?.toInt();
          dtrsExpected = (res['dtrs_expected'] as num?)?.toInt() ?? 0;
          dtrsCompleted = (res['dtrs_completed'] as num?)?.toInt() ?? 0;
          standaloneSurveyedDtrIds = standaloneIds;
          surveyedDtrIds = surveyedIds;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          feederSurveyDone = null;
          feederSurveyStatusLoading = false;
        });
      }
    }
  }

  Future<void> _loadActiveDtrOptions(int? fid) async {
    if (fid == null) {
      if (mounted) {
        setState(() {
          _activeDtrOptions = [];
          _activeDtrOptionsLoaded = false;
        });
      }
      return;
    }
    final list = await hierarchyCache.ensureDtrs(fid, excludeSurveyed: true);
    if (!mounted) return;
    setState(() {
      _activeDtrOptions = list;
      _activeDtrOptionsLoaded = true;
    });
  }

  List<Map<String, dynamic>> _dtrOptionsForFeeder() {
    if (_activeDtrOptionsLoaded) return _activeDtrOptions;
    final all = hierarchyCache.dtrs(feederId);
    if (surveyedDtrIds.isEmpty && standaloneSurveyedDtrIds.isEmpty) return all;
    final hide = surveyedDtrIds.isNotEmpty ? surveyedDtrIds : standaloneSurveyedDtrIds;
    return all.where((e) {
      final id = e['id'];
      final asInt = id is int ? id : int.tryParse('$id');
      if (asInt == null) return true;
      return !hide.contains(asInt);
    }).toList();
  }

  Future<void> _hydrateFromServer(int id) async {
    final res = await api.get('/surveys/$id');
    final s = Map<String, dynamic>.from(res['survey'] as Map);
    regionId = s['region_id'] as int?;
    circleId = s['circle_id'] as int?;
    divisionId = s['division_id'] as int?;
    zoneId = s['zone_id'] as int?;
    substationId = s['substation_id'] as int?;
    feederId = s['feeder_id'] as int?;
    dtrId = s['dtr_id'] as int?;
    latCtrl.text = '${s['latitude'] ?? ''}';
    lngCtrl.text = '${s['longitude'] ?? ''}';
    gpsCtrl.text = s['gps_accuracy'] != null ? '${s['gps_accuracy']} m' : '';
    capacity = s['dtr_capacity_kva']?.toString();
    dtrCondition = '${s['dtr_condition'] ?? 'Normal'}';
    ltLineType = _normalizeLtLineType(s['lt_line_type']?.toString());
    smartMeterStatus = '${s['smart_meter_status'] ?? 'Installed'}';
    oldMeterCondition = s['old_meter_condition']?.toString();
    oldMeterMake = s['old_meter_make']?.toString();
    oldMsnCtrl.text = '${s['old_msn'] ?? ''}';
    newMsnCtrl.text = '${s['new_msn'] ?? ''}';
    final nmm = s['new_meter_make']?.toString();
    newMeterMake = nmm == 'LNT' ? 'L&T Schneider' : nmm;
    ctCtrl.text = '${s['new_meter_ct_ratio'] ?? ''}';
    mfCtrl.text = '${s['new_meter_mf'] ?? ''}';
    obsCtrl.text = '${s['observation'] ?? ''}';
    serverHasDtrPhoto = (s['dtr_overall_photo']?.toString() ?? '').trim().isNotEmpty;
    serverHasMeterPhoto = (s['smart_meter_photo']?.toString() ?? '').trim().isNotEmpty;
    final mStatus = s['mapping_correction_status']?.toString();
    if (mStatus == 'pending' || mStatus == 'approved' || mStatus == 'rejected') {
      isMappingCorrection = mStatus == 'pending';
      masterFeederId = (s['master_feeder_id'] as num?)?.toInt();
      reportedFeederId = (s['reported_feeder_id'] as num?)?.toInt();
      fieldDtrName = s['field_dtr_name']?.toString();
    } else {
      _clearMappingCorrection();
    }
    _syncExternalCtSelection();
    _syncFeederDtrLabels();
  }

  void _hydrateFromDraft(OfflineDraft d) {
    final f = d.fields;
    regionId = int.tryParse(f['region_id'] ?? '');
    circleId = int.tryParse(f['circle_id'] ?? '');
    divisionId = int.tryParse(f['division_id'] ?? '');
    zoneId = int.tryParse(f['zone_id'] ?? '');
    substationId = int.tryParse(f['substation_id'] ?? '');
    feederId = int.tryParse(f['feeder_id'] ?? '');
    dtrId = int.tryParse(f['dtr_id'] ?? '');
    latCtrl.text = f['latitude'] ?? '';
    lngCtrl.text = f['longitude'] ?? '';
    gpsCtrl.text = f['gps_accuracy'] ?? '';
    capacity = f['dtr_capacity_kva'];
    dtrCondition = f['dtr_condition'] ?? 'Normal';
    ltLineType = _normalizeLtLineType(f['lt_line_type']);
    smartMeterStatus = f['smart_meter_status'] ?? 'Installed';
    oldMeterCondition = f['old_meter_condition'];
    oldMeterMake = f['old_meter_make'];
    oldMsnCtrl.text = f['old_msn'] ?? '';
    newMsnCtrl.text = f['new_msn'] ?? '';
    newMeterMake = f['new_meter_make'];
    ctCtrl.text = f['new_meter_ct_ratio'] ?? '';
    mfCtrl.text = f['new_meter_mf'] ?? '';
    obsCtrl.text = f['observation'] ?? '';
    isMappingCorrection = f['mapping_correction'] == '1' || f['mapping_correction'] == 'true';
    masterFeederId = int.tryParse(f['master_feeder_id'] ?? '');
    reportedFeederId = int.tryParse(f['reported_feeder_id'] ?? '');
    fieldDtrName = f['field_dtr_name'];
    if (!isMappingCorrection) {
      _clearMappingCorrection();
    }
    dtrPhotoPath = d.dtrPhotoPath;
    meterPhotoPath = d.meterPhotoPath;
    serverId = d.serverId;
    _syncExternalCtSelection();
    _syncFeederDtrLabels();
  }

  void _syncExternalCtSelection() {
    final ratio = ctCtrl.text.trim();
    final mf = mfCtrl.text.trim();
    final match = externalCtChoices.where((e) => e.ratio == ratio && e.mf == mf);
    if (match.isNotEmpty) {
      externalCtLabel = match.first.label;
      return;
    }
    final byRatio = externalCtChoices.where((e) => e.ratio == ratio);
    if (byRatio.isNotEmpty) {
      final c = byRatio.first;
      externalCtLabel = c.label;
      mfCtrl.text = c.mf;
      return;
    }
    externalCtLabel = null;
  }

  void _applyExternalCt(String label) {
    final match = externalCtChoices.where((e) => e.label == label);
    if (match.isEmpty) return;
    final c = match.first;
    setState(() {
      externalCtLabel = c.label;
      ctCtrl.text = c.ratio;
      mfCtrl.text = c.mf;
    });
  }

  void _syncFeederDtrLabels() {
    final feeders = hierarchyCache.feeders(substationId);
    final fMatch = feeders.where((e) => e['id'] == feederId);
    if (fMatch.isNotEmpty) {
      final f = fMatch.first;
      feederCode = '${f['code'] ?? ''}';
      feederName = '${f['name'] ?? ''}';
    }
    final dtrs = hierarchyCache.dtrs(feederId);
    final dMatch = dtrs.where((e) => e['id'] == dtrId);
    if (dMatch.isNotEmpty) {
      final d = dMatch.first;
      dtrCode = '${d['code'] ?? ''}';
      dtrName = '${d['name'] ?? ''}';
      capacity ??= d['capacity_kva']?.toString();
    }
  }

  Future<void> _captureGps() async {
    setState(() => gpsLoading = true);
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        return;
      }
      final pos = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high));
      if (!mounted) return;
      setState(() {
        latCtrl.text = pos.latitude.toStringAsFixed(7);
        lngCtrl.text = pos.longitude.toStringAsFixed(7);
        gpsCtrl.text = '${pos.accuracy.toStringAsFixed(1)} m';
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => gpsLoading = false);
    }
  }

  Future<void> _pickPhoto(String kind) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        decoration: const BoxDecoration(color: SeasColors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(width: 40, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99))),
            const SizedBox(height: 16),
            Text('Add photo', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18)),
            const SizedBox(height: 12),
            ListTile(
              leading: const CircleAvatar(backgroundColor: SeasColors.voltSoft, child: Icon(Icons.photo_camera, color: SeasColors.volt)),
              title: const Text('Camera', style: TextStyle(fontWeight: FontWeight.w700)),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const CircleAvatar(backgroundColor: SeasColors.canvas, child: Icon(Icons.photo_library_outlined)),
              title: const Text('Gallery', style: TextStyle(fontWeight: FontWeight.w700)),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final file = await _picker.pickImage(source: source, imageQuality: 85, maxWidth: 1600);
    if (file == null) return;
    Uint8List? bytes;
    try {
      bytes = await file.readAsBytes();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      switch (kind) {
        case 'dtr':
          dtrPhotoPath = file.path;
          dtrPhotoWeb = file;
          dtrPhotoBytes = bytes;
        case 'meter':
          meterPhotoPath = file.path;
          meterPhotoWeb = file;
          meterPhotoBytes = bytes;
        case 'ct':
          ctPhotoPath = file.path;
          ctPhotoWeb = file;
          ctPhotoBytes = bytes;
      }
    });
  }

  Future<void> _openAddNewDtr() async {
    if (feederId == null) {
      setState(() => error = 'Please select a Feeder Code first, then use Add New DTR.');
      return;
    }
    await _captureGps();
    if (!mounted) return;
    final created = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddNewDtrSheet(
        feederId: feederId!,
        feederName: feederName.isEmpty ? feederCode : feederName,
        lat: latCtrl.text,
        lng: lngCtrl.text,
        gps: gpsCtrl.text,
        onRefreshGps: _captureGps,
      ),
    );
    if (created == null) return;

    try {
      final online = await syncService.isOnline;
      Map<String, dynamic> dtr;
      var remapped = false;

      if (online) {
        final res = await api.post('/hierarchy/dtrs', {
          'feeder_id': feederId,
          'name': created['name'],
          'code': created['code'],
          if (created['capacity_kva'] != null) 'capacity_kva': int.tryParse('${created['capacity_kva']}'),
        });
        dtr = Map<String, dynamic>.from(res['dtr'] as Map);
        remapped = res['remapped'] == true;
      } else {
        dtr = {
          'id': -DateTime.now().millisecondsSinceEpoch,
          'name': created['name'],
          'code': created['code'],
          'capacity_kva': created['capacity_kva'],
          'feeder_id': feederId,
          '_pending_create': true,
        };
      }

      await hierarchyCache.addDtr(feederId!, dtr);
      setState(() {
        dtrId = (dtr['id'] as num?)?.toInt();
        dtrName = '${created['name'] ?? dtr['name']}';
        dtrCode = '${dtr['code']}';
        capacity = created['capacity_kva']?.toString() ?? dtr['capacity_kva']?.toString();
        // Overwrite/remap is product rule — do not enter mapping-correction pending UX.
        _clearMappingCorrection();
        error = null;
      });
      if (created['lat'] != null) {
        latCtrl.text = '${created['lat']}';
        lngCtrl.text = '${created['lng']}';
        gpsCtrl.text = '${created['gps']}';
      } else {
        await _captureGps();
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(
          !online
              ? 'New DTR saved locally (will sync online)'
              : (remapped
                  ? 'DTR updated & mapped to this feeder — continue survey'
                  : 'New DTR added & selected'),
        ),
        backgroundColor: SeasColors.ink950,
      ));
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  void _clearMappingCorrection() {
    isMappingCorrection = false;
    masterFeederId = null;
    reportedFeederId = null;
    fieldDtrName = null;
    mappedFeederLabel = null;
    mappedSubstationLabel = null;
    mappedCapacityLabel = null;
  }

  Map<String, String> _fieldsMap() {
    final gpsNum = gpsCtrl.text.replaceAll(' m', '').trim();
    final lat = latCtrl.text.trim();
    final lng = lngCtrl.text.trim();
    final entrySource = widget.fromFeederFlow ? 'feeder' : 'standalone';
    return {
      if (regionId != null) 'region_id': '$regionId',
      if (circleId != null) 'circle_id': '$circleId',
      if (divisionId != null) 'division_id': '$divisionId',
      if (zoneId != null) 'zone_id': '$zoneId',
      if (substationId != null) 'substation_id': '$substationId',
      if (feederId != null) 'feeder_id': '$feederId',
      if (dtrId != null) 'dtr_id': '$dtrId',
      // Omit empty GPS so Laravel nullable|numeric does not see "".
      if (lat.isNotEmpty) 'latitude': lat,
      if (lng.isNotEmpty) 'longitude': lng,
      if (gpsNum.isNotEmpty) 'gps_accuracy': gpsNum,
      if (capacity != null && capacity!.isNotEmpty) 'dtr_capacity_kva': capacity!,
      'dtr_condition': dtrCondition,
      if (ltLineType != null && ltLineType!.trim().isNotEmpty) 'lt_line_type': ltLineType!.trim(),
      'smart_meter_status': smartMeterStatus,
      if (oldMeterCondition != null) 'old_meter_condition': oldMeterCondition!,
      if (oldMsnCtrl.text.trim().isNotEmpty) 'old_msn': oldMsnCtrl.text.trim(),
      if (oldMeterMake != null) 'old_meter_make': oldMeterMake!,
      if (newMsnCtrl.text.trim().isNotEmpty) 'new_msn': newMsnCtrl.text.trim(),
      if (newMeterMake != null) 'new_meter_make': newMeterMake!,
      if (ctCtrl.text.trim().isNotEmpty) 'new_meter_ct_ratio': ctCtrl.text.trim(),
      if (mfCtrl.text.trim().isNotEmpty) 'new_meter_mf': mfCtrl.text.trim(),
      if (obsCtrl.text.trim().isNotEmpty) 'observation': obsCtrl.text.trim(),
      'entry_source': entrySource,
      if (widget.fromFeederFlow && resolvedFeederSurveyId != null)
        'feeder_survey_id': '$resolvedFeederSurveyId',
      if (isMappingCorrection) 'mapping_correction': '1',
      if (isMappingCorrection && masterFeederId != null) 'master_feeder_id': '$masterFeederId',
      if (isMappingCorrection && reportedFeederId != null) 'reported_feeder_id': '$reportedFeederId',
      if (isMappingCorrection && fieldDtrName != null && fieldDtrName!.isNotEmpty) 'field_dtr_name': fieldDtrName!,
    };
  }

  bool get _hasDtrPhoto =>
      dtrPhotoPath != null || dtrPhotoBytes != null || serverHasDtrPhoto;

  bool get _hasMeterPhoto =>
      meterPhotoPath != null || meterPhotoBytes != null || serverHasMeterPhoto;

  String? _validate(bool submit) {
    if (regionId == null || circleId == null || divisionId == null || zoneId == null || substationId == null) {
      return 'Please complete Location Details (Region → Substation).';
    }
    if (feederId == null) return 'Please select Feeder Code.';
    if (dtrId == null) return 'Please select or add a DTR.';
    if (dtrId != null && dtrId! < 0 && submit) {
      return 'New offline DTR needs internet once to create, then you can submit.';
    }
    if (submit) {
      if (ltLineType == null || ltLineType!.isEmpty) {
        return 'Please select Line Type (Under Ground / Over Ground).';
      }
      if (smartMeterStatus != 'Meter Missing') {
        if (newMsnCtrl.text.trim().isEmpty || newMeterMake == null || ctCtrl.text.trim().isEmpty || mfCtrl.text.trim().isEmpty) {
          return 'New Smart Meter details are required for submission.';
        }
      }
      if (smartMeterStatus == 'Not Installed') {
        if (oldMeterCondition == null || oldMsnCtrl.text.trim().isEmpty || oldMeterMake == null) {
          return 'Old Meter details are required when Smart Meter is Not Installed.';
        }
      }
      if (!_hasDtrPhoto) {
        return 'DTR Overall Photo is required.';
      }
      if (smartMeterStatus != 'Meter Missing' && !_hasMeterPhoto) {
        return 'Smart Meter Photo is required (or choose Meter Missing).';
      }
    }
    return null;
  }

  Future<void> _guardExistingAudit(int selectedDtrId) async {
    if (serverId != null) return; // already editing an existing survey
    try {
      final res = await api.get('/surveys/by-dtr?dtr_id=$selectedDtrId');
      if (res['exists'] != true) return;
      if (res['blocked'] == true) {
        if (!mounted) return;
        await showDialog<void>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text('DTR Already Surveyed', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
            content: Text(
              res['message']?.toString() ??
                  'This DTR was already surveyed. Duplicate DTR survey is not allowed.',
            ),
            actions: [
              FilledButton(
                onPressed: () => Navigator.pop(ctx),
                style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                child: const Text('OK'),
              ),
            ],
          ),
        );
        if (!mounted) return;
        setState(() {
          dtrId = null;
          dtrCode = '';
          dtrName = '';
          capacity = null;
        });
        return;
      }
      if (res['survey'] is! Map) return;
      final existing = Map<String, dynamic>.from(res['survey'] as Map);
      final existingId = existing['id'] as int?;
      final status = existing['status']?.toString() ?? '';
      if (existingId == null || !mounted) return;

      final canEdit = status == 'draft' || status == 'rejected' || status == 'pending_approval';
      final open = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text('DTR already audited', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
          content: Text(
            canEdit
                ? 'You already surveyed this DTR. You cannot start a new audit. Open the existing survey to edit and resubmit for review.'
                : 'This DTR was already audited (${status.replaceAll('_', ' ')}). A fresh audit is not allowed.',
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            if (canEdit)
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                child: const Text('Open & Edit'),
              ),
          ],
        ),
      );

      if (!mounted) return;
      setState(() {
        dtrId = null;
        dtrCode = '';
        dtrName = '';
        capacity = null;
      });

      if (open == true && canEdit) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => DtrSurveyFormScreen(serverId: existingId)),
        );
      }
    } catch (_) {
      // offline — lock enforced on server when saving
    }
  }

  Future<void> _save({required bool submit}) async {
    final v = _validate(submit);
    if (v != null) {
      setState(() => error = v);
      return;
    }
    if (submit) {
      final ok = await confirmSubmit(
        context,
        message: 'Are you sure you want to submit this DTR survey?',
      );
      if (!ok || !mounted) return;
    }
    setState(() {
      saving = true;
      error = null;
      surveyedAt = DateTime.now();
    });

    final fields = _fieldsMap();
    final action = submit ? 'submit' : 'draft';
    final online = await syncService.isOnline;

    try {
      if (!online) {
        final draft = await offlineQueue.upsert(
          localId: localId,
          serverId: serverId,
          fields: fields,
          action: action,
          dtrPhotoPath: dtrPhotoPath,
          meterPhotoPath: meterPhotoPath,
        );
        localId = draft.localId;
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(submit
              ? 'Saved offline — will submit when internet returns (Consumer Survey unlocks after sync).'
              : 'Draft saved offline. Will sync when internet is available.'),
          backgroundColor: SeasColors.ink950,
        ));
        if (submit) Navigator.pop(context, true);
        return;
      }

      final path = serverId == null ? '/surveys' : '/surveys/$serverId';
      fields['action'] = action;
      List<int>? dtrBytes = dtrPhotoBytes;
      List<int>? meterBytes = meterPhotoBytes;
      List<int>? ctBytes = ctPhotoBytes;
      if (kIsWeb) {
        if (dtrBytes == null && dtrPhotoWeb != null) dtrBytes = await dtrPhotoWeb!.readAsBytes();
        if (meterBytes == null && meterPhotoWeb != null) meterBytes = await meterPhotoWeb!.readAsBytes();
        if (ctBytes == null && ctPhotoWeb != null) ctBytes = await ctPhotoWeb!.readAsBytes();
      }
      final res = await api.postSurveyMultipart(
        path: path,
        fields: fields,
        dtrPhotoPath: kIsWeb ? null : dtrPhotoPath,
        meterPhotoPath: kIsWeb ? null : meterPhotoPath,
        ctPhotoPath: kIsWeb ? null : ctPhotoPath,
        dtrPhotoBytes: dtrBytes,
        meterPhotoBytes: meterBytes,
        ctPhotoBytes: ctBytes,
      );

      final survey = res['survey'];
      if (survey is Map && survey['id'] != null) {
        serverId = survey['id'] as int;
      }
      if (localId != null) await offlineQueue.remove(localId!);

      if (!mounted) return;
      if (submit && (widget.fromFeederFlow || widget.prefill != null)) {
        final label = [dtrCode, dtrName].where((e) => e.trim().isNotEmpty).join(' - ');
        final nextPrefill = <String, dynamic>{
          ...(widget.prefill ?? {}),
          'region_id': regionId,
          'circle_id': circleId,
          'division_id': divisionId,
          'zone_id': zoneId,
          'substation_id': substationId,
          'feeder_id': feederId,
          'feeder_code': feederCode,
          'feeder_name': feederName,
          if (resolvedFeederSurveyId != null) 'feeder_survey_id': resolvedFeederSurveyId,
        };
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => DtrFeederSuccessScreen(
              prefill: nextPrefill,
              feederSurveyId: resolvedFeederSurveyId,
              dtrLabel: label.isEmpty ? null : label,
              message: res['message']?.toString(),
            ),
          ),
        );
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(res['message']?.toString() ?? (submit ? 'Submitted' : 'Draft saved')),
        backgroundColor: SeasColors.ink950,
      ));
      if (submit) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (e.statusCode == 409 && mounted) {
        final existingRaw = e.body['existing_survey_id'];
        final existingId = existingRaw is int ? existingRaw : int.tryParse('$existingRaw');
        final friendly = e.body['message']?.toString() ??
            'This DTR was already surveyed. Duplicate survey is not allowed.';
        final open = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('DTR Already Surveyed'),
            content: Text(friendly),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('OK')),
              if (existingId != null)
                FilledButton(
                  onPressed: () => Navigator.pop(ctx, true),
                  style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                  child: const Text('Open existing'),
                ),
            ],
          ),
        );
        if (open == true && existingId != null && mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => DtrSurveyFormScreen(serverId: existingId)),
          );
        }
        return;
      }
      // 4xx (validation / auth / forbidden) — show real error; do not fake offline success.
      if (e.statusCode >= 400 && e.statusCode < 500) {
        if (!mounted) return;
        setState(() => error = e.message);
        return;
      }
      final draft = await offlineQueue.upsert(
        localId: localId,
        serverId: serverId,
        fields: fields,
        action: action,
        dtrPhotoPath: dtrPhotoPath,
        meterPhotoPath: meterPhotoPath,
      );
      localId = draft.localId;
      if (!mounted) return;
      setState(() => error = 'Saved offline (sync pending): ${e.message}');
    } catch (e) {
      final draft = await offlineQueue.upsert(
        localId: localId,
        serverId: serverId,
        fields: fields,
        action: action,
        dtrPhotoPath: dtrPhotoPath,
        meterPhotoPath: meterPhotoPath,
      );
      localId = draft.localId;
      if (!mounted) return;
      setState(() => error = 'Saved offline (sync pending): ${e.toString().replaceFirst('Exception: ', '')}');
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final stepDone = [
      regionId != null,
      feederId != null,
      dtrId != null,
      smartMeterStatus.isNotEmpty,
      _hasDtrPhoto && (smartMeterStatus == 'Meter Missing' || _hasMeterPhoto),
    ].where((e) => e).length;

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      body: booting
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                _FormHeader(
                  saving: saving,
                  stepDone: stepDone,
                  executiveName: user?['name']?.toString() ?? 'Executive',
                  onBack: () => Navigator.pop(context),
                ),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    children: [
                      if (error != null)
                        Container(
                          padding: const EdgeInsets.all(14),
                          margin: const EdgeInsets.only(bottom: 12),
                          decoration: BoxDecoration(
                            color: SeasColors.voltSoft,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: const Color(0x33E10600)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.error_outline, color: SeasColors.volt),
                              const SizedBox(width: 10),
                              Expanded(child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600))),
                            ],
                          ),
                        ),

                      if (!hierarchyCache.hasAssignedFeeders)
                        Container(
                          padding: const EdgeInsets.all(16),
                          margin: const EdgeInsets.only(bottom: 12),
                          decoration: BoxDecoration(
                            color: SeasColors.voltSoft,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: SeasColors.volt.withValues(alpha: 0.25)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('No feeders assigned', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: SeasColors.volt)),
                              const SizedBox(height: 4),
                              Text(
                                'Contact your manager. You can survey only feeders assigned to you.',
                                style: GoogleFonts.plusJakartaSans(fontSize: 13, color: SeasColors.ink700, height: 1.35),
                              ),
                            ],
                          ),
                        ),

                      _SectionCard(
                        step: '01',
                        title: 'Location Details',
                        subtitle: 'Cascade down to feeder',
                        child: Column(children: [
                          SeasSelectField(
                            label: 'Region',
                            hint: 'Select region',
                            leadingIcon: Icons.map_outlined,
                            value: regionId,
                            options: _mapOpts(hierarchyCache.regions),
                            onSelected: (o) => setState(() {
                              regionId = o.value as int?;
                              circleId = divisionId = zoneId = substationId = feederId = dtrId = null;
                              feederCode = feederName = dtrCode = dtrName = '';
                              feederSurveyDone = null;
                            }),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'Circle',
                            hint: regionId == null ? 'Select region first' : 'Select circle',
                            enabled: regionId != null,
                            leadingIcon: Icons.radio_button_checked,
                            value: circleId,
                            options: _mapOpts(hierarchyCache.circles(regionId)),
                            onSelected: (o) => setState(() {
                              circleId = o.value as int?;
                              divisionId = zoneId = substationId = feederId = dtrId = null;
                              feederCode = feederName = dtrCode = dtrName = '';
                              feederSurveyDone = null;
                            }),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'Division',
                            hint: circleId == null ? 'Select circle first' : 'Select division',
                            enabled: circleId != null,
                            leadingIcon: Icons.account_tree_outlined,
                            value: divisionId,
                            options: _mapOpts(hierarchyCache.divisions(circleId)),
                            onSelected: (o) => setState(() {
                              divisionId = o.value as int?;
                              zoneId = substationId = feederId = dtrId = null;
                              feederCode = feederName = dtrCode = dtrName = '';
                              feederSurveyDone = null;
                            }),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'Zone / DC',
                            hint: divisionId == null ? 'Select division first' : 'Select zone',
                            enabled: divisionId != null,
                            leadingIcon: Icons.location_city_outlined,
                            value: zoneId,
                            options: _mapOpts(hierarchyCache.zones(divisionId)),
                            onSelected: (o) => setState(() {
                              _applyZone(o.value as int?);
                              substationId = feederId = dtrId = null;
                              feederCode = feederName = dtrCode = dtrName = '';
                              feederSurveyDone = null;
                            }),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'Substation',
                            hint: zoneId == null ? 'Select zone first' : 'Select substation',
                            enabled: zoneId != null,
                            leadingIcon: Icons.bolt_outlined,
                            value: substationId,
                            options: _mapOpts(hierarchyCache.substations(zoneId)),
                            onSelected: (o) => setState(() {
                              substationId = o.value as int?;
                              feederId = dtrId = null;
                              feederCode = feederName = dtrCode = dtrName = '';
                              feederSurveyDone = null;
                            }),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'Feeder Code',
                            hint: substationId == null
                                ? 'Select substation first'
                                : hierarchyCache.feeders(substationId).isEmpty
                                    ? 'No feeders assigned. Contact your manager.'
                                    : 'Select feeder code',
                            enabled: substationId != null && hierarchyCache.feeders(substationId).isNotEmpty,
                            leadingIcon: Icons.tag,
                            value: feederId,
                            options: _mapOpts(
                              hierarchyCache.feeders(substationId),
                              label: (e) => '${e['code']}',
                              subtitle: (e) => '${e['name']}',
                            ),
                            onSelected: (o) {
                              final match = hierarchyCache.feeders(substationId).where((e) => e['id'] == o.value);
                              final f = match.isEmpty ? null : match.first;
                              setState(() {
                                feederId = o.value as int?;
                                feederCode = '${f?['code'] ?? ''}';
                                feederName = '${f?['name'] ?? ''}';
                                dtrId = null;
                                dtrCode = dtrName = '';
                                capacity = null;
                                _clearMappingCorrection();
                                feederSurveyDone = null;
                                standaloneSurveyedDtrIds = {};
                                surveyedDtrIds = {};
                                _activeDtrOptions = [];
                                _activeDtrOptionsLoaded = false;
                              });
                              if (widget.autofetch) {
                                _refreshFeederSurveyStatus();
                              }
                              final fid = o.value as int?;
                              if (fid != null) {
                                _loadActiveDtrOptions(fid);
                              }
                            },
                          ),
                          const SizedBox(height: 12),
                          _AutoChip(
                            icon: Icons.electrical_services_rounded,
                            label: 'Feeder Name (Auto)',
                            value: feederName.isEmpty ? 'Select feeder code above' : feederName,
                            filled: feederName.isNotEmpty,
                          ),
                          if (widget.autofetch && feederId != null) ...[
                            const SizedBox(height: 12),
                            _FeederSurveyStatusNote(
                              loading: feederSurveyStatusLoading,
                              surveyed: feederSurveyDone,
                            ),
                          ],
                        ]),
                      ),

                      _SectionCard(
                        step: '02',
                        title: 'DTR Information',
                        subtitle: 'Select existing or add new',
                        trailing: TextButton.icon(
                          onPressed: _openAddNewDtr,
                          icon: const Icon(Icons.add_circle_outline, size: 18, color: SeasColors.volt),
                          label: Text('Add New DTR', style: GoogleFonts.plusJakartaSans(color: SeasColors.volt, fontWeight: FontWeight.w800, fontSize: 12)),
                        ),
                        child: Column(children: [
                          if (isMappingCorrection) ...[
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(12),
                              margin: const EdgeInsets.only(bottom: 12),
                              decoration: BoxDecoration(
                                color: SeasColors.warningSoft,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: const Color(0xFFFCD34D)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      const SeasBadge('Mapping Correction', tone: SeasBadgeTone.warning),
                                      const Spacer(),
                                      Text(
                                        'Admin review',
                                        style: GoogleFonts.plusJakartaSans(fontSize: 11, fontWeight: FontWeight.w700, color: SeasColors.warning),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    'Reported under current feeder. Master mapping stays on ${mappedFeederLabel ?? 'another feeder'} until admin approves.',
                                    style: const TextStyle(color: SeasColors.warning, fontSize: 12, height: 1.35, fontWeight: FontWeight.w600),
                                  ),
                                  if (mappedSubstationLabel != null && mappedSubstationLabel!.isNotEmpty)
                                    Text('Master SS: $mappedSubstationLabel', style: const TextStyle(color: SeasColors.ink400, fontSize: 11)),
                                ],
                              ),
                            ),
                          ],
                          SeasSelectField(
                            label: 'DTR Name',
                            hint: feederId == null
                                ? 'Select feeder first'
                                : (_dtrOptionsForFeeder().isEmpty
                                    ? 'No remaining DTRs (already surveyed / pending)'
                                    : 'Select DTR name'),
                            enabled: feederId != null,
                            leadingIcon: Icons.factory_outlined,
                            value: dtrId,
                            options: _mapOpts(
                              _dtrOptionsForFeeder(),
                              label: (e) => '${e['name']}',
                              subtitle: (e) => 'Code ${e['code']} · ${e['capacity_kva'] ?? '—'} kVA',
                            ),
                            onSelected: (o) async {
                              final match = _dtrOptionsForFeeder().where((e) => e['id'] == o.value);
                              final d = match.isEmpty ? null : match.first;
                              final selectedId = o.value as int?;
                              if (selectedId != null &&
                                  (surveyedDtrIds.contains(selectedId) ||
                                      standaloneSurveyedDtrIds.contains(selectedId))) {
                                if (!mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                                  content: Text(
                                    'DTR Already Surveyed. It stays in Pending / Survey Status — not in the active list.',
                                  ),
                                  backgroundColor: SeasColors.volt,
                                ));
                                setState(() {
                                  dtrId = null;
                                  dtrCode = '';
                                  dtrName = '';
                                  capacity = null;
                                  _clearMappingCorrection();
                                });
                                return;
                              }
                              setState(() {
                                dtrId = selectedId;
                                dtrCode = '${d?['code'] ?? ''}';
                                dtrName = '${d?['name'] ?? ''}';
                                capacity = d?['capacity_kva']?.toString();
                                _clearMappingCorrection();
                              });
                              await _captureGps();
                              if (selectedId != null && selectedId > 0) {
                                await _guardExistingAudit(selectedId);
                              }
                            },
                          ),
                          const SizedBox(height: 12),
                          _AutoChip(icon: Icons.qr_code_2_rounded, label: 'DTR Code (Auto)', value: dtrCode.isEmpty ? 'Select / add DTR' : dtrCode, filled: dtrCode.isNotEmpty),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'DTR Capacity (kVA)',
                            hint: 'Select capacity',
                            leadingIcon: Icons.speed,
                            value: capacity,
                            options: _stringOpts(capacities),
                            onSelected: (o) => setState(() => capacity = o.value as String?),
                          ),
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(colors: [SeasColors.ink950, SeasColors.ink800]),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Column(
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.my_location, color: SeasColors.volt, size: 18),
                                    const SizedBox(width: 8),
                                    Text('GPS Location', style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800)),
                                    const Spacer(),
                                    TextButton(
                                      onPressed: gpsLoading ? null : _captureGps,
                                      child: Text(gpsLoading ? 'Reading…' : 'Refresh', style: const TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w800)),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                Row(children: [
                                  Expanded(child: _GpsMini(label: 'Latitude', value: latCtrl.text.isEmpty ? '—' : latCtrl.text)),
                                  const SizedBox(width: 8),
                                  Expanded(child: _GpsMini(label: 'Longitude', value: lngCtrl.text.isEmpty ? '—' : lngCtrl.text)),
                                  const SizedBox(width: 8),
                                  Expanded(child: _GpsMini(label: 'Accuracy', value: gpsCtrl.text.isEmpty ? '—' : gpsCtrl.text)),
                                ]),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'DTR Condition',
                            hint: 'Select condition',
                            leadingIcon: Icons.health_and_safety_outlined,
                            value: dtrCondition,
                            options: _stringOpts(dtrConditions),
                            onSelected: (o) => setState(() => dtrCondition = o.value as String),
                          ),
                          const SizedBox(height: 12),
                          SeasSelectField(
                            label: 'Line Type',
                            hint: 'Under Ground / Over Ground',
                            leadingIcon: Icons.electrical_services_rounded,
                            value: ltLineType,
                            options: _stringOpts(ltLineTypes),
                            onSelected: (o) => setState(() => ltLineType = o.value as String),
                          ),
                        ]),
                      ),

                      _SectionCard(
                        step: '03',
                        title: 'Smart Meter Status',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: meterStatuses.map((s) {
                            final selected = smartMeterStatus == s;
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Material(
                                color: selected ? SeasColors.volt : SeasColors.canvasSoft,
                                borderRadius: BorderRadius.circular(14),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(14),
                                  onTap: () => setState(() => smartMeterStatus = s),
                                  child: Padding(
                                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                                    child: Row(
                                      children: [
                                        Icon(
                                          selected ? Icons.radio_button_checked : Icons.radio_button_off,
                                          color: selected ? Colors.white : SeasColors.ink400,
                                          size: 20,
                                        ),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Text(
                                            s,
                                            style: GoogleFonts.plusJakartaSans(
                                              fontWeight: FontWeight.w800,
                                              fontSize: 14,
                                              color: selected ? Colors.white : SeasColors.ink950,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            );
                          }).toList(),
                        ),
                      ),

                      // Dynamic section numbers so sequence never jumps
                      ...() {
                        var n = 3;
                        String next() {
                          n += 1;
                          return n.toString().padLeft(2, '0');
                        }

                        // Section 03 already rendered above as Smart Meter Status
                        final widgets = <Widget>[];

                        if (smartMeterStatus == 'Not Installed') {
                          widgets.add(_SectionCard(
                            step: next(),
                            title: 'Old Meter Details',
                            subtitle: 'Required when smart meter is not installed',
                            child: Column(children: [
                              SeasSelectField(label: 'Old Meter Condition', hint: 'Select', value: oldMeterCondition, options: _stringOpts(oldConditions), onSelected: (o) => setState(() => oldMeterCondition = o.value as String)),
                              const SizedBox(height: 12),
                              SeasTextField(label: 'Old MSN', controller: oldMsnCtrl, hint: 'Enter old meter serial'),
                              const SizedBox(height: 12),
                              SeasSelectField(label: 'Old Meter Make', hint: 'Select', value: oldMeterMake, options: _stringOpts(oldMakes), onSelected: (o) => setState(() => oldMeterMake = o.value as String)),
                            ]),
                          ));
                        }

                        if (smartMeterStatus != 'Meter Missing') {
                          widgets.add(_SectionCard(
                            step: next(),
                            title: 'New Smart Meter Details',
                            subtitle: smartMeterStatus == 'Not Installed' ? 'New meter being installed' : 'Installed meter details',
                            child: Column(children: [
                              SeasTextField(label: 'New MSN', controller: newMsnCtrl, hint: 'PH00901054'),
                              const SizedBox(height: 12),
                              SeasSelectField(label: 'New Meter Make', hint: 'Select make', value: newMeterMake, options: _stringOpts(meterMakes), onSelected: (o) => setState(() => newMeterMake = o.value as String)),
                              const SizedBox(height: 12),
                              SeasSelectField(
                                label: 'External CT Ratio',
                                hint: 'Select CT ratio + MF',
                                leadingIcon: Icons.tune_rounded,
                                value: externalCtLabel,
                                options: externalCtChoices
                                    .map((e) => SeasSelectOption(value: e.label, label: e.label))
                                    .toList(),
                                onSelected: (o) => _applyExternalCt('${o.value}'),
                              ),
                              const SizedBox(height: 10),
                              _CtPhotoRow(path: ctPhotoPath, bytes: ctPhotoBytes, onTap: () => _pickPhoto('ct')),
                              const SizedBox(height: 12),
                              _AutoChip(
                                icon: Icons.calculate_outlined,
                                label: 'Meter MF (Auto)',
                                value: mfCtrl.text.trim().isEmpty ? 'Select CT ratio above' : mfCtrl.text.trim(),
                                filled: mfCtrl.text.trim().isNotEmpty,
                              ),
                            ]),
                          ));
                        }

                        widgets.add(_SectionCard(
                          step: next(),
                          title: 'Photo Capture',
                          child: Row(children: [
                            Expanded(child: _PhotoBox(
                              label: serverHasDtrPhoto && dtrPhotoPath == null ? 'DTR Overall (saved)' : 'DTR Overall',
                              path: dtrPhotoPath,
                              bytes: dtrPhotoBytes,
                              onTap: () => _pickPhoto('dtr'),
                            )),
                            const SizedBox(width: 12),
                            Expanded(child: _PhotoBox(
                              label: smartMeterStatus == 'Meter Missing'
                                  ? 'Smart Meter (optional)'
                                  : (serverHasMeterPhoto && meterPhotoPath == null ? 'Smart Meter (saved)' : 'Smart Meter'),
                              path: meterPhotoPath,
                              bytes: meterPhotoBytes,
                              onTap: () => _pickPhoto('meter'),
                            )),
                          ]),
                        ));

                        widgets.add(_SectionCard(
                          step: next(),
                          title: 'Observation',
                          child: SeasTextField(label: 'Remarks', controller: obsCtrl, hint: 'Enter observation / remarks…', maxLines: 4, maxLength: 500),
                        ));

                        return widgets;
                      }(),
                    ],
                  ),
                ),
                Container(
                  padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + MediaQuery.paddingOf(context).bottom),
                  decoration: const BoxDecoration(
                    color: SeasColors.white,
                    boxShadow: [BoxShadow(color: Color(0x140F172A), blurRadius: 20, offset: Offset(0, -6))],
                  ),
                  child: Row(children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: saving ? null : () => _save(submit: false),
                        icon: const Icon(Icons.save_outlined),
                        label: Text(saving ? 'Saving…' : 'Save Draft'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: SeasColors.ink950,
                          side: const BorderSide(color: SeasColors.ink200, width: 1.5),
                          padding: const EdgeInsets.symmetric(vertical: 15),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      flex: 1,
                      child: DecoratedBox(
                        decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), boxShadow: SeasShadows.glow),
                        child: FilledButton.icon(
                          onPressed: saving ? null : () => _save(submit: true),
                          icon: const Icon(Icons.send_rounded, size: 18),
                          label: const Text('Submit'),
                          style: FilledButton.styleFrom(
                            backgroundColor: SeasColors.volt,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                        ),
                      ),
                    ),
                  ]),
                ),
              ],
            ),
    );
  }
}

class _FeederSurveyStatusNote extends StatelessWidget {
  const _FeederSurveyStatusNote({required this.loading, required this.surveyed});

  final bool loading;
  final bool? surveyed;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: SeasColors.ink50,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: SeasColors.ink100),
        ),
        child: Row(
          children: [
            const SizedBox(
              height: 14,
              width: 14,
              child: CircularProgressIndicator(strokeWidth: 2, color: SeasColors.volt),
            ),
            const SizedBox(width: 10),
            Text(
              'Checking feeder survey status…',
              style: GoogleFonts.plusJakartaSans(fontSize: 12.5, fontWeight: FontWeight.w600, color: SeasColors.ink400),
            ),
          ],
        ),
      );
    }

    if (surveyed == null) return const SizedBox.shrink();

    final done = surveyed == true;
    final bg = done ? SeasColors.successSoft : SeasColors.voltSoft;
    final border = done ? SeasColors.success.withValues(alpha: 0.28) : SeasColors.volt.withValues(alpha: 0.28);
    final fg = done ? SeasColors.success : SeasColors.volt;
    final icon = done ? Icons.check_circle_outline_rounded : Icons.warning_amber_rounded;
    final text = done
        ? 'Feeder survey done for this feeder (optional — DTR can continue either way).'
        : 'Feeder survey not done yet — you can still survey this DTR.';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(12, 11, 12, 11),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: fg),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                height: 1.35,
                color: fg,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FormHeader extends StatelessWidget {
  const _FormHeader({
    required this.saving,
    required this.stepDone,
    required this.executiveName,
    required this.onBack,
  });
  final bool saving;
  final int stepDone;
  final String executiveName;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final initial = executiveName.isNotEmpty ? executiveName[0].toUpperCase() : 'E';
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [SeasColors.ink950, Color(0xFF1A1A1A)]),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(4, 0, 8, 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                height: 48,
                child: Row(
                  children: [
                    IconButton(
                      visualDensity: VisualDensity.compact,
                      onPressed: onBack,
                      icon: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 22),
                    ),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('DTR Survey', style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16, height: 1.1)),
                          Text(saving ? 'Saving…' : 'Field capture', style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 10, height: 1.1)),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.fromLTRB(6, 4, 8, 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.white12),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            height: 24,
                            width: 24,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(color: SeasColors.volt, borderRadius: BorderRadius.circular(7)),
                            child: Text(initial, style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 11)),
                          ),
                          const SizedBox(width: 6),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                executiveName.split(' ').take(2).join(' '),
                                style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11),
                                overflow: TextOverflow.ellipsis,
                              ),
                              const _LiveClockText(),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 0),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(99),
                  child: LinearProgressIndicator(
                    value: stepDone / 5,
                    minHeight: 3,
                    backgroundColor: Colors.white12,
                    color: SeasColors.volt,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Tiny live clock — isolates 1s rebuilds from the full survey form.
class _LiveClockText extends StatefulWidget {
  const _LiveClockText();

  @override
  State<_LiveClockText> createState() => _LiveClockTextState();
}

class _LiveClockTextState extends State<_LiveClockText> {
  late DateTime _now;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _now = DateTime.now();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Text(
      DateFormat('dd-MMM HH:mm:ss').format(_now),
      style: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 9, fontFeatures: const [FontFeature.tabularFigures()]),
    );
  }
}

class _CtPhotoRow extends StatelessWidget {
  const _CtPhotoRow({required this.path, required this.onTap, this.bytes});
  final String? path;
  final Uint8List? bytes;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    Widget? thumb;
    if (bytes != null && bytes!.isNotEmpty) {
      thumb = Image.memory(bytes!, fit: BoxFit.cover, width: 40, height: 40);
    } else if (path != null && path!.isNotEmpty && !kIsWeb) {
      try {
        final f = File(path!);
        if (f.existsSync()) {
          thumb = Image.file(f, fit: BoxFit.cover, width: 40, height: 40);
        }
      } catch (_) {}
    }
    final has = thumb != null || (path != null && path!.isNotEmpty);

    return Material(
      color: SeasColors.canvasSoft,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: has ? SeasColors.volt.withValues(alpha: 0.35) : SeasColors.ink200),
          ),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  height: 40,
                  width: 40,
                  color: has ? SeasColors.voltSoft : SeasColors.white,
                  child: thumb ??
                      Icon(
                        has ? Icons.check_circle : Icons.photo_camera_outlined,
                        color: has ? SeasColors.volt : SeasColors.ink400,
                      ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('CT Ratio Photo', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 13)),
                    Text(
                      has ? 'Photo attached — tap to retake' : 'Capture CT nameplate / ratio with camera',
                      style: const TextStyle(color: SeasColors.ink400, fontSize: 11),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.step, required this.title, required this.child, this.subtitle, this.trailing});
  final String step;
  final String title;
  final String? subtitle;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: SeasCard(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 36,
                  width: 36,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: SeasColors.ink950, borderRadius: BorderRadius.circular(12)),
                  child: Text(step, style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16, letterSpacing: -0.3)),
                      if (subtitle != null) Text(subtitle!, style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                    ],
                  ),
                ),
                if (trailing != null) trailing!,
              ],
            ),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }
}

class _AutoChip extends StatelessWidget {
  const _AutoChip({required this.icon, required this.label, required this.value, this.filled = true});
  final IconData icon;
  final String label;
  final String value;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: filled ? SeasColors.canvasSoft : SeasColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: SeasColors.ink200),
      ),
      child: Row(
        children: [
          Icon(icon, size: 18, color: SeasColors.ink400),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: GoogleFonts.plusJakartaSans(fontSize: 11, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
                Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14)),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(99)),
            child: Text('AUTO', style: GoogleFonts.plusJakartaSans(fontSize: 9, fontWeight: FontWeight.w800, color: SeasColors.volt)),
          ),
        ],
      ),
    );
  }
}

class _GpsMini extends StatelessWidget {
  const _GpsMini({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.white12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(), style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 0.6)),
          const SizedBox(height: 4),
          Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
        ],
      ),
    );
  }
}

class _PhotoBox extends StatelessWidget {
  const _PhotoBox({required this.label, required this.path, required this.onTap, this.bytes});
  final String label;
  final String? path;
  final Uint8List? bytes;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    Widget? preview;
    if (bytes != null && bytes!.isNotEmpty) {
      preview = Image.memory(bytes!, fit: BoxFit.cover, width: double.infinity, height: double.infinity);
    } else if (path != null && path!.isNotEmpty && !kIsWeb) {
      try {
        final f = File(path!);
        if (f.existsSync()) {
          preview = Image.file(f, fit: BoxFit.cover, width: double.infinity, height: double.infinity);
        }
      } catch (_) {}
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        height: 150,
        decoration: BoxDecoration(
          color: SeasColors.canvasSoft,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: preview != null ? SeasColors.volt : SeasColors.ink200,
            width: preview != null ? 1.6 : 1.2,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: preview != null
            ? Stack(
                fit: StackFit.expand,
                children: [
                  preview,
                  Positioned(
                    left: 8,
                    bottom: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: SeasColors.ink950.withValues(alpha: 0.8),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                    ),
                  ),
                  Positioned(
                    right: 8,
                    top: 8,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: SeasColors.ink950.withValues(alpha: 0.7),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(Icons.refresh_rounded, color: Colors.white, size: 16),
                    ),
                  ),
                ],
              )
            : Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    height: 44,
                    width: 44,
                    decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(14)),
                    child: const Icon(Icons.photo_camera_outlined, color: SeasColors.volt),
                  ),
                  const SizedBox(height: 10),
                  Text(label, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 13)),
                  const SizedBox(height: 2),
                  const Text('Tap to capture', style: TextStyle(color: SeasColors.ink400, fontSize: 11)),
                ],
              ),
      ),
    );
  }
}

class _AddNewDtrSheet extends StatefulWidget {
  const _AddNewDtrSheet({
    required this.feederId,
    required this.feederName,
    required this.lat,
    required this.lng,
    required this.gps,
    required this.onRefreshGps,
  });
  final int feederId;
  final String feederName;
  final String lat;
  final String lng;
  final String gps;
  final Future<void> Function() onRefreshGps;

  @override
  State<_AddNewDtrSheet> createState() => _AddNewDtrSheetState();
}

class _AddNewDtrSheetState extends State<_AddNewDtrSheet> {
  final nameCtrl = TextEditingController();
  final codeCtrl = TextEditingController();
  String? capacity;
  bool checking = false;
  bool saving = false;
  String? error;
  String? checkHint;
  Map<String, dynamic>? lastCheck;
  late String lat;
  late String lng;
  late String gps;

  @override
  void initState() {
    super.initState();
    lat = widget.lat;
    lng = widget.lng;
    gps = widget.gps;
  }

  @override
  void dispose() {
    nameCtrl.dispose();
    codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    await widget.onRefreshGps();
    try {
      final pos = await Geolocator.getCurrentPosition();
      setState(() {
        lat = pos.latitude.toStringAsFixed(7);
        lng = pos.longitude.toStringAsFixed(7);
        gps = '${pos.accuracy.toStringAsFixed(1)} m';
      });
    } catch (_) {}
  }

  bool _requireFields() {
    if (nameCtrl.text.trim().isEmpty || codeCtrl.text.trim().isEmpty) {
      setState(() => error = 'DTR Name and Code are required.');
      return false;
    }
    return true;
  }

  Future<Map<String, dynamic>?> _runCheck() async {
    if (!_requireFields()) return null;
    setState(() {
      checking = true;
      error = null;
      checkHint = null;
    });
    try {
      final res = await api.post('/dtr/check-code', {
        'feeder_id': widget.feederId,
        'code': codeCtrl.text.trim(),
      });
      setState(() {
        lastCheck = res;
        if (res['exists'] != true) {
          checkHint = 'Code available — new DTR under this feeder.';
        } else if (res['same_feeder'] == true) {
          checkHint = 'Code already under this feeder — will update existing DTR.';
        } else {
          checkHint = 'Code exists under another feeder — will update & map to this feeder.';
        }
      });
      return res;
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
      return null;
    } finally {
      if (mounted) setState(() => checking = false);
    }
  }

  Future<void> _onCheck() async {
    await _runCheck();
  }

  Future<void> _onCheckAndSave() async {
    if (!_requireFields()) return;
    setState(() => saving = true);
    try {
      // Optional online check for hint only — save always proceeds; backend overwrites/remaps.
      final online = await syncService.isOnline;
      if (online) {
        final check = await _runCheck();
        if (check == null) return;
      }

      Navigator.pop(context, {
        'name': nameCtrl.text.trim(),
        'code': codeCtrl.text.trim(),
        'capacity_kva': capacity,
        'lat': lat,
        'lng': lng,
        'gps': gps,
      });
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    final busy = checking || saving;
    return Padding(
      padding: EdgeInsets.only(bottom: bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: SeasColors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
              const SizedBox(height: 16),
              Text('Add New DTR', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
              const SizedBox(height: 4),
              Text('Under feeder: ${widget.feederName}', style: const TextStyle(color: SeasColors.ink400)),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: SeasColors.ink950,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      const Icon(Icons.my_location, color: SeasColors.volt, size: 16),
                      const SizedBox(width: 8),
                      Text('Your location (auto)', style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800)),
                      const Spacer(),
                      TextButton(onPressed: busy ? null : _refresh, child: const Text('Refresh', style: TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w800))),
                    ]),
                    const SizedBox(height: 8),
                    Text('$lat , $lng', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                    Text('Accuracy $gps', style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 11)),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              SeasTextField(label: 'DTR Name', controller: nameCtrl, hint: 'e.g. DTR Near School'),
              const SizedBox(height: 12),
              SeasTextField(label: 'DTR Code', controller: codeCtrl, hint: 'e.g. 3044010481'),
              const SizedBox(height: 12),
              SeasSelectField(
                label: 'Capacity (kVA)',
                hint: 'Select capacity',
                value: capacity,
                options: const ['25', '63', '100', '200', '315', '500', '630']
                    .map((e) => SeasSelectOption(value: e, label: e))
                    .toList(),
                onSelected: (o) => setState(() => capacity = o.value as String?),
              ),
              if (checkHint != null) ...[
                const SizedBox(height: 10),
                Text(
                  checkHint!,
                  style: TextStyle(
                    color: lastCheck?['will_overwrite'] == true ? SeasColors.warning : SeasColors.success,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
              ],
              if (error != null) ...[
                const SizedBox(height: 10),
                Text(error!, style: const TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w600)),
              ],
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy ? null : _onCheck,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.ink800,
                        side: const BorderSide(color: SeasColors.ink200),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: checking
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text('Check', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: FilledButton(
                      onPressed: busy ? null : _onCheckAndSave,
                      style: FilledButton.styleFrom(
                        backgroundColor: SeasColors.volt,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: saving
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : Text('Check & Save', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
