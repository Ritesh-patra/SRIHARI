import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import '../core/api_client.dart';
import '../core/hierarchy_cache.dart';
import '../theme/seas_colors.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';

/// Substation Survey / Audit (Substation ka survey — GPS + asset + meter details).
/// GPS captured here becomes the substation pin on the network map after approval.
class SubstationSurveyFormScreen extends StatefulWidget {
  const SubstationSurveyFormScreen({
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
  State<SubstationSurveyFormScreen> createState() => _SubstationSurveyFormScreenState();
}

class _SubstationSurveyFormScreenState extends State<SubstationSurveyFormScreen> {
  final _picker = ImagePicker();
  bool booting = true;
  bool saving = false;
  bool gpsLoading = false;
  String? error;
  int? serverId;

  int? regionId, circleId, divisionId, zoneId, substationId;
  String substationName = '';
  String substationCode = '';

  final latCtrl = TextEditingController();
  final lngCtrl = TextEditingController();
  final gpsCtrl = TextEditingController();
  final capacityCtrl = TextEditingController();
  final transformerCtrl = TextEditingController();
  final feederCountCtrl = TextEditingController();
  final meterNumberCtrl = TextEditingController();
  final meterSerialCtrl = TextEditingController();
  final ctRatioCtrl = TextEditingController();
  final ptRatioCtrl = TextEditingController();
  final mfCtrl = TextEditingController();
  final observationCtrl = TextEditingController();
  final remarksCtrl = TextEditingController();

  String? substationType;
  String? incomingVoltage;
  String? outgoingVoltage;
  String? meterMake;
  String? meteringType;
  String? meterCondition;
  String? meterWorking;

  final Map<String, String?> photoPaths = {
    'substation_photo': null,
    'meter_photo': null,
    'nameplate_photo': null,
    'sld_photo': null,
  };
  final Map<String, Uint8List?> photoBytes = {
    'substation_photo': null,
    'meter_photo': null,
    'nameplate_photo': null,
    'sld_photo': null,
  };

  static const substationTypes = ['132/33 KV', '33/11 KV', '11/0.4 KV', 'Other'];
  static const voltages = ['132 KV', '33 KV', '11 KV', '0.4 KV'];
  static const meterMakes = ['L&T Schneider', 'Secure', 'HPL', 'Visiontek', 'Genus', 'Other'];
  static const meteringTypes = ['Input Feeder', 'Output Feeder', 'Main Meter', 'Check Meter'];
  static const meterConditions = ['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'];
  static const yesNo = ['Yes', 'No'];

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
    capacityCtrl.dispose();
    transformerCtrl.dispose();
    feederCountCtrl.dispose();
    meterNumberCtrl.dispose();
    meterSerialCtrl.dispose();
    ctRatioCtrl.dispose();
    ptRatioCtrl.dispose();
    mfCtrl.dispose();
    observationCtrl.dispose();
    remarksCtrl.dispose();
    super.dispose();
  }

  Future<void> _boot() async {
    setState(() => booting = true);
    try {
      await hierarchyCache.ensureLoaded();
      if (serverId != null) {
        await _hydrateFromServer(serverId!);
      } else {
        _maybeAutofillSingleZone();
        await _captureGps();
      }
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    }
    if (mounted) setState(() => booting = false);
  }

  Future<void> _hydrateFromServer(int id) async {
    final res = await api.get('/substation-surveys/$id');
    final raw = res['survey'];
    if (raw is! Map) return;
    final s = Map<String, dynamic>.from(raw);
    int? asInt(dynamic v) => v is int ? v : int.tryParse('$v');

    regionId = asInt(s['region_id']);
    circleId = asInt(s['circle_id']);
    divisionId = asInt(s['division_id']);
    zoneId = asInt(s['zone_id']);
    substationId = asInt(s['substation_id']);
    substationCode = '${s['substation_code'] ?? ''}';
    substationName = '${s['substation_name'] ?? ''}';
    if (s['latitude'] != null) latCtrl.text = '${s['latitude']}';
    if (s['longitude'] != null) lngCtrl.text = '${s['longitude']}';
    if (s['gps_accuracy'] != null) gpsCtrl.text = '${s['gps_accuracy']}';
    substationType = s['substation_type']?.toString();
    capacityCtrl.text = '${s['capacity_mva'] ?? ''}';
    transformerCtrl.text = '${s['transformer_count'] ?? ''}';
    incomingVoltage = s['incoming_voltage']?.toString();
    outgoingVoltage = s['outgoing_voltage']?.toString();
    feederCountCtrl.text = '${s['feeder_count_declared'] ?? ''}';
    meterNumberCtrl.text = '${s['meter_number'] ?? ''}';
    meterMake = s['meter_make']?.toString();
    meterSerialCtrl.text = '${s['meter_serial_no'] ?? ''}';
    meteringType = s['metering_type']?.toString();
    ctRatioCtrl.text = '${s['ct_ratio'] ?? ''}';
    ptRatioCtrl.text = '${s['pt_ratio'] ?? ''}';
    mfCtrl.text = '${s['mf'] ?? ''}';
    meterCondition = s['meter_condition']?.toString();
    if (s['meter_working'] != null) {
      meterWorking = (s['meter_working'] == true || '${s['meter_working']}' == '1') ? 'Yes' : 'No';
    }
    observationCtrl.text = '${s['observation'] ?? ''}';
    remarksCtrl.text = '${s['remarks'] ?? ''}';
  }

  Future<void> _captureGps() async {
    setState(() => gpsLoading = true);
    try {
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.deniedForever || perm == LocationPermission.denied) return;
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      if (!mounted) return;
      setState(() {
        latCtrl.text = pos.latitude.toStringAsFixed(7);
        lngCtrl.text = pos.longitude.toStringAsFixed(7);
        gpsCtrl.text = pos.accuracy.toStringAsFixed(1);
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => gpsLoading = false);
    }
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

  Future<void> _pickPhoto(String field) async {
    final x = await _picker.pickImage(source: ImageSource.camera, imageQuality: 72, maxWidth: 1600);
    if (x == null) return;
    Uint8List? bytes;
    try {
      bytes = await x.readAsBytes();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      photoPaths[field] = x.path;
      photoBytes[field] = bytes;
    });
  }

  bool _hasPhoto(String field) => photoBytes[field] != null || photoPaths[field] != null;

  String? _validate({required bool submit}) {
    if (regionId == null || circleId == null || divisionId == null || zoneId == null || substationId == null) {
      return 'Complete hierarchy down to Substation.';
    }
    if (submit) {
      if (substationType == null) return 'Select Substation Type.';
      if (meterNumberCtrl.text.trim().isEmpty) return 'Enter Meter Number.';
      if (!_hasPhoto('meter_photo')) return 'Capture photograph of the substation meter.';
      if (latCtrl.text.trim().isEmpty || lngCtrl.text.trim().isEmpty) {
        return 'GPS location is required. Tap Refresh in the GPS box.';
      }
    }
    return null;
  }

  Future<void> _save({required bool submit}) async {
    final err = _validate(submit: submit);
    if (err != null) {
      setState(() => error = err);
      return;
    }
    if (submit) {
      final ok = await confirmSubmit(
        context,
        message: 'Are you sure you want to submit this substation survey?',
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
        'substation_code': substationCode,
        // Omit empty GPS so Laravel nullable|numeric does not see "".
        if (latCtrl.text.trim().isNotEmpty) 'latitude': latCtrl.text.trim(),
        if (lngCtrl.text.trim().isNotEmpty) 'longitude': lngCtrl.text.trim(),
        if (gpsCtrl.text.trim().isNotEmpty) 'gps_accuracy': gpsCtrl.text.trim(),
        if (substationType != null) 'substation_type': substationType!,
        if (capacityCtrl.text.trim().isNotEmpty) 'capacity_mva': capacityCtrl.text.trim(),
        if (transformerCtrl.text.trim().isNotEmpty) 'transformer_count': transformerCtrl.text.trim(),
        if (incomingVoltage != null) 'incoming_voltage': incomingVoltage!,
        if (outgoingVoltage != null) 'outgoing_voltage': outgoingVoltage!,
        if (feederCountCtrl.text.trim().isNotEmpty) 'feeder_count_declared': feederCountCtrl.text.trim(),
        'meter_number': meterNumberCtrl.text.trim(),
        if (meterMake != null) 'meter_make': meterMake!,
        'meter_serial_no': meterSerialCtrl.text.trim(),
        if (meteringType != null) 'metering_type': meteringType!,
        'ct_ratio': ctRatioCtrl.text.trim(),
        'pt_ratio': ptRatioCtrl.text.trim(),
        'mf': mfCtrl.text.trim(),
        if (meterCondition != null) 'meter_condition': meterCondition!,
        if (meterWorking != null) 'meter_working': meterWorking!,
        'observation': observationCtrl.text.trim(),
        'remarks': remarksCtrl.text.trim(),
        'action': submit ? 'submit' : 'draft',
      };

      final path = serverId == null ? '/substation-surveys' : '/substation-surveys/$serverId';
      final filePaths = <String, String>{};
      final fileBytes = <String, List<int>>{};
      for (final field in photoPaths.keys) {
        final localPath = photoPaths[field];
        final bytes = photoBytes[field];
        final useBytes = kIsWeb || localPath == null || localPath.startsWith('blob:');
        if (useBytes) {
          if (bytes != null) fileBytes[field] = bytes;
        } else {
          filePaths[field] = localPath;
        }
      }

      final res = await api.postMultipart(
        path: path,
        fields: fields,
        filePaths: filePaths.isEmpty ? null : filePaths,
        fileBytes: fileBytes.isEmpty ? null : fileBytes,
      );

      final survey = Map<String, dynamic>.from((res['survey'] as Map?) ?? {});
      serverId = (survey['id'] as num?)?.toInt() ?? serverId;

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(res['message']?.toString() ?? (submit ? 'Substation survey submitted' : 'Draft saved')),
        backgroundColor: SeasColors.ink950,
      ));
      if (submit) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (e.statusCode == 409 && mounted) {
        final friendly = e.body['message']?.toString() ??
            'This substation was already surveyed. Duplicate survey is not allowed.';
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
            MaterialPageRoute(builder: (_) => SubstationSurveyFormScreen(serverId: existingId)),
          );
        }
        setState(() => error = friendly);
        return;
      }
      setState(() => error = e.body['message']?.toString() ?? e.message);
    } catch (e) {
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
        title: Text('Substation Survey', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17)),
        actions: [
          IconButton(
            tooltip: 'My substation surveys',
            onPressed: saving
                ? null
                : () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const SubstationSurveyListScreen()),
                    ),
            icon: const Icon(Icons.history_rounded),
          ),
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
                  circleId = divisionId = zoneId = substationId = null;
                  substationName = substationCode = '';
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
                  divisionId = zoneId = substationId = null;
                  substationName = substationCode = '';
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
                  zoneId = substationId = null;
                  substationName = substationCode = '';
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
                  substationId = null;
                  substationName = substationCode = '';
                }),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Substation Name *',
                hint: zoneId == null ? 'Select zone first' : 'Select',
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
                  });
                },
              ),
              const SizedBox(height: 8),
              _Kv('Substation Code', substationCode.isEmpty ? '—' : substationCode, last: true),
            ]),
          ),

          _Band('GPS Location (Map Pin)'),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: SeasColors.ink950,
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
                      child: Text(
                        gpsLoading ? 'Reading…' : 'Refresh',
                        style: const TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(children: [
                  Expanded(child: _GpsMini(label: 'Latitude', value: latCtrl.text.isEmpty ? '—' : latCtrl.text)),
                  const SizedBox(width: 8),
                  Expanded(child: _GpsMini(label: 'Longitude', value: lngCtrl.text.isEmpty ? '—' : lngCtrl.text)),
                ]),
                const SizedBox(height: 8),
                _GpsMini(label: 'Accuracy (m)', value: gpsCtrl.text.isEmpty ? '—' : gpsCtrl.text),
                const SizedBox(height: 8),
                Text(
                  'Substation pin isi location se banega — meter ke paas khade hokar Refresh karein.',
                  style: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 11),
                ),
              ],
            ),
          ),

          _Band('Substation Details'),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              SeasSelectField(
                label: 'Substation Type *',
                hint: '33/11 KV, 11/0.4 KV …',
                value: substationType,
                options: substationTypes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => substationType = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasTextField(
                label: 'Capacity (MVA)',
                controller: capacityCtrl,
                hint: 'e.g. 5',
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
              ),
              const SizedBox(height: 10),
              SeasTextField(
                label: 'Number of Transformers',
                controller: transformerCtrl,
                hint: 'e.g. 2',
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Incoming Voltage',
                hint: 'Select',
                value: incomingVoltage,
                options: voltages.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => incomingVoltage = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Outgoing Voltage',
                hint: 'Select',
                value: outgoingVoltage,
                options: voltages.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => outgoingVoltage = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasTextField(
                label: 'Number of Feeders (as per site)',
                controller: feederCountCtrl,
                hint: 'e.g. 6',
                keyboardType: TextInputType.number,
              ),
            ]),
          ),

          _Band('Substation Meter Details'),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              SeasTextField(label: 'Meter Number *', controller: meterNumberCtrl, hint: 'e.g. PS01083833'),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Meter Make',
                hint: 'Select',
                value: meterMake,
                options: meterMakes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meterMake = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasTextField(label: 'Meter Serial No', controller: meterSerialCtrl, hint: 'Serial / SL No'),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Metering Type',
                hint: 'Select',
                value: meteringType,
                options: meteringTypes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meteringType = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasTextField(label: 'CT Ratio', controller: ctRatioCtrl, hint: 'e.g. 200/5'),
              const SizedBox(height: 10),
              SeasTextField(label: 'PT Ratio', controller: ptRatioCtrl, hint: 'e.g. 33 kV / 110V'),
              const SizedBox(height: 10),
              SeasTextField(label: 'MF (Multiplying Factor)', controller: mfCtrl, hint: 'e.g. 1200'),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Meter Condition',
                hint: 'Select',
                value: meterCondition,
                options: meterConditions.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meterCondition = o.value as String?),
              ),
              const SizedBox(height: 10),
              SeasSelectField(
                label: 'Meter Working',
                hint: 'Yes / No',
                value: meterWorking,
                options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meterWorking = o.value as String?),
              ),
            ]),
          ),

          _Band('Photographs'),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              _PhotoBox(
                label: 'Substation Overall Photo',
                bytes: photoBytes['substation_photo'],
                onTap: () => _pickPhoto('substation_photo'),
              ),
              const SizedBox(height: 10),
              _PhotoBox(
                label: 'Meter Photo *',
                bytes: photoBytes['meter_photo'],
                onTap: () => _pickPhoto('meter_photo'),
              ),
              const SizedBox(height: 10),
              _PhotoBox(
                label: 'Nameplate Photo',
                bytes: photoBytes['nameplate_photo'],
                onTap: () => _pickPhoto('nameplate_photo'),
              ),
              const SizedBox(height: 10),
              _PhotoBox(
                label: 'SLD Photo (optional)',
                bytes: photoBytes['sld_photo'],
                onTap: () => _pickPhoto('sld_photo'),
              ),
            ]),
          ),

          _Band('Observation & Remarks'),
          SeasTextField(label: 'Observation', controller: observationCtrl, hint: 'Site observation', maxLines: 3),
          const SizedBox(height: 10),
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
                saving ? 'Saving…' : 'Submit Substation Survey',
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

/// My substation surveys — open a draft / rejected survey to continue.
class SubstationSurveyListScreen extends StatefulWidget {
  const SubstationSurveyListScreen({super.key});

  @override
  State<SubstationSurveyListScreen> createState() => _SubstationSurveyListScreenState();
}

class _SubstationSurveyListScreenState extends State<SubstationSurveyListScreen> {
  List items = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await api.get('/substation-surveys?per_page=100');
      items = (res['data'] as List?) ?? [];
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  bool _editable(Map s) {
    final st = '${s['status'] ?? ''}'.toLowerCase();
    return st == 'draft' || st == 'rejected';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: SeasColors.white,
        elevation: 0,
        title: Text(
          'My Substation Surveys',
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : items.isEmpty
              ? SeasEmptyState(
                  title: error != null ? 'Could not load surveys' : 'No substation surveys yet',
                  subtitle: error ?? 'Start a new Substation Survey from the home screen.',
                  icon: Icons.holiday_village_outlined,
                )
              : RefreshIndicator(
                  color: SeasColors.volt,
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                    itemCount: items.length,
                    itemBuilder: (_, i) {
                      final s = items[i] as Map;
                      final name = '${s['substation_name'] ?? 'Substation'}';
                      final code = '${s['substation_code'] ?? ''}'.trim();
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: SeasCard(
                          padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                          onTap: !_editable(s)
                              ? null
                              : () async {
                                  final id = (s['id'] as num?)?.toInt();
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => SubstationSurveyFormScreen(serverId: id)),
                                  );
                                  _load();
                                },
                          child: Row(
                            children: [
                              const SeasIconTile(
                                icon: Icons.holiday_village_rounded,
                                bg: SeasColors.voltSoft,
                                fg: SeasColors.volt,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      code.isEmpty ? name : '$name · $code',
                                      style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      '${s['meter_number'] ?? 'Meter not captured'}',
                                      style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              SeasBadge(
                                '${s['display_status'] ?? s['status'] ?? ''}',
                                tone: badgeToneForStatus('${s['status']}'),
                              ),
                              const SizedBox(width: 4),
                              const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
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

class _GpsMini extends StatelessWidget {
  const _GpsMini({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 11)),
          const SizedBox(height: 2),
          Text(value, style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
        ],
      ),
    );
  }
}

class _PhotoBox extends StatelessWidget {
  const _PhotoBox({required this.label, required this.bytes, required this.onTap});
  final String label;
  final Uint8List? bytes;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 120,
        width: double.infinity,
        decoration: BoxDecoration(
          color: SeasColors.canvasSoft,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: SeasColors.ink100),
          image: bytes != null ? DecorationImage(image: MemoryImage(bytes!), fit: BoxFit.cover) : null,
        ),
        alignment: Alignment.center,
        child: bytes == null
            ? Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.photo_camera_outlined, color: SeasColors.volt),
                  const SizedBox(height: 6),
                  Text(label, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12)),
                ],
              )
            : null,
      ),
    );
  }
}
