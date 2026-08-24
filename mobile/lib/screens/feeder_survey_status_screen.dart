import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import '../core/api_client.dart';
import '../core/api_config.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';
import '../widgets/confirm_dialog.dart';
import 'dtr_survey_form.dart';
import 'feeder_survey_form.dart';

/// Feeder Survey Status — track surveys; upload SLD after Finish DTR.
class FeederSurveyStatusScreen extends StatefulWidget {
  const FeederSurveyStatusScreen({
    super.key,
    this.initialFilter = 'all',
    this.autoOpenSldSurvey,
    this.bannerMessage,
  });

  /// Prefill chip filter (e.g. `sld_pending` after Finish DTR).
  final String initialFilter;
  /// When set, opens SLD upload for this survey after list load (Finish DTR path).
  final Map<String, dynamic>? autoOpenSldSurvey;
  /// Optional banner (e.g. SLD Verification — upload required).
  final String? bannerMessage;

  @override
  State<FeederSurveyStatusScreen> createState() => _FeederSurveyStatusScreenState();
}

class _FeederSurveyStatusScreenState extends State<FeederSurveyStatusScreen> {
  List items = [];
  bool loading = true;
  String? error;
  late String filter; // all | pending_dtr | sld_pending | pending | rejected | approved
  bool _didAutoOpenSld = false;

  @override
  void initState() {
    super.initState();
    filter = widget.initialFilter;
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await api.get('/feeder-surveys?per_page=100');
      items = (res['data'] as List?) ?? [];
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
      if (mounted) _maybeAutoOpenSld();
    }
  }

  Future<void> _maybeAutoOpenSld() async {
    if (_didAutoOpenSld || widget.autoOpenSldSurvey == null) return;
    _didAutoOpenSld = true;
    final seed = widget.autoOpenSldSurvey!;
    final id = (seed['id'] as num?)?.toInt();
    Map? fromList;
    if (id != null) {
      final match = items.whereType<Map>().where((s) => (s['id'] as num?)?.toInt() == id);
      if (match.isNotEmpty) fromList = match.first;
    }
    final survey = Map<String, dynamic>.from(fromList ?? seed);
    await Future<void>.delayed(const Duration(milliseconds: 120));
    if (!mounted) return;
    await _openSld(survey);
  }

  String _statusOf(Map s) => '${s['status'] ?? ''}'.toLowerCase();

  String _displayLabel(Map s) {
    final fromApi = '${s['display_status'] ?? ''}'.trim();
    if (fromApi.isNotEmpty) return fromApi;
    switch (_statusOf(s)) {
      case 'draft':
        return 'Pending DTR Survey';
      case 'sld_pending':
        return 'SLD Verification Pending';
      case 'pending_approval':
      case 'pending':
        return 'Pending Approval';
      case 'approved':
      case 'completed':
        return 'Approved';
      case 'rejected':
        return 'Rejected';
      default:
        return _statusOf(s).isEmpty ? '—' : _statusOf(s);
    }
  }

  bool _needsSld(Map s) {
    final st = _statusOf(s);
    return st == 'sld_pending' || (st == 'rejected' && '${s['sld_photo'] ?? ''}'.trim().isEmpty);
  }

  List<Map> get _filtered {
    final all = items.whereType<Map>().toList();
    switch (filter) {
      case 'pending_dtr':
        return all.where((s) => _statusOf(s) == 'draft').toList();
      case 'sld_pending':
        return all.where((s) => _statusOf(s) == 'sld_pending' || _needsSld(s)).toList();
      case 'pending':
        return all.where((s) {
          final st = _statusOf(s);
          return st == 'pending_approval' || st == 'pending';
        }).toList();
      case 'rejected':
        return all.where((s) => _statusOf(s).contains('reject')).toList();
      case 'approved':
        return all.where((s) {
          final st = _statusOf(s);
          return st.contains('approv') || st.contains('complet');
        }).toList();
      default:
        return all;
    }
  }

  int _count(String key) {
    final all = items.whereType<Map>();
    switch (key) {
      case 'pending_dtr':
        return all.where((s) => _statusOf(s) == 'draft').length;
      case 'sld_pending':
        return all.where((s) => _statusOf(s) == 'sld_pending').length;
      case 'pending':
        return all.where((s) {
          final st = _statusOf(s);
          return st == 'pending_approval' || st == 'pending';
        }).length;
      case 'rejected':
        return all.where((s) => _statusOf(s).contains('reject')).length;
      case 'approved':
        return all.where((s) {
          final st = _statusOf(s);
          return st.contains('approv') || st.contains('complet');
        }).length;
      default:
        return all.length;
    }
  }

  Future<void> _openSld(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => FeederSldUploadScreen(survey: Map<String, dynamic>.from(s))),
    );
    if (changed == true) _load();
  }

  Future<void> _openEdit(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    final st = _statusOf(s);
    if (id == null || (st != 'draft' && st != 'rejected')) return;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => FeederSurveyFormScreen(serverId: id)),
    );
    _load();
  }

  Future<void> _openContinueDtr(Map s) async {
    final prefill = <String, dynamic>{
      'region_id': s['region_id'],
      'circle_id': s['circle_id'],
      'division_id': s['division_id'],
      'zone_id': s['zone_id'],
      'substation_id': s['substation_id'],
      'feeder_id': s['feeder_id'],
      'feeder_code': s['feeder_code'],
      'feeder_name': s['feeder_name'],
      'feeder_survey_id': s['id'],
    };
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DtrSurveyFormScreen(
          prefill: prefill,
          fromFeederFlow: true,
          feederSurveyId: (s['id'] as num?)?.toInt(),
        ),
      ),
    );
    _load();
  }

  bool _canDelete(Map s) {
    if (s.containsKey('can_delete')) return s['can_delete'] == true;
    // Fallback if older API omits can_delete: non-approved statuses only.
    final st = _statusOf(s);
    return st == 'draft' ||
        st == 'sld_pending' ||
        st == 'pending_approval' ||
        st == 'pending' ||
        st == 'rejected';
  }

  Future<void> _deleteSurvey(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null || !_canDelete(s)) return;
    final name = '${s['feeder_name'] ?? 'Feeder'}';
    final code = '${s['feeder_code'] ?? ''}'.trim();
    final label = code.isEmpty ? name : '$name · $code';
    final ok = await confirmSubmit(
      context,
      title: 'Delete this feeder survey?',
      message: 'Remove $label? Linked pending DTR work under this survey will also be removed. You can survey this feeder again.',
      confirmLabel: 'Delete',
      cancelLabel: 'Cancel',
    );
    if (!ok || !mounted) return;
    try {
      await api.delete('/my-feeder-surveys/$id');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Feeder survey deleted — you can survey again'),
          backgroundColor: SeasColors.ink950,
        ),
      );
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final list = _filtered;

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: SeasColors.white,
        elevation: 0,
        title: Text(
          'Feeder Survey Status',
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        actions: [
          IconButton(onPressed: loading ? null : _load, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : RefreshIndicator(
              color: SeasColors.volt,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                children: [
                  if (error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontSize: 13)),
                    ),
                  if (widget.bannerMessage != null && widget.bannerMessage!.trim().isNotEmpty) ...[
                    Container(
                      width: double.infinity,
                      margin: const EdgeInsets.only(bottom: 14),
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF7ED),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFFDBA74)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.upload_file_rounded, color: Color(0xFFEA580C), size: 20),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              widget.bannerMessage!,
                              style: GoogleFonts.plusJakartaSans(
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                                height: 1.35,
                                color: const Color(0xFF9A3412),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _Chip(
                        label: 'All (${_count('all')})',
                        selected: filter == 'all',
                        color: SeasColors.ink800,
                        onTap: () => setState(() => filter = 'all'),
                      ),
                      _Chip(
                        label: 'Pending DTR (${_count('pending_dtr')})',
                        selected: filter == 'pending_dtr',
                        color: SeasColors.warning,
                        onTap: () => setState(() => filter = 'pending_dtr'),
                      ),
                      _Chip(
                        label: 'SLD Pending (${_count('sld_pending')})',
                        selected: filter == 'sld_pending',
                        color: const Color(0xFFEA580C),
                        onTap: () => setState(() => filter = 'sld_pending'),
                      ),
                      _Chip(
                        label: 'Pending Approval (${_count('pending')})',
                        selected: filter == 'pending',
                        color: SeasColors.volt,
                        onTap: () => setState(() => filter = 'pending'),
                      ),
                      _Chip(
                        label: 'Rejected (${_count('rejected')})',
                        selected: filter == 'rejected',
                        color: SeasColors.ink400,
                        onTap: () => setState(() => filter = 'rejected'),
                      ),
                      _Chip(
                        label: 'Approved (${_count('approved')})',
                        selected: filter == 'approved',
                        color: SeasColors.success,
                        onTap: () => setState(() => filter = 'approved'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (list.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 48),
                      child: SeasEmptyState(
                        title: 'No feeder surveys',
                        subtitle: 'Submit a Feeder Survey, finish DTRs, then upload SLD for approval.',
                        icon: Icons.insights_outlined,
                      ),
                    )
                  else
                    ...list.map((s) {
                      final status = _statusOf(s);
                      final needsSld = _needsSld(s);
                      final label = _displayLabel(s);
                      final name = '${s['feeder_name'] ?? 'Feeder'}';
                      final code = '${s['feeder_code'] ?? ''}'.trim();
                      final ss = '${s['substation_name'] ?? ''}'.trim();
                      final expected = s['dtrs_expected'];
                      final completed = s['dtrs_completed'];
                      final progress = (expected != null || completed != null)
                          ? ' · DTRs ${completed ?? 0}/${expected ?? '?'}'
                          : '';
                      final canDelete = _canDelete(s);
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: SeasCard(
                          padding: const EdgeInsets.fromLTRB(16, 14, 8, 14),
                          onTap: () {
                            if (needsSld) {
                              _openSld(s);
                            } else if (status == 'draft') {
                              _openContinueDtr(s);
                            } else if (status == 'rejected') {
                              _openEdit(s);
                            }
                          },
                          child: Row(
                            children: [
                              SeasIconTile(
                                icon: needsSld
                                    ? Icons.upload_file_rounded
                                    : (status == 'draft' ? Icons.bolt_rounded : Icons.account_tree_outlined),
                                bg: needsSld
                                    ? SeasColors.warningSoft
                                    : (status == 'draft' ? const Color(0xFFFFF7ED) : SeasColors.voltSoft),
                                fg: needsSld
                                    ? SeasColors.warning
                                    : (status == 'draft' ? const Color(0xFFEA580C) : SeasColors.volt),
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
                                      needsSld
                                          ? (ss.isEmpty ? 'Upload SLD for approval' : '$ss · Upload SLD')
                                          : (ss.isEmpty ? '$label$progress' : '$ss · $label$progress'),
                                      style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              SeasBadge(
                                label,
                                tone: needsSld
                                    ? SeasBadgeTone.warning
                                    : badgeToneForStatus(status),
                              ),
                              if (canDelete)
                                IconButton(
                                  tooltip: 'Delete survey',
                                  visualDensity: VisualDensity.compact,
                                  padding: EdgeInsets.zero,
                                  constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                                  icon: const Icon(Icons.delete_outline_rounded, color: Color(0xFFB91C1C), size: 22),
                                  onPressed: () => _deleteSurvey(s),
                                )
                              else ...[
                                const SizedBox(width: 4),
                                const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400),
                              ],
                            ],
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.selected,
    required this.color,
    required this.onTap,
  });
  final String label;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? color.withValues(alpha: 0.14) : SeasColors.white,
      borderRadius: BorderRadius.circular(99),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(99),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(99),
            border: Border.all(color: selected ? color : SeasColors.ink200),
          ),
          child: Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              fontWeight: FontWeight.w700,
              fontSize: 11.5,
              color: selected ? color : SeasColors.ink800,
            ),
          ),
        ),
      ),
    );
  }
}

/// Upload SLD image for a feeder survey → manager approval queue.
class FeederSldUploadScreen extends StatefulWidget {
  const FeederSldUploadScreen({super.key, required this.survey});
  final Map<String, dynamic> survey;

  @override
  State<FeederSldUploadScreen> createState() => _FeederSldUploadScreenState();
}

class _FeederSldUploadScreenState extends State<FeederSldUploadScreen> {
  final _picker = ImagePicker();
  Uint8List? photoBytes;
  String? photoPath;
  bool saving = false;
  String? error;

  int? get surveyId => (widget.survey['id'] as num?)?.toInt();

  Future<void> _pick() async {
    final x = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 80, maxWidth: 2000);
    if (x == null) return;
    Uint8List? bytes;
    try {
      bytes = await x.readAsBytes();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      photoPath = x.path;
      photoBytes = bytes;
      error = null;
    });
  }

  Future<void> _capture() async {
    final x = await _picker.pickImage(source: ImageSource.camera, imageQuality: 80, maxWidth: 2000);
    if (x == null) return;
    Uint8List? bytes;
    try {
      bytes = await x.readAsBytes();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      photoPath = x.path;
      photoBytes = bytes;
      error = null;
    });
  }

  Future<void> _submit() async {
    final id = surveyId;
    if (id == null) return;
    if (photoBytes == null && photoPath == null) {
      setState(() => error = 'Select or capture an SLD image.');
      return;
    }
    final ok = await confirmSubmit(
      context,
      message: 'Are you sure you want to submit this SLD for approval?',
    );
    if (!ok || !mounted) return;
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final useBytes = kIsWeb || (photoPath != null && photoPath!.startsWith('blob:'));
      final res = await api.postMultipart(
        path: '/feeder-surveys/$id/sld',
        fields: const {},
        filePaths: useBytes || photoPath == null ? null : {'sld_photo': photoPath!},
        fileBytes: {
          if (photoBytes != null && (useBytes || photoPath == null)) 'sld_photo': photoBytes!,
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(res['message']?.toString() ?? 'SLD uploaded. Submitted for manager approval.'),
        backgroundColor: SeasColors.ink950,
      ));
      Navigator.of(context).pop(true);
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final name = '${widget.survey['feeder_name'] ?? 'Feeder'}';
    final code = '${widget.survey['feeder_code'] ?? ''}'.trim();
    final existing = ApiConfig.mediaUrl(widget.survey['sld_photo_url']?.toString() ?? widget.survey['sld_photo']?.toString());

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: Colors.white,
        title: Text('SLD Verification', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17)),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          SeasCard(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  code.isEmpty ? name : '$name · $code',
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16),
                ),
                const SizedBox(height: 6),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF7ED),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFFDBA74)),
                  ),
                  child: Text(
                    'SLD Verification — upload required',
                    style: GoogleFonts.plusJakartaSans(
                      fontWeight: FontWeight.w800,
                      fontSize: 12.5,
                      color: const Color(0xFFEA580C),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Upload the Single Line Diagram (SLD) for this feeder after Finish DTR. This submits the Feeder→DTR survey for manager approval. Consumer surveys continue after DTR approval as today.',
                  style: TextStyle(color: SeasColors.ink400, fontSize: 13, height: 1.35),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (((widget.survey['sld_photos'] as List?) ?? []).isNotEmpty) ...[
            Text('Last uploads (kept max 3)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14)),
            const SizedBox(height: 8),
            SizedBox(
              height: 88,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: ((widget.survey['sld_photos'] as List?) ?? []).take(3).length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (_, i) {
                  final photos = (widget.survey['sld_photos'] as List?) ?? [];
                  final p = photos[i] as Map;
                  final url = ApiConfig.mediaUrl('${p['url'] ?? p['path']}');
                  return Container(
                    width: 88,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: i == 0 ? SeasColors.volt : SeasColors.ink100, width: i == 0 ? 2 : 1),
                      color: SeasColors.canvasSoft,
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: url == null
                        ? null
                        : Image.network(
                            url,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined, color: SeasColors.ink400),
                          ),
                  );
                },
              ),
            ),
            const SizedBox(height: 14),
          ],
          if (error != null)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(12)),
              child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
            ),
          InkWell(
            onTap: _pick,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              height: 220,
              width: double.infinity,
              decoration: BoxDecoration(
                color: SeasColors.canvasSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: SeasColors.ink100),
                image: photoBytes != null
                    ? DecorationImage(image: MemoryImage(photoBytes!), fit: BoxFit.cover)
                    : (existing != null
                        ? DecorationImage(image: NetworkImage(existing), fit: BoxFit.cover)
                        : null),
              ),
              alignment: Alignment.center,
              child: photoBytes == null && existing == null
                  ? Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.image_outlined, color: SeasColors.volt, size: 36),
                        const SizedBox(height: 8),
                        Text('Tap to choose SLD image', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700)),
                      ],
                    )
                  : null,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: saving ? null : _capture,
                  icon: const Icon(Icons.photo_camera_outlined),
                  label: const Text('Camera'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: SeasColors.ink950,
                    side: const BorderSide(color: SeasColors.ink200),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: saving ? null : _pick,
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('Gallery'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: SeasColors.ink950,
                    side: const BorderSide(color: SeasColors.ink200),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: saving ? null : _submit,
              style: FilledButton.styleFrom(
                backgroundColor: SeasColors.volt,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: Text(
                saving ? 'Submitting…' : 'Submit SLD for Approval',
                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
