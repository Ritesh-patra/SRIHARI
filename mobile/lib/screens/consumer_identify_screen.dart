import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import '../core/api_client.dart';
import '../core/msn_extractor.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_glass_header.dart';
import 'consumer_survey_screen.dart';
import 'consumer_survey_success_screen.dart';
import 'msn_scan_screen.dart';

class ConsumerIdentifyScreen extends StatefulWidget {
  const ConsumerIdentifyScreen({super.key, required this.dtrSurvey, required this.pole});
  final Map<String, dynamic> dtrSurvey;
  final Map<String, dynamic> pole;

  @override
  State<ConsumerIdentifyScreen> createState() => _ConsumerIdentifyScreenState();
}

class _ConsumerIdentifyScreenState extends State<ConsumerIdentifyScreen> {
  final _picker = ImagePicker();
  late Map<String, dynamic> pole;
  bool busy = false;
  String? error;
  http.Client? _searchClient;
  int _searchGen = 0;

  int get surveyId => widget.dtrSurvey['id'] as int;
  int get poleId => pole['id'] as int;
  int get surveyed => (pole['surveyed_count'] as num?)?.toInt() ?? 0;
  int get expected => (pole['houses_connected'] as num?)?.toInt() ?? 0;

  @override
  void initState() {
    super.initState();
    pole = Map<String, dynamic>.from(widget.pole);
  }

  @override
  void dispose() {
    _searchClient?.close();
    super.dispose();
  }

  void _cancelInFlightSearch() {
    _searchClient?.close();
    _searchClient = null;
    _searchGen++;
  }

  Future<void> _refreshPole() async {
    try {
      final res = await api.get('/consumer/$surveyId/poles');
      final list = (res['poles'] as List?) ?? [];
      final match = list.where((p) => p['id'] == poleId);
      if (match.isNotEmpty && mounted) {
        setState(() => pole = Map<String, dynamic>.from(match.first as Map));
      }
    } catch (_) {}
  }

  Future<void> _searchAndOpen({String? ivrs, String? msn}) async {
    _cancelInFlightSearch();
    final client = http.Client();
    _searchClient = client;
    final gen = _searchGen;

    setState(() {
      busy = true;
      error = null;
    });
    try {
      // Zone-scoped master search (user zone + current DTR/feeder zone).
      final feederId = widget.dtrSurvey['feeder_id'];
      final dtrId = widget.dtrSurvey['dtr_id'];
      final zoneId = widget.dtrSurvey['zone_id'];
      final q = StringBuffer('/consumer/search?');
      if (ivrs != null && ivrs.isNotEmpty) q.write('ivrs=${Uri.encodeQueryComponent(ivrs)}&');
      if (msn != null && msn.isNotEmpty) q.write('msn=${Uri.encodeQueryComponent(msn)}&');
      if (feederId != null) q.write('feeder_id=$feederId&');
      if (dtrId != null) q.write('dtr_id=$dtrId&');
      if (zoneId != null) q.write('zone_id=$zoneId');

      final res = await api.get(
        q.toString(),
        timeout: const Duration(seconds: 12),
        client: client,
      );
      if (!mounted || gen != _searchGen) return;

      Map<String, dynamic>? consumer;
      if (res['found'] == true && res['consumer'] is Map) {
        consumer = Map<String, dynamic>.from(res['consumer'] as Map);
      }
      final mismatch = res['mismatch'] == true;
      final zoneEmpty = res['zone_scope_empty'] == true;
      final apiMessage = (res['message'] as String?)?.trim();
      final mappedFeeder = res['mapped_feeder'] is Map
          ? Map<String, dynamic>.from(res['mapped_feeder'] as Map)
          : null;
      final mappedDtr = res['mapped_dtr'] is Map
          ? Map<String, dynamic>.from(res['mapped_dtr'] as Map)
          : null;

      setState(() => busy = false);

      if (zoneEmpty) {
        setState(() => error = apiMessage?.isNotEmpty == true
            ? apiMessage
            : 'No zone assigned. Ask manager to assign your zone.');
        return;
      }

      if (consumer == null) {
        final action = await _showNotFoundSheet(
          ivrs: ivrs,
          msn: msn,
          apiMessage: apiMessage,
        );
        if (!mounted || gen != _searchGen) return;
        if (action == _NotFoundAction.add) {
          await _openSurvey(consumer: null, ivrs: ivrs, msn: msn);
        } else if (action == _NotFoundAction.searchAgain) {
          if (ivrs != null && ivrs.isNotEmpty) {
            await _promptIvrs();
          } else {
            await _promptMsn();
          }
        }
        return;
      }

      // Soft warning — do not block. Continue remaps master to current feeder on submit.
      if (mismatch) {
        final cont = await _confirmFeederMismatch(
          consumer: consumer,
          mappedFeeder: mappedFeeder,
          mappedDtr: mappedDtr,
        );
        if (!cont || !mounted || gen != _searchGen) return;
      }

      final action = await _showFoundResult(
        consumer: consumer,
        ivrs: ivrs,
        msn: msn,
        feederMismatch: mismatch,
        mappedFeeder: mappedFeeder,
        mappedDtr: mappedDtr,
      );
      if (action == null || action == _FoundAction.cancel || !mounted || gen != _searchGen) return;
      await _openSurvey(
        consumer: consumer,
        ivrs: ivrs,
        msn: msn,
        remapToCurrentFeeder: mismatch,
      );
    } on TimeoutException {
      if (mounted && gen == _searchGen) {
        setState(() => error = 'Search timed out. Check network and try again.');
      }
    } catch (e) {
      if (mounted && gen == _searchGen) {
        final msg = e.toString().replaceFirst('Exception: ', '');
        // Client closed after cancel — ignore.
        if (msg.toLowerCase().contains('client is closed') ||
            msg.toLowerCase().contains('connection closed')) {
          return;
        }
        setState(() => error = msg);
      }
    } finally {
      if (identical(_searchClient, client)) {
        client.close();
        _searchClient = null;
      }
      if (mounted && gen == _searchGen) setState(() => busy = false);
    }
  }

  Future<void> _openSurvey({
    Map<String, dynamic>? consumer,
    String? ivrs,
    String? msn,
    bool remapToCurrentFeeder = false,
  }) async {
    final done = await Navigator.push<Object?>(
      context,
      MaterialPageRoute(
        builder: (_) => ConsumerSurveyScreen(
          dtrSurvey: widget.dtrSurvey,
          pole: pole,
          prefillConsumer: consumer,
          prefillIvrs: ivrs,
          prefillMsn: msn,
          remapToCurrentFeeder: remapToCurrentFeeder,
        ),
      ),
    );
    if (!mounted) return;
    if (done == ConsumerSurveyNav.nextPole || done == ConsumerSurveyNav.addPole) {
      Navigator.pop(context, done);
      return;
    }
    if (done == true || done == ConsumerSurveyNav.nextConsumer) {
      await _refreshPole();
    }
  }

  /// Soft remap warning — Continue allows form fill; Cancel aborts.
  Future<bool> _confirmFeederMismatch({
    required Map<String, dynamic> consumer,
    Map<String, dynamic>? mappedFeeder,
    Map<String, dynamic>? mappedDtr,
  }) async {
    final feederLabel = mappedFeeder == null
        ? 'another feeder'
        : '${mappedFeeder['code'] ?? ''} ${mappedFeeder['name'] ?? ''}'.trim();
    final dtrLabel = mappedDtr == null
        ? 'another DTR'
        : '${mappedDtr['code'] ?? ''} ${mappedDtr['name'] ?? ''}'.trim();
    final currentFeeder =
        '${widget.dtrSurvey['feeder_code'] ?? ''} ${widget.dtrSurvey['feeder_name'] ?? widget.dtrSurvey['feeder']?['name'] ?? ''}'
            .trim();
    final currentDtr =
        '${widget.dtrSurvey['dtr_code'] ?? ''} ${widget.dtrSurvey['dtr_name'] ?? ''}'.trim();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Different feeder / DTR', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        content: Text(
          'This consumer is mapped under another feeder/DTR in master'
          '${feederLabel.isNotEmpty ? ' ($feederLabel · $dtrLabel)' : ''}.\n\n'
          'Continue will remap to current feeder'
          '${currentFeeder.isNotEmpty || currentDtr.isNotEmpty ? ' ($currentFeeder · $currentDtr)' : ''} '
          'on submit.\n\n'
          'Consumer: ${consumer['name'] ?? '—'} · IVRS ${consumer['ivrs'] ?? '—'}',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
    return ok == true;
  }

  /// Found in master — confirm and continue, or cancel.
  Future<_FoundAction?> _showFoundResult({
    required Map<String, dynamic> consumer,
    String? ivrs,
    String? msn,
    bool feederMismatch = false,
    Map<String, dynamic>? mappedFeeder,
    Map<String, dynamic>? mappedDtr,
  }) {
    return showModalBottomSheet<_FoundAction>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        void pick(_FoundAction action) {
          Navigator.pop(ctx, action);
        }

        final mappedLabel = [
          if (mappedFeeder != null) '${mappedFeeder['code'] ?? ''} ${mappedFeeder['name'] ?? ''}'.trim(),
          if (mappedDtr != null) '${mappedDtr['code'] ?? ''} ${mappedDtr['name'] ?? ''}'.trim(),
        ].where((s) => s.isNotEmpty).join(' · ');

        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: SafeArea(
            top: false,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'Fetch From Master / WFM Data',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17, color: SeasColors.ink950),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    feederMismatch
                        ? 'Mapped under another feeder — continue remaps on submit'
                        : 'View only — confirm this is the right consumer',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: feederMismatch ? SeasColors.warning : SeasColors.ink400,
                      fontSize: 12,
                      fontWeight: feederMismatch ? FontWeight.w600 : FontWeight.w400,
                    ),
                  ),
                  if (feederMismatch) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: SeasColors.warningSoft,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: SeasColors.warning.withValues(alpha: 0.35)),
                      ),
                      child: Text(
                        'Master mapping: ${mappedLabel.isEmpty ? 'another feeder/DTR' : mappedLabel}. '
                        'Submit will move this consumer under the current feeder/DTR.',
                        style: const TextStyle(color: SeasColors.warning, fontSize: 12, height: 1.35, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: SeasColors.canvasSoft,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: SeasColors.ink100),
                    ),
                    child: Column(
                      children: [
                        _MasterRow('IVRS Number', '${consumer['ivrs'] ?? ivrs ?? '—'}'),
                        _MasterRow('New Meter Serial Number', '${consumer['msn'] ?? msn ?? '—'}'),
                        _MasterRow('Consumer Name', '${consumer['name'] ?? '—'}'),
                        _MasterRow('Consumer Address', '${consumer['address'] ?? '—'}'),
                        _MasterRow('Meter Type ( Phase )', '${consumer['phase'] ?? '—'}'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  _NotFoundOption(
                    icon: Icons.check_circle_rounded,
                    title: 'Continue',
                    subtitle: feederMismatch
                        ? 'Open verification — remap on submit'
                        : 'Correct consumer — open field verification',
                    accent: SeasColors.volt,
                    filled: true,
                    onTap: () => pick(_FoundAction.continueOk),
                  ),
                  const SizedBox(height: 10),
                  _NotFoundOption(
                    icon: Icons.close_rounded,
                    title: 'Cancel',
                    subtitle: 'Stay on identify screen',
                    accent: SeasColors.ink400,
                    onTap: () => pick(_FoundAction.cancel),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  /// Not found — three clear actions (SEAS brand, not mock colors).
  Future<_NotFoundAction?> _showNotFoundSheet({String? ivrs, String? msn, String? apiMessage}) {
    final searched = <String>[];
    if (ivrs != null && ivrs.isNotEmpty) searched.add('IVRS $ivrs');
    if (msn != null && msn.isNotEmpty) searched.add('MSN $msn');
    final searchedLabel = searched.isEmpty ? 'your search' : searched.join(' · ');
    final detail = (apiMessage != null && apiMessage.isNotEmpty)
        ? apiMessage
        : 'No master record for $searchedLabel in your zone';

    return showModalBottomSheet<_NotFoundAction>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: SafeArea(
            top: false,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Container(
                      height: 48,
                      width: 48,
                      decoration: BoxDecoration(
                        color: SeasColors.voltSoft,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.person_search_rounded, color: SeasColors.volt, size: 26),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Consumer not found',
                            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.ink950),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            detail,
                            style: const TextStyle(color: SeasColors.ink400, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: SeasColors.canvasSoft,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: SeasColors.ink100),
                  ),
                  child: Text(
                    'Searched: $searchedLabel · your assigned zone only',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13, color: SeasColors.ink950),
                  ),
                ),
                const SizedBox(height: 16),
                _NotFoundOption(
                  icon: Icons.person_add_alt_1_rounded,
                  title: 'Add New Consumer',
                  subtitle: 'Register under this DTR & Pole',
                  accent: SeasColors.volt,
                  filled: true,
                  onTap: () => Navigator.pop(ctx, _NotFoundAction.add),
                ),
                const SizedBox(height: 10),
                _NotFoundOption(
                  icon: Icons.refresh_rounded,
                  title: 'Search Again',
                  subtitle: 'Clear and retry IVRS / MSN',
                  accent: SeasColors.ink950,
                  onTap: () => Navigator.pop(ctx, _NotFoundAction.searchAgain),
                ),
                const SizedBox(height: 10),
                _NotFoundOption(
                  icon: Icons.close_rounded,
                  title: 'Cancel',
                  subtitle: 'Return to identification options',
                  accent: SeasColors.ink400,
                  onTap: () => Navigator.pop(ctx, _NotFoundAction.cancel),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _scanMsn() async {
    final msn = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const MsnScanScreen()),
    );
    if (msn == null || msn.isEmpty) return;
    final clean = MsnExtractor.extract(msn) ?? msn.trim().toUpperCase();
    await _searchAndOpen(msn: clean);
  }

  Future<void> _promptMsn() async {
    final msn = await showDialog<String>(
      context: context,
      builder: (_) => const _MsnEntryDialog(),
    );
    if (msn == null || msn.isEmpty || !mounted) return;
    final clean = MsnExtractor.extract(msn) ?? msn.trim().toUpperCase();
    await _searchAndOpen(msn: clean);
  }

  Future<void> _promptIvrs() async {
    final ivrs = await showDialog<String>(
      context: context,
      builder: (_) => const _IvrsEntryDialog(),
    );
    if (ivrs == null || ivrs.length != 10 || !mounted) return;
    await _searchAndOpen(ivrs: ivrs);
  }

  /// Nested under Consumer Not Accessible — pick access issue vs PDC, then reason form.
  Future<void> _openNotAccessibleFlow() async {
    final pdc = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: SafeArea(
            top: false,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Consumer Not Accessible',
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.ink950),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Select why the consumer could not be surveyed',
                  style: TextStyle(color: SeasColors.ink400, fontSize: 13),
                ),
                const SizedBox(height: 16),
                _NotFoundOption(
                  icon: Icons.lock_person_outlined,
                  title: 'Access Not Available',
                  subtitle: 'House locked / meter not reachable',
                  accent: SeasColors.volt,
                  onTap: () => Navigator.pop(ctx, false),
                ),
                const SizedBox(height: 10),
                _NotFoundOption(
                  icon: Icons.link_off_rounded,
                  title: 'Permanently Disconnected',
                  subtitle: 'Meter removed / consumer shifted',
                  accent: SeasColors.volt,
                  onTap: () => Navigator.pop(ctx, true),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: Text(
                    'Cancel',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: SeasColors.ink400),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
    if (pdc == null || !mounted) return;
    await _openException(pdc: pdc);
  }

  Future<void> _openException({required bool pdc}) async {
    final reasons = pdc
        ? const [
            'DISCOM Removed Meter',
            'Consumer Shifted Permanently',
            'Service Line Removed',
            'Meter Burnt & Removed',
            'Other (Please Specify)',
          ]
        : const [
            'Premises Locked',
            'Meter Installed Inside House',
            'Meter Not Found',
            'Access Not Available',
            'Consumer Not Available',
            'Other (Please Specify)',
          ];

    String? reason = reasons.first;
    final remarks = TextEditingController();
    String? photoPath;
    XFile? photoWeb;
    String? localError;

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setModal) {
          Future<void> capture() async {
            final x = await _picker.pickImage(source: ImageSource.camera, imageQuality: 72, maxWidth: 1600);
            if (x == null) return;
            setModal(() {
              photoPath = x.path;
              photoWeb = x;
              localError = null;
            });
          }

          Future<void> save() async {
            if (photoPath == null && photoWeb == null) {
              setModal(() => localError = 'Photo is mandatory.');
              return;
            }
            try {
              List<int>? bytes;
              if (kIsWeb && photoWeb != null) bytes = await photoWeb!.readAsBytes();
              await api.postMultipart(
                path: '/consumer/$surveyId/exception',
                fields: {
                  'pole_id': '$poleId',
                  'survey_flag': pdc ? 'pdc' : 'not_accessible',
                  'reason': reason!,
                  'observation': remarks.text.trim(),
                },
                filePaths: kIsWeb || photoPath == null ? null : {'meter_photo': photoPath!},
                fileBytes: bytes == null ? null : {'meter_photo': bytes},
              );
              if (ctx.mounted) Navigator.pop(ctx, true);
            } catch (e) {
              setModal(() => localError = e.toString().replaceFirst('Exception: ', ''));
            }
          }

          return Padding(
            padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
            child: Container(
              constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(ctx).height * 0.92),
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
              ),
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 8, 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            pdc ? 'Permanently Disconnected' : 'Not Accessible',
                            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16),
                          ),
                        ),
                        IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                      ],
                    ),
                  ),
                  const Divider(height: 1),
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
                      children: [
                        Text('Select Reason', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                        ...reasons.map((r) {
                          final selected = reason == r;
                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            dense: true,
                            onTap: () => setModal(() => reason = r),
                            leading: Icon(
                              selected ? Icons.radio_button_checked : Icons.radio_button_off,
                              color: selected ? SeasColors.volt : SeasColors.ink400,
                            ),
                            title: Text(r, style: TextStyle(fontWeight: selected ? FontWeight.w700 : FontWeight.w500, fontSize: 14)),
                          );
                        }),
                        const SizedBox(height: 8),
                        Text.rich(TextSpan(children: [
                          TextSpan(text: 'Capture Photo ', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                          const TextSpan(text: '(Mandatory)', style: TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w700)),
                        ])),
                        const SizedBox(height: 8),
                        InkWell(
                          onTap: capture,
                          borderRadius: BorderRadius.circular(14),
                          child: Container(
                            height: 120,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: SeasColors.ink200),
                              color: SeasColors.canvasSoft,
                            ),
                            child: photoPath != null && !kIsWeb
                                ? ClipRRect(borderRadius: BorderRadius.circular(14), child: Image.file(File(photoPath!), fit: BoxFit.cover, width: double.infinity))
                                : photoPath != null || photoWeb != null
                                    ? const Icon(Icons.check_circle, color: SeasColors.volt, size: 40)
                                    : const Column(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Icon(Icons.photo_camera_outlined, color: SeasColors.ink400),
                                          SizedBox(height: 6),
                                          Text('Tap to capture photo', style: TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600)),
                                        ],
                                      ),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: remarks,
                          maxLength: 250,
                          maxLines: 3,
                          decoration: const InputDecoration(labelText: 'Remarks (Optional)', border: OutlineInputBorder()),
                        ),
                        if (localError != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(localError!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
                          ),
                      ],
                    ),
                  ),
                  SafeArea(
                    top: false,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                      child: Row(
                        children: [
                          Expanded(child: OutlinedButton(onPressed: () => Navigator.pop(ctx), child: const Text('CANCEL'))),
                          const SizedBox(width: 10),
                          Expanded(
                            child: FilledButton(
                              onPressed: save,
                              style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                              child: const Text('SAVE'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        });
      },
    );

    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(pdc ? 'PDC saved on this pole' : 'Not accessible saved'),
        backgroundColor: SeasColors.ink950,
      ));
      await _refreshPole();
    }
  }

  @override
  Widget build(BuildContext context) {
    final dtrName = '${widget.dtrSurvey['dtr_name'] ?? '—'}';
    final poleNo = '${pole['pole_no']}';

    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Consumer Identification',
        subtitle: 'DTR → Pole $poleNo audit',
        onBack: () => Navigator.pop(context),
      ),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
            children: [
              if (error != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(14)),
                  child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
                ),
              _GlassInfo(
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(color: SeasColors.ink950, borderRadius: BorderRadius.circular(12)),
                      child: const Icon(Icons.factory_outlined, color: Colors.white, size: 18),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('DTR Name', style: TextStyle(color: SeasColors.ink400, fontSize: 11)),
                          Text(dtrName, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              _GlassInfo(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Pole No.', style: TextStyle(color: SeasColors.ink400, fontSize: 11)),
                          Text(poleNo, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 22, color: SeasColors.ink950)),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(colors: [SeasColors.volt, SeasColors.voltDeep]),
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: SeasShadows.glow,
                      ),
                      child: Column(
                        children: [
                          Text('$surveyed/$expected', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: Colors.white, fontSize: 18)),
                          Text('Surveyed', style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 10, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text('Identify Consumer', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 4),
              const Text('IVRS / MSN search within your assigned zone only.', style: TextStyle(color: SeasColors.ink400, fontSize: 12)),
              const SizedBox(height: 12),
              _ActionTile(
                icon: Icons.qr_code_scanner_rounded,
                title: 'Scan MSN',
                subtitle: 'Camera → meter S.No. barcode',
                onTap: busy ? () {} : _scanMsn,
              ),
              _ActionTile(
                icon: Icons.pin_outlined,
                title: 'Enter MSN',
                subtitle: 'Type serial like PL00213258',
                onTap: busy ? () {} : _promptMsn,
              ),
              _ActionTile(
                icon: Icons.badge_outlined,
                title: 'Search by IVRS',
                subtitle: '10-digit IVRS · your zone',
                onTap: busy ? () {} : _promptIvrs,
                dark: true,
              ),
              const SizedBox(height: 14),
              Row(children: [
                const Expanded(child: Divider()),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text('OR', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: SeasColors.ink400)),
                ),
                const Expanded(child: Divider()),
              ]),
              const SizedBox(height: 12),
              _ActionTile(
                icon: Icons.lock_person_outlined,
                title: 'Consumer Not Accessible',
                subtitle: 'Locked, unreachable, or permanently disconnected',
                onTap: busy ? () {} : _openNotAccessibleFlow,
                danger: true,
              ),
            ],
          ),
          if (busy)
            Positioned.fill(
              child: ColoredBox(
                color: Colors.white.withValues(alpha: 0.45),
                child: const Center(
                  child: SizedBox(
                    width: 28,
                    height: 28,
                    child: CircularProgressIndicator(strokeWidth: 2.5, color: SeasColors.volt),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _IvrsEntryDialog extends StatefulWidget {
  const _IvrsEntryDialog();

  @override
  State<_IvrsEntryDialog> createState() => _IvrsEntryDialogState();
}

class _IvrsEntryDialogState extends State<_IvrsEntryDialog> {
  late final TextEditingController _ctrl;
  var _closing = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _ctrl = TextEditingController();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  void _onChanged(String v) {
    final digits = v.replaceAll(RegExp(r'\D'), '');
    if (digits != v) {
      _ctrl.value = TextEditingValue(text: digits, selection: TextSelection.collapsed(offset: digits.length));
    }
    setState(() {});
    _debounce?.cancel();
    if (digits.length == 10 && !_closing) {
      // 300ms debounce so last keystroke isn't racing the auto-search.
      _debounce = Timer(const Duration(milliseconds: 300), () {
        if (!mounted || _closing || _ctrl.text.length != 10) return;
        _closing = true;
        Navigator.pop(context, _ctrl.text);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final len = _ctrl.text.length;
    return AlertDialog(
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      title: Text('Search by IVRS', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _ctrl,
            autofocus: true,
            keyboardType: TextInputType.number,
            maxLength: 10,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(10),
            ],
            onChanged: _onChanged,
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 18, letterSpacing: 1.2),
            decoration: InputDecoration(
              labelText: 'IVRS Number',
              hintText: '10-digit IVRS',
              counterText: '$len / 10',
              helperText: 'Searches your assigned zone only',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: SeasColors.volt, width: 1.5),
              ),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: len == 10 ? () => Navigator.pop(context, _ctrl.text) : null,
          style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950),
          child: const Text('Search'),
        ),
      ],
    );
  }
}

class _MsnEntryDialog extends StatefulWidget {
  const _MsnEntryDialog();

  @override
  State<_MsnEntryDialog> createState() => _MsnEntryDialogState();
}

class _MsnEntryDialogState extends State<_MsnEntryDialog> {
  late final TextEditingController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = TextEditingController();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      title: Text('Enter MSN', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
      content: TextField(
        controller: _ctrl,
        autofocus: true,
        textCapitalization: TextCapitalization.characters,
        decoration: const InputDecoration(
          labelText: 'Meter Serial',
          hintText: 'PL00213258',
          helperText: 'Searches your assigned zone only',
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: () {
            final v = _ctrl.text.trim();
            if (v.isNotEmpty) Navigator.pop(context, v);
          },
          style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
          child: const Text('Search'),
        ),
      ],
    );
  }
}

enum _NotFoundAction { add, searchAgain, cancel }

enum _FoundAction { continueOk, cancel }

class _NotFoundOption extends StatelessWidget {
  const _NotFoundOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.accent,
    required this.onTap,
    this.filled = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color accent;
  final VoidCallback onTap;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: filled ? SeasColors.volt : SeasColors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          constraints: const BoxConstraints(minHeight: 64),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: filled ? SeasColors.volt : SeasColors.ink100),
          ),
          child: Row(
            children: [
              Container(
                height: 44,
                width: 44,
                decoration: BoxDecoration(
                  color: filled ? Colors.white.withValues(alpha: 0.18) : accent.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: filled ? Colors.white : accent),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        color: filled ? Colors.white : SeasColors.ink950,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: filled ? Colors.white.withValues(alpha: 0.85) : SeasColors.ink400,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: filled ? Colors.white : accent),
            ],
          ),
        ),
      ),
    );
  }
}

class _MasterRow extends StatelessWidget {
  const _MasterRow(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: SeasColors.ink100)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 5,
            child: Container(
              color: SeasColors.ink50,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: SeasColors.ink950)),
            ),
          ),
          Expanded(
            flex: 6,
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12)),
            ),
          ),
        ],
      ),
    );
  }
}

class _GlassInfo extends StatelessWidget {
  const _GlassInfo({required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: SeasColors.ink100),
        boxShadow: SeasShadows.card,
      ),
      child: child,
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.danger = false,
    this.dark = false,
  });
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool danger;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final accent = danger ? SeasColors.volt : (dark ? SeasColors.ink950 : SeasColors.volt);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(18),
          child: Container(
            padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: danger ? SeasColors.volt.withValues(alpha: 0.25) : SeasColors.ink100),
            ),
            child: Row(
              children: [
                Container(
                  height: 46,
                  width: 46,
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: accent),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                      const SizedBox(height: 2),
                      Text(subtitle, style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right_rounded, color: accent),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
