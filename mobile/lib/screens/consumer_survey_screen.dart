import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import '../core/api_client.dart';
import '../main.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_glass_header.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';
import '../widgets/confirm_dialog.dart';
import 'consumer_survey_success_screen.dart';

/// Field verification after consumer is identified (or new consumer).
class ConsumerSurveyScreen extends StatefulWidget {
  const ConsumerSurveyScreen({
    super.key,
    required this.dtrSurvey,
    required this.pole,
    this.prefillConsumer,
    this.prefillIvrs,
    this.prefillMsn,
    this.remapToCurrentFeeder = false,
  });

  final Map<String, dynamic> dtrSurvey;
  final Map<String, dynamic> pole;
  final Map<String, dynamic>? prefillConsumer;
  final String? prefillIvrs;
  final String? prefillMsn;
  /// Surveyor confirmed master consumer was under another feeder — submit remaps.
  final bool remapToCurrentFeeder;

  @override
  State<ConsumerSurveyScreen> createState() => _ConsumerSurveyScreenState();
}

class _ConsumerSurveyScreenState extends State<ConsumerSurveyScreen> {
  final _picker = ImagePicker();

  bool submitting = false;
  bool gpsLoading = false;
  String? error;

  final ivrsCtrl = TextEditingController();
  final msnCtrl = TextEditingController();
  final nameCtrl = TextEditingController();
  final addressCtrl = TextEditingController();
  final phoneCtrl = TextEditingController();
  final remarksCtrl = TextEditingController();
  final latCtrl = TextEditingController();
  final lngCtrl = TextEditingController();
  final gpsCtrl = TextEditingController();
  final otherMakeCtrl = TextEditingController();

  Map<String, dynamic>? masterConsumer;
  bool addMode = false;
  String? phase;
  String? meterCondition;
  String? meterMake;
  bool meterMakeOther = false;
  String verificationStatus = 'Verified';
  String? meterPhotoPath;
  String? premisePhotoPath;
  Uint8List? meterPhotoBytes;
  Uint8List? premisePhotoBytes;

  static const meterConditions = ['Normal', 'Defective', 'Burnt'];
  static const phases = ['1PH', '3PH', '3PH 4CT'];
  static const knownMakes = ['(L&T) Schneider', 'HPL', '(Linkwell) Visiontek'];

  int get surveyId => widget.dtrSurvey['id'] as int;
  int get poleId => widget.pole['id'] as int;

  /// New MSN prefix (first 2 letters, case-insensitive) → meter make.
  /// PS → (L&T) Schneider, PH → HPL, PL → (Linkwell) Visiontek. Else null (Other).
  static String? makeFromMsn(String msn) {
    final t = msn.trim().toUpperCase();
    if (t.length < 2) return null;
    final prefix = t.substring(0, 2);
    switch (prefix) {
      case 'PS':
        return '(L&T) Schneider';
      case 'PH':
        return 'HPL';
      case 'PL':
        return '(Linkwell) Visiontek';
      default:
        return null;
    }
  }

  String? get _resolvedMeterMake {
    final auto = makeFromMsn(msnCtrl.text);
    if (auto != null) return auto;
    if (meterMakeOther) {
      final custom = otherMakeCtrl.text.trim();
      return custom.isEmpty ? null : custom;
    }
    return meterMake;
  }

  void _syncMakeFromMsn() {
    if (!mounted) return;
    final next = makeFromMsn(msnCtrl.text);
    final msnReady = msnCtrl.text.trim().length >= 2;
    setState(() {
      if (next != null) {
        meterMake = next;
        meterMakeOther = false;
      } else if (msnReady) {
        meterMakeOther = true;
        if (meterMake != null && knownMakes.contains(meterMake)) {
          meterMake = null;
        }
      } else {
        meterMake = null;
        meterMakeOther = false;
      }
    });
  }

  @override
  void initState() {
    super.initState();
    masterConsumer = widget.prefillConsumer;
    if (masterConsumer != null) {
      nameCtrl.text = '${masterConsumer!['name'] ?? ''}';
      addressCtrl.text = '${masterConsumer!['address'] ?? ''}';
      phoneCtrl.text = '${masterConsumer!['phone'] ?? ''}';
      phase = masterConsumer!['phase']?.toString();
      ivrsCtrl.text = '${masterConsumer!['ivrs'] ?? widget.prefillIvrs ?? ''}';
      msnCtrl.text = '${masterConsumer!['msn'] ?? widget.prefillMsn ?? ''}';
      verificationStatus = 'Verified';
      addMode = false;
    } else {
      ivrsCtrl.text = widget.prefillIvrs ?? '';
      msnCtrl.text = widget.prefillMsn ?? '';
      addMode = true;
      verificationStatus = 'New Consumer';
    }
    final auto = makeFromMsn(msnCtrl.text);
    if (auto != null) {
      meterMake = auto;
      meterMakeOther = false;
    } else if (msnCtrl.text.trim().length >= 2) {
      meterMakeOther = true;
      meterMake = null;
    }
    msnCtrl.addListener(_syncMakeFromMsn);
    _boot();
  }

  Future<void> _boot() async {
    // Auto-capture still runs (GPS lat/lng/accuracy) — UI block is hidden (#6).
    // Surveyor identity is persisted via auth token on the backend.
    await loadSavedUser();
    await _captureGps();
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    msnCtrl.removeListener(_syncMakeFromMsn);
    ivrsCtrl.dispose();
    msnCtrl.dispose();
    nameCtrl.dispose();
    addressCtrl.dispose();
    phoneCtrl.dispose();
    remarksCtrl.dispose();
    latCtrl.dispose();
    lngCtrl.dispose();
    gpsCtrl.dispose();
    otherMakeCtrl.dispose();
    super.dispose();
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
      latCtrl.text = pos.latitude.toStringAsFixed(7);
      lngCtrl.text = pos.longitude.toStringAsFixed(7);
      gpsCtrl.text = pos.accuracy.toStringAsFixed(0);
    } catch (_) {
    } finally {
      if (mounted) setState(() => gpsLoading = false);
    }
  }

  Future<void> _pick(bool meter) async {
    final x = await _picker.pickImage(source: ImageSource.camera, imageQuality: 72, maxWidth: 1600);
    if (x == null) return;
    Uint8List? bytes;
    try {
      bytes = await x.readAsBytes();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      if (meter) {
        meterPhotoPath = x.path;
        meterPhotoBytes = bytes;
      } else {
        premisePhotoPath = x.path;
        premisePhotoBytes = bytes;
      }
    });
  }

  Future<void> _submit() async {
    if (meterPhotoPath == null && meterPhotoBytes == null) {
      setState(() => error = 'Meter photo is mandatory.');
      return;
    }
    if (meterCondition == null) {
      setState(() => error = 'Select meter condition.');
      return;
    }
    if (addMode && nameCtrl.text.trim().isEmpty) {
      setState(() => error = 'Enter consumer name.');
      return;
    }
    final makeToSave = _resolvedMeterMake;
    if (msnCtrl.text.trim().length >= 2 && (makeToSave == null || makeToSave.isEmpty)) {
      setState(() => error = meterMakeOther
          ? 'Enter custom meter make (Other).'
          : 'Meter make missing — check New MSN / Make.');
      return;
    }

    final ok = await confirmSubmit(
      context,
      message: 'Are you sure you want to submit this consumer survey?',
    );
    if (!ok || !mounted) return;

    setState(() {
      submitting = true;
      error = null;
    });

    try {
      final fields = <String, String>{
        'pole_id': '$poleId',
        if (masterConsumer?['id'] != null) 'consumer_id': '${masterConsumer!['id']}',
        'consumer_name': nameCtrl.text.trim(),
        'phone': phoneCtrl.text.trim(),
        'ivrs': ivrsCtrl.text.trim(),
        'msn': msnCtrl.text.trim(),
        if (makeToSave != null && makeToSave.isNotEmpty) 'meter_make': makeToSave,
        if (phase != null) 'phase': phase!,
        'address': addressCtrl.text.trim(),
        // Auto-capture fields — still persisted, UI hidden
        'latitude': latCtrl.text.trim(),
        'longitude': lngCtrl.text.trim(),
        'gps_accuracy': gpsCtrl.text.trim(),
        'observation': remarksCtrl.text.trim(),
        'meter_condition': meterCondition!,
        'verification_status': verificationStatus,
        if (widget.remapToCurrentFeeder) 'remap_to_current_feeder': '1',
      };

      final useBytes = kIsWeb || (meterPhotoPath != null && meterPhotoPath!.startsWith('blob:'));
      final res = await api.postMultipart(
        path: '/consumer/$surveyId/verify',
        fields: fields,
        filePaths: useBytes
            ? null
            : {
                if (meterPhotoPath != null) 'meter_photo': meterPhotoPath!,
                if (premisePhotoPath != null) 'premise_photo': premisePhotoPath!,
              },
        fileBytes: {
          if (meterPhotoBytes != null && (useBytes || meterPhotoPath == null)) 'meter_photo': meterPhotoBytes!,
          if (premisePhotoBytes != null && (useBytes || premisePhotoPath == null)) 'premise_photo': premisePhotoBytes!,
        },
      );

      if (!mounted) return;
      final msg = res['message']?.toString() ?? 'Submitted';
      await Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ConsumerSurveySuccessScreen(
            dtrSurvey: widget.dtrSurvey,
            pole: widget.pole,
            message: msg,
          ),
        ),
      );
    } on ApiException catch (e) {
      final friendly = e.statusCode == 409
          ? (e.body['message']?.toString() ??
              'This consumer was already surveyed. Duplicate survey is not allowed.')
          : (e.body['message']?.toString() ?? e.message);
      setState(() => error = friendly);
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Field Verification',
        subtitle: 'Pole ${widget.pole['pole_no']} · Manager approval pending',
        onBack: () => Navigator.pop(context),
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
          if (widget.remapToCurrentFeeder)
            Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: SeasColors.warningSoft,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: SeasColors.warning.withValues(alpha: 0.35)),
              ),
              child: const Text(
                'This consumer was under another feeder/DTR in master. Submit will remap to the current feeder and overwrite master details.',
                style: TextStyle(color: SeasColors.warning, fontSize: 12, height: 1.35, fontWeight: FontWeight.w600),
              ),
            ),
          if (gpsLoading)
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: LinearProgressIndicator(color: SeasColors.volt, minHeight: 2),
            ),
          // Compact context only (DTR · Pole · Consumer progress) — no full hierarchy dump
          SeasCard(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
            child: Column(
              children: [
                Row(
                  children: [
                    Icon(Icons.electrical_services_rounded, size: 18, color: SeasColors.volt),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'DTR : ${widget.dtrSurvey['dtr_code'] ?? '—'} - ${widget.dtrSurvey['dtr_name'] ?? '—'}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 13, color: SeasColors.ink950),
                      ),
                    ),
                  ],
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 10),
                  child: Divider(height: 1, color: SeasColors.ink100),
                ),
                Row(
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          Icon(Icons.cell_tower_rounded, size: 16, color: SeasColors.volt),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              'Pole No. : ${widget.pole['pole_no'] ?? '—'}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12.5),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(width: 1, height: 18, color: SeasColors.ink100),
                    Expanded(
                      child: Row(
                        children: [
                          const SizedBox(width: 8),
                          Icon(Icons.people_alt_rounded, size: 16, color: SeasColors.volt),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              'Consumer : ${(widget.pole['surveyed_count'] as num?)?.toInt() ?? 0} of ${(widget.pole['houses_connected'] as num?)?.toInt() ?? 0}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12.5),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          _Band('Master / WFM Data', blue: true),
          if (masterConsumer != null && !addMode)
            SeasCard(
              padding: const EdgeInsets.all(12),
              child: Column(children: [
                _Kv('IVRS Number', '${masterConsumer!['ivrs'] ?? '—'}'),
                _Kv('Meter Serial (MSN)', '${masterConsumer!['msn'] ?? '—'}'),
                _Kv('Consumer Name', '${masterConsumer!['name'] ?? '—'}'),
                _Kv('Consumer Address', '${masterConsumer!['address'] ?? '—'}'),
                _Kv('Meter Type (Phase)', '${masterConsumer!['phase'] ?? '—'}'),
              ]),
            )
          else
            SeasCard(
              padding: const EdgeInsets.all(12),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(10)),
                child: const Text(
                  'Not found in master — enter new consumer details below.',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: SeasColors.voltDeep),
                ),
              ),
            ),
          if (addMode || masterConsumer == null) ...[
            const SizedBox(height: 10),
            SeasTextField(
              label: 'IVRS Number',
              controller: ivrsCtrl,
              hint: '10-digit IVRS',
              keyboardType: TextInputType.number,
              maxLength: 10,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(10),
              ],
            ),
            const SizedBox(height: 10),
            SeasTextField(label: 'Consumer Name', controller: nameCtrl, hint: 'Name'),
            const SizedBox(height: 10),
            SeasTextField(label: 'Consumer Address', controller: addressCtrl, hint: 'Address', maxLines: 2),
            const SizedBox(height: 10),
            SeasSelectField(
              label: 'Meter Type (Phase)',
              hint: 'Select',
              value: phase,
              options: phases.map((e) => SeasSelectOption(value: e, label: e)).toList(),
              onSelected: (o) => setState(() => phase = o.value as String?),
            ),
          ],
          _Band('Field Verification (Editable)', blue: true),
          SeasCard(
            padding: const EdgeInsets.all(12),
            child: Column(children: [
              SeasTextField(label: 'New MSN', controller: msnCtrl, hint: 'Scan / enter MSN'),
              const SizedBox(height: 10),
              if (makeFromMsn(msnCtrl.text) != null)
                _MakeAutoChip(value: meterMake)
              else if (meterMakeOther) ...[
                SeasSelectField(
                  label: 'Make',
                  hint: 'Select',
                  value: 'Other',
                  options: const [SeasSelectOption(value: 'Other', label: 'Other')],
                  onSelected: (_) => setState(() => meterMakeOther = true),
                ),
                const SizedBox(height: 10),
                SeasTextField(
                  label: 'Meter Make (Other)',
                  controller: otherMakeCtrl,
                  hint: 'Type meter make name',
                ),
              ] else
                const _MakeAutoChip(value: null),
              const SizedBox(height: 12),
              Row(children: [
                Expanded(
                  child: _PhotoTile(
                    label: 'Meter Photo (Mandatory)',
                    path: meterPhotoPath,
                    bytes: meterPhotoBytes,
                    onTap: () => _pick(true),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _PhotoTile(
                    label: 'Premise Photo (Optional)',
                    path: premisePhotoPath,
                    bytes: premisePhotoBytes,
                    onTap: () => _pick(false),
                  ),
                ),
              ]),
              const SizedBox(height: 12),
              SeasSelectField(
                label: 'Meter Condition',
                hint: 'Normal, Defective, Burnt',
                value: meterCondition,
                options: meterConditions.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                onSelected: (o) => setState(() => meterCondition = o.value as String?),
              ),
              const SizedBox(height: 12),
              SeasTextField(label: 'Mobile Number (Optional)', controller: phoneCtrl, hint: 'Optional', keyboardType: TextInputType.phone),
              const SizedBox(height: 12),
              SeasTextField(label: 'Remarks', controller: remarksCtrl, hint: 'Notes', maxLines: 3),
              const SizedBox(height: 8),
              _Kv('Consumer Verification Status', verificationStatus),
            ]),
          ),
          _Band('Submit Survey (Manager Approval Pending)', blue: false),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: submitting ? null : _submit,
              icon: const Icon(Icons.send_rounded),
              label: Text(submitting ? 'Submitting…' : 'Submit Survey'),
              style: FilledButton.styleFrom(
                backgroundColor: SeasColors.volt,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Band extends StatelessWidget {
  const _Band(this.label, {required this.blue});
  final String label;
  final bool blue;

  @override
  Widget build(BuildContext context) {
    final bg = blue ? const Color(0xFFF5F5F5) : SeasColors.voltSoft;
    final fg = blue ? SeasColors.ink950 : SeasColors.voltDeep;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 14, bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
      child: Text(label, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12, color: fg)),
    );
  }
}

class _Kv extends StatelessWidget {
  const _Kv(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: SeasColors.ink100))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(flex: 4, child: Text(label, style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600, fontSize: 12))),
          Expanded(flex: 5, child: Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12))),
        ],
      ),
    );
  }
}

class _PhotoTile extends StatelessWidget {
  const _PhotoTile({required this.label, required this.path, required this.onTap, this.bytes});
  final String label;
  final String? path;
  final Uint8List? bytes;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    Widget? preview;
    if (bytes != null && bytes!.isNotEmpty) {
      preview = Image.memory(bytes!, fit: BoxFit.cover, width: double.infinity, height: double.infinity);
    } else if (path != null && !kIsWeb) {
      try {
        final f = File(path!);
        if (f.existsSync()) {
          preview = Image.file(f, fit: BoxFit.cover, width: double.infinity, height: double.infinity);
        }
      } catch (_) {}
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 140,
        decoration: BoxDecoration(
          color: SeasColors.canvasSoft,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: preview != null ? SeasColors.volt.withValues(alpha: 0.35) : SeasColors.ink200),
        ),
        clipBehavior: Clip.antiAlias,
        child: preview != null
            ? Stack(
                fit: StackFit.expand,
                children: [
                  preview,
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
            : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Icon(Icons.photo_camera_outlined, color: SeasColors.ink400),
                const SizedBox(height: 6),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text(label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
                ),
              ]),
      ),
    );
  }
}

class _MakeAutoChip extends StatelessWidget {
  const _MakeAutoChip({required this.value});
  final String? value;

  @override
  Widget build(BuildContext context) {
    final filled = value != null && value!.isNotEmpty;
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
          Icon(Icons.precision_manufacturing_outlined, size: 18, color: filled ? SeasColors.voltDeep : SeasColors.ink400),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Make (Auto)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12, color: SeasColors.ink400)),
                const SizedBox(height: 2),
                Text(
                  filled ? value! : 'Enter / scan New MSN (PS/PH/PL)',
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14, color: SeasColors.ink950),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
