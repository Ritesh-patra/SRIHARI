import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import '../core/api_client.dart';
import '../core/hierarchy_cache.dart';
import '../main.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';
import '../widgets/confirm_dialog.dart';
import 'feeder_survey_success_screen.dart';

// #region agent log
void _dbgFeeder(String location, String message, Map<String, dynamic> data, {String hypothesisId = 'H2'}) {
  http
      .post(
        Uri.parse('http://127.0.0.1:7880/ingest/462b9acf-aae8-43ed-8500-97bbe6dedf80'),
        headers: {'Content-Type': 'application/json', 'X-Debug-Session-Id': 'a2382b'},
        body: jsonEncode({
          'sessionId': 'a2382b',
          'runId': 'pre-fix',
          'hypothesisId': hypothesisId,
          'location': location,
          'message': message,
          'data': data,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        }),
      )
      .catchError((_) => http.Response('', 500));
}
// #endregion

/// Substation → Feeder survey form (GPS / surveyor / datetime captured silently).
class FeederSurveyFormScreen extends StatefulWidget {
  const FeederSurveyFormScreen({
    super.key,
    this.serverId,
    this.initialLat,
    this.initialLng,
    this.initialAccuracy,
  });
  final int? serverId;
  final double? initialLat;
  final double? initialLng;
  final double? initialAccuracy;

  @override
  State<FeederSurveyFormScreen> createState() => _FeederSurveyFormScreenState();
}

class _FeederSurveyFormScreenState extends State<FeederSurveyFormScreen> {
  final _picker = ImagePicker();
  bool booting = true;
  bool saving = false;
  String? error;
  int? serverId;
  /// Feeder IDs that already have a non-rejected survey (hide from new-survey picker).
  Set<int> surveyedFeederIds = {};

  Map<String, dynamic>? user;

  int? regionId, circleId, divisionId, zoneId, substationId, feederId;
  String feederCode = '';
  String feederName = '';
  String substationName = '';
  String substationCode = '';

  final latCtrl = TextEditingController();
  final lngCtrl = TextEditingController();
  final gpsCtrl = TextEditingController();
  final newMeterCtrl = TextEditingController();
  final oldMeterCtrl = TextEditingController();
  final remarksCtrl = TextEditingController();

  String? feederVoltage;
  String? ctptAvailable;
  String? meCtRatio;
  String? meInstalled;
  String? meWorking;
  String? smartMeterInstalled;
  String? oldMeterMake;
  String? oldMeterCondition;

  String? meterPhotoPath;
  Uint8List? meterPhotoBytes;

  static const voltages = ['11 KV', '33 KV'];
  static const yesNo = ['Yes', 'No'];
  static const ctRatios = ['100/5', '150/5', '200/5', '300/5'];
  static const smartOpts = ['Yes', 'No', 'Meter Not Available'];
  static const oldMakes = ['L&T Schneider', 'Secure', 'HPL', 'Visiontek', 'Other'];
  static const oldConditions = ['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'];

  /// 11 KV → Output Feeder; 33 KV → Input Feeder (auto, not editable — same as New MF).
  String? get meteringType {
    final v = (feederVoltage ?? '').replaceAll(' ', '').toUpperCase();
    if (v == '11KV') return 'Output Feeder';
    if (v == '33KV') return 'Input Feeder';
    return null;
  }

  String get mePtRatio {
    if (feederVoltage == '33 KV') return '33 kV / 110V';
    return '11 kV / 110V';
  }

  String get newMf {
    final pt = feederVoltage == '33 KV' ? 300.0 : 100.0;
    final parts = (meCtRatio ?? '').split('/');
    if (parts.length != 2) return '—';
    final a = double.tryParse(parts[0].trim());
    final b = double.tryParse(parts[1].trim());
    if (a == null || b == null || b == 0) return '—';
    return ((pt * (a / b)).round()).toString();
  }

  bool get showOldMeter =>
      smartMeterInstalled == 'No' || smartMeterInstalled == 'Meter Not Available';

  bool get showNewMeterFields => smartMeterInstalled == 'Yes';

  @override
  void initState() {
    super.initState();
    serverId = widget.serverId;
    if (widget.initialLat != null) latCtrl.text = widget.initialLat!.toStringAsFixed(7);
    if (widget.initialLng != null) lngCtrl.text = widget.initialLng!.toStringAsFixed(7);
    if (widget.initialAccuracy != null) gpsCtrl.text = widget.initialAccuracy!.toStringAsFixed(1);
    _boot();
  }

  @override
  void dispose() {
    latCtrl.dispose();
    lngCtrl.dispose();
    gpsCtrl.dispose();
    newMeterCtrl.dispose();
    oldMeterCtrl.dispose();
    remarksCtrl.dispose();
    super.dispose();
  }

  Future<void> _boot() async {
    setState(() => booting = true);
    try {
      user = await loadSavedUser();
      await hierarchyCache.ensureLoaded();
      if (serverId != null) {
        await _hydrateFromServer(serverId!);
      } else {
        await _loadSurveyedFeederIds();
        _maybeAutofillSingleZone();
      }
      await _captureGps();
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    }
    if (mounted) setState(() => booting = false);
  }

  Future<void> _loadSurveyedFeederIds() async {
    try {
      final res = await api.get('/feeder-surveys?per_page=100');
      final raw = (res['data'] as List?) ?? [];
      final ids = <int>{};
      for (final e in raw) {
        if (e is! Map) continue;
        final st = '${e['status'] ?? ''}'.toLowerCase();
        if (st == 'rejected') continue;
        final fid = e['feeder_id'];
        final asInt = fid is int ? fid : int.tryParse('$fid');
        if (asInt != null) ids.add(asInt);
      }
      surveyedFeederIds = ids;
    } catch (_) {
      surveyedFeederIds = {};
    }
  }

  List<Map<String, dynamic>> _availableFeeders(int? ssId) {
    final all = hierarchyCache.feeders(ssId);
    if (serverId != null || surveyedFeederIds.isEmpty) return all;
    return all.where((f) {
      final id = f['id'];
      final asInt = id is int ? id : int.tryParse('$id');
      if (asInt == null) return true;
      return !surveyedFeederIds.contains(asInt);
    }).toList();
  }

  Future<void> _hydrateFromServer(int id) async {
    final res = await api.get('/feeder-surveys?per_page=100');
    final raw = (res['data'] as List?) ?? [];
    Map? match;
    for (final e in raw) {
      if (e is Map && (e['id'] as num?)?.toInt() == id) {
        match = e;
        break;
      }
    }
    if (match == null) return;
    final s = Map<String, dynamic>.from(match);
    int? asInt(dynamic v) => v is int ? v : int.tryParse('$v');
    regionId = asInt(s['region_id']);
    circleId = asInt(s['circle_id']);
    divisionId = asInt(s['division_id']);
    zoneId = asInt(s['zone_id']);
    substationId = asInt(s['substation_id']);
    feederId = asInt(s['feeder_id']);
    substationCode = '${s['substation_code'] ?? ''}';
    substationName = '${s['substation_name'] ?? ''}';
    feederCode = '${s['feeder_code'] ?? ''}';
    feederName = '${s['feeder_name'] ?? ''}';
    if (s['latitude'] != null) latCtrl.text = '${s['latitude']}';
    if (s['longitude'] != null) lngCtrl.text = '${s['longitude']}';
    if (s['gps_accuracy'] != null) gpsCtrl.text = '${s['gps_accuracy']}';
    feederVoltage = s['feeder_voltage']?.toString();
    // meteringType is derived from feederVoltage (11 KV → Output Feeder, 33 KV → Input Feeder).
    ctptAvailable = s['ctpt_available']?.toString();
    meCtRatio = s['me_ct_ratio']?.toString();
    meInstalled = s['me_installed']?.toString();
    meWorking = s['me_working']?.toString();
    smartMeterInstalled = s['new_smart_meter_installed']?.toString();
    newMeterCtrl.text = '${s['new_meter_number'] ?? ''}';
    oldMeterCtrl.text = '${s['old_meter_number'] ?? ''}';
    oldMeterMake = s['old_meter_make']?.toString();
    oldMeterCondition = s['old_meter_condition']?.toString();
    remarksCtrl.text = '${s['remarks'] ?? ''}';
  }

  Future<void> _captureGps() async {
    try {
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.deniedForever || perm == LocationPermission.denied) return;
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      latCtrl.text = pos.latitude.toStringAsFixed(7);
      lngCtrl.text = pos.longitude.toStringAsFixed(7);
      gpsCtrl.text = pos.accuracy.toStringAsFixed(1);
    } catch (_) {}
  }

  List<SeasSelectOption> _mapOpts(List list) {
    return list
        .map((e) {
          final m = Map<String, dynamic>.from(e as Map);
          return SeasSelectOption(value: m['id'] as int, label: '${m['name'] ?? m['code'] ?? ''}');
        })
        .toList();
  }

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

  Future<void> _pickMeterPhoto() async {
    final x = await _picker.pickImage(source: ImageSource.camera, imageQuality: 72, maxWidth: 1600);
    if (x == null) return;
    Uint8List? bytes;
    try {
      bytes = await x.readAsBytes();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      meterPhotoPath = x.path;
      meterPhotoBytes = bytes;
    });
  }

  String? _validate({required bool submit}) {
    if (regionId == null || circleId == null || divisionId == null || zoneId == null || substationId == null || feederId == null) {
      return 'Complete hierarchy down to Feeder.';
    }
    if (feederVoltage == null || meteringType == null || ctptAvailable == null || meCtRatio == null) {
      return 'Fill Field Verification fields.';
    }
    if (meInstalled == null || meWorking == null || smartMeterInstalled == null) {
      return 'Fill ME / smart meter status fields.';
    }
    if (showNewMeterFields && newMeterCtrl.text.trim().isEmpty) {
      return 'Enter New Meter Number.';
    }
    if (submit && showNewMeterFields && meterPhotoBytes == null && meterPhotoPath == null) {
      return 'Capture photograph of new meter.';
    }
    if (showOldMeter) {
      if (oldMeterCtrl.text.trim().isEmpty || oldMeterMake == null || oldMeterCondition == null) {
        return 'Fill Old Meter details (If No).';
      }
    }
    return null;
  }

  Future<void> _save({required bool submit}) async {
    // #region agent log
    _dbgFeeder('feeder_survey_form.dart:_save:start', 'save started', {
      'submit': submit,
      'serverId': serverId,
      'hasPhotoBytes': meterPhotoBytes != null,
      'hasPhotoPath': meterPhotoPath != null,
      'smartMeter': smartMeterInstalled,
    }, hypothesisId: 'H4');
    // #endregion
    final err = _validate(submit: submit);
    if (err != null) {
      // #region agent log
      _dbgFeeder('feeder_survey_form.dart:_save:validate', 'client validation blocked', {
        'submit': submit,
        'err': err,
      }, hypothesisId: 'H4');
      // #endregion
      setState(() => error = err);
      return;
    }
    if (submit) {
      final ok = await confirmSubmit(
        context,
        message: 'Are you sure you want to submit feeder details?',
      );
      if (!ok || !mounted) return;
    }
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final fields = <String, String>{
        'region_id': '$regionId',
        'circle_id': '$circleId',
        'division_id': '$divisionId',
        'zone_id': '$zoneId',
        'substation_id': '$substationId',
        'feeder_id': '$feederId',
        'substation_code': substationCode,
        'latitude': latCtrl.text.trim(),
        'longitude': lngCtrl.text.trim(),
        'gps_accuracy': gpsCtrl.text.trim(),
        'feeder_voltage': feederVoltage!,
        'metering_type': meteringType!,
        'ctpt_available': ctptAvailable!,
        'me_pt_ratio': mePtRatio,
        'me_ct_ratio': meCtRatio!,
        'new_mf': newMf == '—' ? '' : newMf,
        'me_installed': meInstalled!,
        'me_working': meWorking!,
        'new_smart_meter_installed': smartMeterInstalled!,
        'new_meter_number': showNewMeterFields ? newMeterCtrl.text.trim() : '',
        'old_meter_number': oldMeterCtrl.text.trim(),
        if (oldMeterMake != null) 'old_meter_make': oldMeterMake!,
        if (oldMeterCondition != null) 'old_meter_condition': oldMeterCondition!,
        'remarks': remarksCtrl.text.trim(),
        // Basic details always save as draft; SLD upload completes the survey later.
        'action': 'draft',
      };

      final path = serverId == null ? '/feeder-surveys' : '/feeder-surveys/$serverId';
      final useBytes = kIsWeb || (meterPhotoPath != null && meterPhotoPath!.startsWith('blob:'));
      // #region agent log
      _dbgFeeder('feeder_survey_form.dart:_save:beforePost', 'posting multipart', {
        'path': path,
        'submit': submit,
        'fieldKeys': fields.keys.toList(),
        'useBytes': useBytes,
        'willSendPhoto': meterPhotoBytes != null || (meterPhotoPath != null && !useBytes),
      }, hypothesisId: 'H2');
      // #endregion
      final sendPhoto = showNewMeterFields && (meterPhotoBytes != null || meterPhotoPath != null);
      final res = await api.postMultipart(
        path: path,
        fields: fields,
        filePaths: !sendPhoto || useBytes || meterPhotoPath == null
            ? null
            : {if (meterPhotoPath != null) 'new_meter_photo': meterPhotoPath!},
        fileBytes: {
          if (sendPhoto && meterPhotoBytes != null && (useBytes || meterPhotoPath == null))
            'new_meter_photo': meterPhotoBytes!,
        },
      );

      final survey = Map<String, dynamic>.from((res['survey'] as Map?) ?? {});
      serverId = (survey['id'] as num?)?.toInt() ?? serverId;
      // #region agent log
      _dbgFeeder('feeder_survey_form.dart:_save:success', 'save succeeded', {
        'submit': submit,
        'surveyId': serverId,
        'status': survey['status']?.toString(),
        'message': res['message']?.toString(),
      }, hypothesisId: 'H2');
      // #endregion

      if (!mounted) return;
      if (!submit) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(res['message']?.toString() ?? 'Draft saved'),
          backgroundColor: SeasColors.ink950,
        ));
        return;
      }

      // Success → Start DTR Survey (hierarchy prefilled) or Dashboard
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => FeederSurveySuccessScreen(
            survey: survey,
            message: res['message']?.toString(),
          ),
        ),
      );
    } on ApiException catch (e) {
      // #region agent log
      _dbgFeeder('feeder_survey_form.dart:_save:catch', 'save failed', {
        'submit': submit,
        'error': e.toString(),
        'isApiException': true,
        'statusCode': e.statusCode,
      }, hypothesisId: 'H3');
      // #endregion
      if (e.statusCode == 409 && mounted) {
        final friendly = e.body['message']?.toString() ??
            'This feeder was already surveyed. Duplicate survey is not allowed.';
        final existingRaw = e.body['existing_survey_id'];
        final existingId = existingRaw is int ? existingRaw : int.tryParse('$existingRaw');
        final open = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text('Already surveyed', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
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
            MaterialPageRoute(builder: (_) => FeederSurveyFormScreen(serverId: existingId)),
          );
        }
        setState(() => error = friendly);
        return;
      }
      setState(() => error = e.body['message']?.toString() ?? e.message);
    } catch (e) {
      // #region agent log
      _dbgFeeder('feeder_survey_form.dart:_save:catch', 'save failed', {
        'submit': submit,
        'error': e.toString(),
        'isApiException': e is ApiException,
        'statusCode': e is ApiException ? e.statusCode : null,
      }, hypothesisId: 'H3');
      // #endregion
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (booting) {
      return const Scaffold(body: Center(child: CircularProgressIndicator(color: SeasColors.volt)));
    }

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: Colors.white,
        title: Text('Feeder Survey', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17)),
        actions: [
          TextButton(
            onPressed: saving ? null : () => _save(submit: false),
            child: Text('Draft', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: Colors.white70)),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          if (error != null)
            Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(12)),
              child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
            ),

          if (!hierarchyCache.hasAssignedFeeders)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
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

          _Band('Select Hierarchy'),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              SeasSelectField(
                label: 'Region *',
                hint: 'Select',
                value: regionId,
                options: _mapOpts(hierarchyCache.regions),
                onSelected: (o) => setState(() {
                  regionId = o.value as int?;
                  circleId = divisionId = zoneId = substationId = feederId = null;
                  feederCode = feederName = substationName = substationCode = '';
                }),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Circle *',
                hint: 'Select',
                enabled: regionId != null,
                value: circleId,
                options: _mapOpts(hierarchyCache.circles(regionId)),
                onSelected: (o) => setState(() {
                  circleId = o.value as int?;
                  divisionId = zoneId = substationId = feederId = null;
                  feederCode = feederName = substationName = substationCode = '';
                }),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Division *',
                hint: 'Select',
                enabled: circleId != null,
                value: divisionId,
                options: _mapOpts(hierarchyCache.divisions(circleId)),
                onSelected: (o) => setState(() {
                  divisionId = o.value as int?;
                  zoneId = substationId = feederId = null;
                  feederCode = feederName = substationName = substationCode = '';
                }),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Zone / DC *',
                hint: 'Select',
                enabled: divisionId != null,
                value: zoneId,
                options: _mapOpts(hierarchyCache.zones(divisionId)),
                onSelected: (o) => setState(() {
                  _applyZone(o.value as int?);
                  substationId = feederId = null;
                  feederCode = feederName = substationName = substationCode = '';
                }),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Substation Name *',
                hint: 'Select',
                enabled: zoneId != null,
                value: substationId,
                options: _mapOpts(hierarchyCache.substations(zoneId)),
                onSelected: (o) {
                  final id = o.value as int?;
                  final list = hierarchyCache.substations(zoneId);
                  final match = list.cast<Map>().where((s) => s['id'] == id).toList();
                  setState(() {
                    substationId = id;
                    substationName = match.isEmpty ? o.label : '${match.first['name'] ?? o.label}';
                    substationCode = match.isEmpty ? '$id' : '${match.first['code'] ?? id}';
                    feederId = null;
                    feederCode = feederName = '';
                  });
                },
              ),
              const SizedBox(height: 8),
              _Kv('Substation Code', substationCode.isEmpty ? '—' : substationCode, last: true),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Feeder Name *',
                hint: substationId == null
                    ? 'Select substation first'
                    : hierarchyCache.feeders(substationId).isEmpty
                        ? 'No feeders assigned. Contact your manager.'
                        : (_availableFeeders(substationId).isEmpty
                            ? 'All assigned feeders already surveyed (see Continue / Status).'
                            : 'Select'),
                enabled: substationId != null && _availableFeeders(substationId).isNotEmpty,
                value: feederId,
                options: _mapOpts(_availableFeeders(substationId)),
                onSelected: (o) {
                  final id = o.value as int?;
                  final list = _availableFeeders(substationId);
                  final match = list.cast<Map>().where((s) => s['id'] == id).toList();
                  setState(() {
                    feederId = id;
                    feederName = match.isEmpty ? o.label : '${match.first['name'] ?? o.label}';
                    feederCode = match.isEmpty ? '' : '${match.first['code'] ?? ''}';
                  });
                },
              ),
              const SizedBox(height: 8),
              _Kv('Feeder Code', feederCode.isEmpty ? '—' : feederCode, last: true),
            ]),
          ),

          _Band('Field Verification (Editable)'),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              SeasSelectField(
                label: 'Feeder Voltage *',
                hint: '11 KV, 33 KV',
                value: feederVoltage,
                options: voltages.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => feederVoltage = o.value as String?),
              ),
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: SeasColors.canvasSoft,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: SeasColors.ink100),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Metering Type (Auto)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12, color: SeasColors.ink400)),
                    const SizedBox(height: 4),
                    Text(
                      meteringType ?? 'Select feeder voltage above',
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                        color: meteringType == null ? SeasColors.ink400 : SeasColors.volt,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'CTPT Available *',
                hint: 'Yes / No',
                value: ctptAvailable,
                options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => ctptAvailable = o.value as String?),
              ),
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFE8F5E9),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFA5D6A7)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('ME PT Ratio (Not editable)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12, color: const Color(0xFF1B5E20))),
                    const SizedBox(height: 4),
                    Text(mePtRatio, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'ME CT Ratio *',
                hint: '100/5 · 150/5 · 200/5 · 300/5',
                value: meCtRatio,
                options: ctRatios.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meCtRatio = o.value as String?),
              ),
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: SeasColors.canvasSoft,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: SeasColors.ink100),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('New MF (Auto Calculate)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12, color: SeasColors.ink400)),
                    const SizedBox(height: 4),
                    Text(newMf, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.volt)),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'ME Installed *',
                hint: 'Yes / No',
                value: meInstalled,
                options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meInstalled = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'ME Working *',
                hint: 'Yes / No',
                value: meWorking,
                options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meWorking = o.value as String?),
              ),
            ]),
          ),

          _Band('Feeder Meter Details'),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              SeasSelectField(
                label: 'New Smart Meter Installed *',
                hint: 'Yes / No / Meter Not Available',
                value: smartMeterInstalled,
                options: smartOpts.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() {
                  smartMeterInstalled = o.value as String?;
                  if (smartMeterInstalled != 'Yes') {
                    newMeterCtrl.clear();
                    meterPhotoPath = null;
                    meterPhotoBytes = null;
                  }
                }),
              ),
              if (showNewMeterFields) ...[
                const SizedBox(height: 10),
                SeasTextField(label: 'New Meter Number *', controller: newMeterCtrl, hint: 'e.g. PS01083833'),
                const SizedBox(height: 10),
                InkWell(
                  onTap: _pickMeterPhoto,
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    height: 120,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: SeasColors.canvasSoft,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: SeasColors.ink100),
                      image: meterPhotoBytes != null
                          ? DecorationImage(image: MemoryImage(meterPhotoBytes!), fit: BoxFit.cover)
                          : null,
                    ),
                    alignment: Alignment.center,
                    child: meterPhotoBytes == null
                        ? Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.photo_camera_outlined, color: SeasColors.volt),
                              const SizedBox(height: 6),
                              Text('Photograph of New Meter *', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12)),
                            ],
                          )
                        : null,
                  ),
                ),
              ],
            ]),
          ),

          if (showOldMeter) ...[
            _Band('If No → Old Meter'),
            SeasCard(
              padding: const EdgeInsets.all(12),
              child: Column(children: [
                SeasTextField(label: 'Old Meter Number', controller: oldMeterCtrl, hint: 'e.g. AXS123456'),
                const SizedBox(height: 10),
                SeasSelectField(
                  label: 'Old Meter Make',
                  hint: 'Select',
                  value: oldMeterMake,
                  options: oldMakes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                  onSelected: (o) => setState(() => oldMeterMake = o.value as String?),
                ),
                const SizedBox(height: 10),
                SeasSelectField(
                  label: 'Old Meter Condition',
                  hint: 'Select',
                  value: oldMeterCondition,
                  options: oldConditions.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                  onSelected: (o) => setState(() => oldMeterCondition = o.value as String?),
                ),
              ]),
            ),
          ],

          _Band('Remarks'),
          SeasTextField(label: 'Remarks', controller: remarksCtrl, hint: 'All Good', maxLines: 3),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: saving ? null : () => _save(submit: true),
              style: FilledButton.styleFrom(
                backgroundColor: SeasColors.volt,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: Text(
                saving ? 'Saving…' : 'Submit Feeder Details',
                textAlign: TextAlign.center,
                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Band extends StatelessWidget {
  const _Band(this.label);
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 14, bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: SeasColors.ink50, borderRadius: BorderRadius.circular(10)),
      child: Text(label, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12, color: SeasColors.ink950)),
    );
  }
}

class _Kv extends StatelessWidget {
  const _Kv(this.label, this.value, {this.last = false});
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        border: last ? null : const Border(bottom: BorderSide(color: SeasColors.ink100)),
      ),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: SeasColors.ink400, fontSize: 12))),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
