import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../core/api_config.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';

/// Manager / Project Manager: assign date-wise work + zone scopes.
class ManagerHubTab extends StatefulWidget {
  const ManagerHubTab({super.key});

  @override
  State<ManagerHubTab> createState() => _ManagerHubTabState();
}

class _ManagerHubTabState extends State<ManagerHubTab> with SingleTickerProviderStateMixin {
  late final TabController tabs;
  List assignments = [];
  List executives = [];
  List feeders = [];
  List feederSurveys = [];
  List assignableZones = [];
  List workAssignments = [];
  Map reviewStats = {};
  Map surveyOptions = {};
  bool loading = true;
  String? error;

  // Zone Assign workspace state
  int? selectedZoneId;
  List zoneFeeders = [];
  int zoneFeederCount = 0;
  bool zoneFeedersLoading = false;
  String zoneFeederQuery = '';
  final Set<int> selectedFeederIds = {};
  int? assignFeId;

  @override
  void initState() {
    super.initState();
    tabs = TabController(length: 3, vsync: this);
    _load();
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final a = await api.get('/assignments');
      final fe = await api.get('/field-executives');
      final bundle = await api.get('/hierarchy/bundle');
      final fs = await api.get('/feeder-surveys?per_page=100');
      final zones = await api.get('/hierarchy/assignable-zones');
      final wa = await api.get('/work-assignments?per_page=100');
      try {
        surveyOptions = await api.get('/meta/survey-options');
      } catch (_) {
        surveyOptions = {};
      }
      assignments = (a['data'] as List?) ?? [];
      workAssignments = (wa['data'] as List?) ?? assignments;
      executives = (fe['data'] as List?) ?? [];
      feederSurveys = (fs['data'] as List?) ?? [];
      assignableZones = (zones['data'] as List?) ?? [];
      reviewStats = Map<String, dynamic>.from((fs['review_stats'] as Map?) ?? {});
      feeders = [];
      for (final r in ((bundle['regions'] as List?) ?? [])) {
        for (final c in ((r['circles'] as List?) ?? [])) {
          for (final d in ((c['divisions'] as List?) ?? [])) {
            for (final z in ((d['zones'] as List?) ?? [])) {
              for (final s in ((z['substations'] as List?) ?? [])) {
                for (final f in ((s['feeders'] as List?) ?? [])) {
                  feeders.add(Map<String, dynamic>.from(f as Map));
                }
              }
            }
          }
        }
      }
      if (selectedZoneId != null) {
        await _loadZoneFeeders(selectedZoneId!, silent: true);
      }
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String _fmtDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _prettyDate(dynamic raw) {
    if (raw == null) return '—';
    final s = raw.toString();
    if (s.length >= 10) {
      final p = s.substring(0, 10).split('-');
      if (p.length == 3) return '${p[2]}/${p[1]}/${p[0]}';
    }
    return s;
  }

  Future<void> _loadZoneFeeders(int zoneId, {bool silent = false}) async {
    if (!silent && mounted) {
      setState(() {
        zoneFeedersLoading = true;
        selectedZoneId = zoneId;
        selectedFeederIds.clear();
        zoneFeederQuery = '';
      });
    }
    try {
      final res = await api.get('/zones/$zoneId/feeders');
      zoneFeeders = (res['feeders'] as List?) ?? [];
      zoneFeederCount = (res['count'] as num?)?.toInt() ?? zoneFeeders.length;
    } catch (e) {
      zoneFeeders = [];
      zoneFeederCount = 0;
      if (mounted && !silent) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => zoneFeedersLoading = false);
    }
  }

  Future<void> _assignSelectedFeeders() async {
    if (selectedZoneId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select a zone first')));
      return;
    }
    if (selectedFeederIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select at least one feeder')));
      return;
    }
    if (assignFeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select a Field Executive')));
      return;
    }
    try {
      await api.post('/work-assignments', {
        'zone_id': selectedZoneId,
        'assigned_to': assignFeId,
        'feeder_ids': selectedFeederIds.toList(),
      });
      selectedFeederIds.clear();
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Assigned feeders to FE'), backgroundColor: SeasColors.ink950),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _reassignAssignment(Map a) async {
    int? newFeId = assignFeId;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setModal) {
          return Container(
            decoration: const BoxDecoration(
              color: SeasColors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                const SizedBox(height: 14),
                Text('Reassign feeder', style: GoogleFonts.plusJakartaSans(fontSize: 20, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text(
                  '${a['feeder']?['code'] ?? ''} · ${a['feeder']?['name'] ?? 'Feeder'}\nCurrently: ${a['assignee']?['name'] ?? 'FE'}',
                  style: const TextStyle(color: SeasColors.ink400, fontSize: 13),
                ),
                const SizedBox(height: 14),
                SeasSelectField(
                  label: 'New Field Executive',
                  hint: 'Select FE',
                  value: newFeId,
                  options: executives
                      .map((e) => SeasSelectOption(value: e['id'], label: '${e['name']}', subtitle: '${e['email']}'))
                      .toList(),
                  onSelected: (o) => setModal(() => newFeId = o.value as int?),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => Navigator.pop(ctx, true),
                  style: FilledButton.styleFrom(backgroundColor: SeasColors.volt, padding: const EdgeInsets.symmetric(vertical: 14)),
                  child: const Text('REASSIGN'),
                ),
              ],
            ),
          );
        });
      },
    );
    if (ok != true || newFeId == null) return;
    final id = (a['id'] as num?)?.toInt();
    if (id == null) return;
    try {
      await api.post('/work-assignments/$id/reassign', {'assigned_to': newFeId});
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Reassigned'), backgroundColor: SeasColors.ink950));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _unassignAssignment(Map a) async {
    final id = (a['id'] as num?)?.toInt();
    if (id == null) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Unassign feeder?'),
        content: Text('${a['feeder']?['code'] ?? ''} will be removed from ${a['assignee']?['name'] ?? 'FE'}.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            child: const Text('Unassign'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await api.delete('/work-assignments/$id');
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Unassigned'), backgroundColor: SeasColors.ink950));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _completeAssignment(Map a) async {
    final id = (a['id'] as num?)?.toInt();
    if (id == null) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Mark assignment complete?'),
        content: Text(
          '${a['feeder']?['code'] ?? 'Feeder'} will leave ${a['assignee']?['name'] ?? 'FE'}\'s active work. '
          'Use this if work is done or you want to free the assignment without SLD.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950),
            child: const Text('Complete'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await api.delete('/work-assignments/$id?complete=1');
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Assignment completed'), backgroundColor: SeasColors.ink950));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _assignZones() async {
    // Jump to Zone Assign tab and keep legacy entry for app-bar map icon.
    tabs.animateTo(1);
  }

  Future<void> _assign() async {
    int? feId;
    int? feederId;
    DateTime workDate = DateTime.now();
    final notes = TextEditingController();

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setModal) {
          return Padding(
            padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
            child: Container(
              decoration: const BoxDecoration(
                color: SeasColors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                  const SizedBox(height: 14),
                  Text('Assign Work', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  const Text('Pick FE, feeder and work date. After that date the task shows Closed.', style: TextStyle(color: SeasColors.ink400, fontSize: 13)),
                  const SizedBox(height: 14),
                  SeasSelectField(
                    label: 'Field Executive',
                    hint: 'Select FE',
                    value: feId,
                    options: executives
                        .map((e) => SeasSelectOption(value: e['id'], label: '${e['name']}', subtitle: '${e['email']}'))
                        .toList(),
                    onSelected: (o) => setModal(() => feId = o.value as int?),
                  ),
                  const SizedBox(height: 12),
                  SeasSelectField(
                    label: 'Feeder',
                    hint: 'Select feeder',
                    value: feederId,
                    options: feeders
                        .map((e) => SeasSelectOption(value: e['id'], label: '${e['code']} · ${e['name']}'))
                        .toList(),
                    onSelected: (o) => setModal(() => feederId = o.value as int?),
                  ),
                  const SizedBox(height: 12),
                  InkWell(
                    onTap: () async {
                      final picked = await showDatePicker(
                        context: ctx,
                        initialDate: workDate,
                        firstDate: DateTime.now().subtract(const Duration(days: 1)),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                      );
                      if (picked != null) setModal(() => workDate = picked);
                    },
                    borderRadius: BorderRadius.circular(16),
                    child: InputDecorator(
                      decoration: const InputDecoration(
                        labelText: 'Work date',
                        border: OutlineInputBorder(),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.event_rounded, size: 20, color: SeasColors.ink400),
                          const SizedBox(width: 10),
                          Text(_prettyDate(_fmtDate(workDate)), style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700)),
                          const Spacer(),
                          const Text('Change', style: TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SeasTextField(label: 'Notes', controller: notes, hint: 'Optional instructions', maxLines: 3),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    style: FilledButton.styleFrom(backgroundColor: SeasColors.volt, padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('ASSIGN'),
                  ),
                ],
              ),
            ),
          );
        });
      },
    );

    if (ok != true) return;
    if (feId == null || feederId == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select Field Executive and Feeder')));
      return;
    }
    try {
      await api.post('/assignments', {
        'assigned_to': feId,
        'feeder_id': feederId,
        'work_date': _fmtDate(workDate),
        'notes': notes.text.trim(),
      });
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Work assigned'), backgroundColor: SeasColors.ink950));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  String? _photoUrl(dynamic pathOrUrl) {
    if (pathOrUrl == null) return null;
    final s = pathOrUrl.toString().trim();
    if (s.isEmpty) return null;
    return ApiConfig.mediaUrl(s);
  }

  List<String> _optList(String key, List<String> fallback) {
    final raw = surveyOptions[key];
    if (raw is List && raw.isNotEmpty) {
      return raw.map((e) => e.toString()).toList();
    }
    return fallback;
  }

  Future<void> _reviewFeeder(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null) return;

    Map detail = Map<String, dynamic>.from(s);
    List dtrSurveys = [];
    Map reviewCounts = {};
    bool canApprove = '${s['status'] ?? ''}' == 'pending_approval';
    bool canUnlock = false;
    bool canEdit = true;
    bool canDelete = true;
    String? loadError;

    try {
      final res = await api.get('/feeder-surveys/$id');
      if (res['survey'] is Map) {
        detail = Map<String, dynamic>.from(res['survey'] as Map);
      }
      dtrSurveys = (res['dtr_surveys'] as List?) ?? [];
      reviewCounts = Map<String, dynamic>.from((res['review_counts'] as Map?) ?? {});
      canApprove = res['can_approve'] == true;
      canUnlock = res['can_unlock'] == true;
      canEdit = res['can_edit'] != false;
      canDelete = res['can_delete'] != false;
    } catch (e) {
      loadError = e.toString().replaceFirst('Exception: ', '');
      canApprove = '${detail['status'] ?? ''}' == 'pending_approval';
      canUnlock = false;
    }

    if (!mounted) return;
    final remarks = TextEditingController();
    String? remarkError;
    final status = '${detail['status'] ?? ''}';
    final sldPhotos = (detail['sld_photos'] as List?) ?? [];
    final locked = detail['is_locked'] == true || (detail['locked_at'] ?? '').toString().isNotEmpty;
    final meterUrl = _photoUrl(detail['new_meter_photo_url'] ?? detail['new_meter_photo']);
    final sldCurrentUrl = _photoUrl(detail['sld_photo_url'] ?? detail['sld_photo']);

    String fmtTs(dynamic raw) {
      if (raw == null) return '—';
      final s = raw.toString();
      if (s.length >= 16) return '${_prettyDate(s)} ${s.substring(11, 16)}';
      return _prettyDate(s);
    }

    String? sldUrl(Map p) {
      final fromApi = p['url']?.toString();
      if (fromApi != null && fromApi.isNotEmpty) return _photoUrl(fromApi);
      return _photoUrl(p['path']?.toString());
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setSheet) {
          return DraggableScrollableSheet(
            initialChildSize: 0.9,
            minChildSize: 0.5,
            maxChildSize: 0.98,
            expand: false,
            builder: (ctx, scrollCtrl) {
              return Container(
                decoration: const BoxDecoration(
                  color: SeasColors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: ListView(
                  controller: scrollCtrl,
                  padding: EdgeInsets.fromLTRB(20, 12, 20, 24 + MediaQuery.viewInsetsOf(ctx).bottom),
                  children: [
                    Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                    const SizedBox(height: 14),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text('${detail['feeder_name'] ?? 'Feeder'}', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800)),
                        ),
                        if (locked)
                          Container(
                            margin: const EdgeInsets.only(left: 8, top: 4),
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(color: SeasColors.ink950, borderRadius: BorderRadius.circular(99)),
                            child: Text('Locked', style: GoogleFonts.plusJakartaSans(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${detail['display_status'] ?? status} · ${detail['feeder_code'] ?? ''}',
                      style: const TextStyle(color: SeasColors.ink400, fontSize: 13),
                    ),
                    if (loadError != null) ...[
                      const SizedBox(height: 10),
                      Text(loadError!, style: const TextStyle(color: SeasColors.volt, fontSize: 12)),
                    ],
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _countChip('SLD pending', '${reviewCounts['sld_pending'] ?? (status == 'sld_pending' ? 1 : 0)}', const Color(0xFFEA580C)),
                        _countChip('Approved', '${reviewCounts['dtr_approved'] ?? reviewCounts['feeder_approved'] ?? 0}', SeasColors.success),
                        _countChip('Awaiting review', status == 'pending_approval' ? '1' : '0', SeasColors.volt),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _reviewMetaRow('Surveyor', '${detail['surveyor']?['name'] ?? '—'}'),
                    _reviewMetaRow('Substation', '${detail['substation_name'] ?? detail['substation']?['name'] ?? '—'}'),
                    _reviewMetaRow('Substation code', '${detail['substation_code'] ?? '—'}'),
                    _reviewMetaRow('Feeder code', '${detail['feeder_code'] ?? '—'}'),
                    _reviewMetaRow('DTR progress', '${detail['dtrs_completed'] ?? 0} / ${detail['dtrs_expected'] ?? '?'}'),
                    _reviewMetaRow('Surveyed', fmtTs(detail['surveyed_at'])),
                    _reviewMetaRow('Updated', fmtTs(detail['updated_at'])),
                    if ((detail['reviewed_at'] ?? '').toString().isNotEmpty) _reviewMetaRow('Reviewed', fmtTs(detail['reviewed_at'])),
                    if ((detail['locked_at'] ?? '').toString().isNotEmpty) _reviewMetaRow('Locked at', fmtTs(detail['locked_at'])),
                    if ((detail['review_remarks'] ?? '').toString().isNotEmpty) _reviewMetaRow('Review remarks', '${detail['review_remarks']}'),
                    const SizedBox(height: 16),
                    Text('Hierarchy', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    _reviewMetaRow('Region', '${detail['region']?['name'] ?? '—'}'),
                    _reviewMetaRow('Circle', '${detail['circle']?['name'] ?? '—'}'),
                    _reviewMetaRow('Division', '${detail['division']?['name'] ?? '—'}'),
                    _reviewMetaRow('Zone', '${detail['zone']?['name'] ?? '—'}'),
                    const SizedBox(height: 16),
                    Text('Field data', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    _reviewMetaRow('Voltage', '${detail['feeder_voltage'] ?? '—'}'),
                    _reviewMetaRow('Metering', '${detail['metering_type'] ?? '—'}'),
                    _reviewMetaRow('CTPT available', '${detail['ctpt_available'] ?? '—'}'),
                    _reviewMetaRow('ME PT ratio', '${detail['me_pt_ratio'] ?? '—'}'),
                    _reviewMetaRow('ME CT ratio', '${detail['me_ct_ratio'] ?? '—'}'),
                    _reviewMetaRow('New MF', '${detail['new_mf'] ?? '—'}'),
                    _reviewMetaRow('ME installed', '${detail['me_installed'] ?? '—'}'),
                    _reviewMetaRow('ME working', '${detail['me_working'] ?? '—'}'),
                    _reviewMetaRow('Smart meter installed', '${detail['new_smart_meter_installed'] ?? '—'}'),
                    _reviewMetaRow('New meter no.', '${detail['new_meter_number'] ?? '—'}'),
                    _reviewMetaRow('Old meter no.', '${detail['old_meter_number'] ?? '—'}'),
                    _reviewMetaRow('Old meter make', '${detail['old_meter_make'] ?? '—'}'),
                    _reviewMetaRow('Old meter condition', '${detail['old_meter_condition'] ?? '—'}'),
                    _reviewMetaRow('GPS', '${detail['latitude'] ?? '—'}, ${detail['longitude'] ?? '—'}'),
                    _reviewMetaRow('GPS accuracy', '${detail['gps_accuracy'] ?? '—'}'),
                    _reviewMetaRow('Remarks', '${detail['remarks'] ?? '—'}'),
                    const SizedBox(height: 16),
                    Text('Photos', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 148,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          if (meterUrl != null) ...[
                            _sldThumb(meterUrl, 'Meter photo', true, onTap: () => _previewImage(meterUrl)),
                            const SizedBox(width: 10),
                          ],
                          if (sldPhotos.isEmpty && sldCurrentUrl != null)
                            _sldThumb(sldCurrentUrl, 'SLD', true, onTap: () => _previewImage(sldCurrentUrl)),
                          ...List.generate(sldPhotos.length.clamp(0, 3), (i) {
                            final p = sldPhotos[i] as Map;
                            final url = sldUrl(p);
                            return Padding(
                              padding: EdgeInsets.only(left: i == 0 && meterUrl == null ? 0 : 10),
                              child: _sldThumb(
                                url,
                                i == 0 ? 'SLD latest · ${fmtTs(p['created_at'])}' : 'SLD · ${fmtTs(p['created_at'])}',
                                i == 0,
                                onTap: () => _previewImage(url),
                              ),
                            );
                          }),
                          if (meterUrl == null && sldPhotos.isEmpty && sldCurrentUrl == null)
                            const Padding(
                              padding: EdgeInsets.only(top: 48),
                              child: Text('No photos uploaded.', style: TextStyle(color: SeasColors.ink400, fontSize: 13)),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text('Related DTR surveys', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    if (dtrSurveys.isEmpty)
                      const Text('No DTR surveys for this feeder yet.', style: TextStyle(color: SeasColors.ink400, fontSize: 13))
                    else
                      ...dtrSurveys.take(12).map((raw) {
                        final d = raw as Map;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('${d['dtr_name'] ?? 'DTR'}', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13)),
                                    Text('${d['dtr_code'] ?? ''}', style: const TextStyle(color: SeasColors.ink400, fontSize: 11)),
                                  ],
                                ),
                              ),
                              SeasBadge('${d['status'] ?? ''}', tone: badgeToneForStatus('${d['status'] ?? ''}')),
                            ],
                          ),
                        );
                      }),
                    if (canUnlock) ...[
                      const SizedBox(height: 14),
                      OutlinedButton.icon(
                        onPressed: () async {
                          try {
                            await api.post('/feeder-surveys/$id/unlock', {});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Survey unlocked'), backgroundColor: SeasColors.ink950),
                            );
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        icon: const Icon(Icons.lock_open_rounded),
                        label: const Text('Unlock'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: SeasColors.ink950,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          side: const BorderSide(color: SeasColors.ink200),
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        if (canEdit)
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                if (ctx.mounted) Navigator.pop(ctx);
                                await _editFeederSurvey(id, detail);
                              },
                              icon: const Icon(Icons.edit_rounded, size: 18),
                              label: const Text('Edit'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: SeasColors.volt,
                                side: const BorderSide(color: SeasColors.volt),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                              ),
                            ),
                          ),
                        if (canEdit && canDelete) const SizedBox(width: 8),
                        if (canDelete)
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                final ok = await showDialog<bool>(
                                  context: ctx,
                                  builder: (dctx) => AlertDialog(
                                    title: const Text('Delete feeder survey?'),
                                    content: Text('Permanently delete ${detail['feeder_name'] ?? 'this survey'}? This cannot be undone.'),
                                    actions: [
                                      TextButton(onPressed: () => Navigator.pop(dctx, false), child: const Text('Cancel')),
                                      FilledButton(
                                        onPressed: () => Navigator.pop(dctx, true),
                                        style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                                        child: const Text('Delete'),
                                      ),
                                    ],
                                  ),
                                );
                                if (ok != true) return;
                                try {
                                  await api.delete('/feeder-surveys/$id');
                                  if (ctx.mounted) Navigator.pop(ctx);
                                  await _load();
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('Feeder survey deleted'), backgroundColor: SeasColors.ink950),
                                  );
                                } catch (e) {
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                                }
                              },
                              icon: const Icon(Icons.delete_outline_rounded, size: 18),
                              label: const Text('Delete'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: SeasColors.ink950,
                                side: const BorderSide(color: SeasColors.ink200),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                              ),
                            ),
                          ),
                      ],
                    ),
                    if (canApprove && status == 'pending_approval') ...[
                      const SizedBox(height: 18),
                      Text('Decision', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                      const SizedBox(height: 8),
                      SeasTextField(
                        label: 'Remarks',
                        controller: remarks,
                        hint: 'Optional for approve · required for reject',
                        maxLines: 3,
                      ),
                      if (remarkError != null) ...[
                        const SizedBox(height: 6),
                        Text(remarkError!, style: const TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w700, fontSize: 12.5)),
                      ],
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: () async {
                          try {
                            await api.post('/feeder-surveys/$id/approve', {'review_remarks': remarks.text.trim()});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.success, padding: const EdgeInsets.symmetric(vertical: 14)),
                        child: const Text('Approve'),
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: () async {
                          if (remarks.text.trim().isEmpty) {
                            setSheet(() => remarkError = 'Rejection remarks are mandatory. Enter a reason before rejecting.');
                            return;
                          }
                          setSheet(() => remarkError = null);
                          try {
                            await api.post('/feeder-surveys/$id/reject', {'review_remarks': remarks.text.trim()});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950, padding: const EdgeInsets.symmetric(vertical: 14)),
                        child: const Text('Reject'),
                      ),
                    ] else ...[
                      if (status == 'draft' || status == 'sld_pending') ...[
                        const SizedBox(height: 14),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF7ED),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFFDBA74)),
                          ),
                          child: Text(
                            status == 'draft'
                                ? 'Approve/Reject appears after the surveyor finishes DTRs and uploads the SLD (status becomes Pending Approval). Current: Pending DTR Survey.'
                                : 'Approve/Reject appears after the surveyor uploads the SLD photo. Current: SLD Verification Pending.',
                            style: GoogleFonts.plusJakartaSans(fontSize: 12.5, fontWeight: FontWeight.w600, color: const Color(0xFF9A3412), height: 1.35),
                          ),
                        ),
                      ],
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
                    ],
                  ],
                ),
              );
            },
          );
        });
      },
    );
  }

  Future<void> _editFeederSurvey(int id, Map detail) async {
    String? voltage = detail['feeder_voltage']?.toString();
    String? meteringFromVoltage(String? v) {
      final n = (v ?? '').replaceAll(' ', '').toUpperCase();
      if (n == '11KV') return 'Output Feeder';
      if (n == '33KV') return 'Input Feeder';
      return null;
    }

    String? metering = meteringFromVoltage(voltage) ?? detail['metering_type']?.toString();
    if (metering == 'Output') metering = 'Output Feeder';
    if (metering == 'Input') metering = 'Input Feeder';
    String? ctpt = detail['ctpt_available']?.toString();
    String? meCt = detail['me_ct_ratio']?.toString();
    String? meInstalled = detail['me_installed']?.toString();
    String? meWorking = detail['me_working']?.toString();
    String? smart = detail['new_smart_meter_installed']?.toString();
    String? oldMake = detail['old_meter_make']?.toString();
    String? oldCond = detail['old_meter_condition']?.toString();
    final newMeter = TextEditingController(text: '${detail['new_meter_number'] ?? ''}');
    final oldMeter = TextEditingController(text: '${detail['old_meter_number'] ?? ''}');
    final remarksCtrl = TextEditingController(text: '${detail['remarks'] ?? ''}');
    final lat = TextEditingController(text: '${detail['latitude'] ?? ''}');
    final lng = TextEditingController(text: '${detail['longitude'] ?? ''}');

    final voltages = _optList('feeder_voltages', ['11 KV', '33 KV']);
    final yesNo = _optList('yes_no', ['Yes', 'No']);
    final ctRatios = _optList('ct_ratios', ['100/5', '150/5', '200/5', '300/5']);
    final smartOpts = _optList('smart_meter_installed', ['Yes', 'No', 'Meter Not Available']);
    final oldMakes = _optList('feeder_old_makes', ['L&T Schneider', 'Secure', 'HPL', 'Visiontek', 'Other']);
    final oldConditions = _optList('old_meter_conditions', ['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed']);

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setModal) {
          return Padding(
            padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
            child: Container(
              constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(ctx).height * 0.92),
              decoration: const BoxDecoration(
                color: SeasColors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
              child: ListView(
                children: [
                  Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                  const SizedBox(height: 14),
                  Text('Edit feeder survey', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  const Text('Correct mismatched fields, then save.', style: TextStyle(color: SeasColors.ink400, fontSize: 13)),
                  const SizedBox(height: 14),
                  SeasSelectField(
                    label: 'Feeder voltage',
                    hint: 'Select',
                    value: voltage,
                    options: voltages.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() {
                      voltage = o.value as String?;
                      metering = meteringFromVoltage(voltage);
                    }),
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
                        Text('Metering type (Auto)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12, color: SeasColors.ink400)),
                        const SizedBox(height: 4),
                        Text(
                          metering ?? 'Select feeder voltage above',
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 16,
                            color: metering == null ? SeasColors.ink400 : SeasColors.volt,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'CTPT available',
                    hint: 'Yes / No',
                    value: ctpt,
                    options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => ctpt = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'ME CT ratio',
                    hint: 'Select',
                    value: meCt,
                    options: ctRatios.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => meCt = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'ME installed',
                    hint: 'Yes / No',
                    value: meInstalled,
                    options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => meInstalled = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'ME working',
                    hint: 'Yes / No',
                    value: meWorking,
                    options: yesNo.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => meWorking = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'Smart meter installed',
                    hint: 'Select',
                    value: smart,
                    options: smartOpts.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => smart = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'New meter number', controller: newMeter),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Old meter number', controller: oldMeter),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'Old meter make',
                    hint: 'Select',
                    value: oldMake,
                    options: oldMakes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => oldMake = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'Old meter condition',
                    hint: 'Select',
                    value: oldCond,
                    options: oldConditions.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => oldCond = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Latitude', controller: lat),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Longitude', controller: lng),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Remarks', controller: remarksCtrl, maxLines: 3),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    style: FilledButton.styleFrom(backgroundColor: SeasColors.volt, padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('Save corrections'),
                  ),
                ],
              ),
            ),
          );
        });
      },
    );

    if (ok != true) return;
    try {
      await api.put('/feeder-surveys/$id', {
        if (voltage != null) 'feeder_voltage': voltage,
        if (metering != null) 'metering_type': metering,
        if (ctpt != null) 'ctpt_available': ctpt,
        if (meCt != null) 'me_ct_ratio': meCt,
        if (meInstalled != null) 'me_installed': meInstalled,
        if (meWorking != null) 'me_working': meWorking,
        if (smart != null) 'new_smart_meter_installed': smart,
        'new_meter_number': newMeter.text.trim(),
        'old_meter_number': oldMeter.text.trim(),
        if (oldMake != null) 'old_meter_make': oldMake,
        if (oldCond != null) 'old_meter_condition': oldCond,
        'remarks': remarksCtrl.text.trim(),
        if (lat.text.trim().isNotEmpty) 'latitude': double.tryParse(lat.text.trim()),
        if (lng.text.trim().isNotEmpty) 'longitude': double.tryParse(lng.text.trim()),
      });
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Feeder survey updated'), backgroundColor: SeasColors.ink950),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _reviewDtr(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null) return;

    Map detail = Map<String, dynamic>.from(s);
    bool canApprove = true;
    bool canUnlock = false;
    bool canEdit = true;
    bool canDelete = true;
    String? loadError;

    try {
      final res = await api.get('/surveys/$id');
      if (res['survey'] is Map) {
        detail = Map<String, dynamic>.from(res['survey'] as Map);
      }
      canApprove = res['can_approve'] == true;
      canUnlock = res['can_unlock'] == true;
      canEdit = res['can_edit'] != false;
      canDelete = res['can_delete'] != false;
    } catch (e) {
      loadError = e.toString().replaceFirst('Exception: ', '');
      canUnlock = false;
    }

    if (!mounted) return;
    final remarks = TextEditingController();
    String? remarkError;
    final status = '${detail['status'] ?? ''}';
    final locked = detail['is_locked'] == true || (detail['locked_at'] ?? '').toString().isNotEmpty;
    final overallUrl = _photoUrl(detail['dtr_overall_photo_url'] ?? detail['dtr_overall_photo']);
    final meterUrl = _photoUrl(detail['smart_meter_photo_url'] ?? detail['smart_meter_photo']);
    final ctUrl = _photoUrl(detail['ct_ratio_photo_url'] ?? detail['ct_ratio_photo']);

    String fmtTs(dynamic raw) {
      if (raw == null) return '—';
      final s = raw.toString();
      if (s.length >= 16) return '${_prettyDate(s)} ${s.substring(11, 16)}';
      return _prettyDate(s);
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setSheet) {
          return DraggableScrollableSheet(
            initialChildSize: 0.9,
            minChildSize: 0.5,
            maxChildSize: 0.98,
            expand: false,
            builder: (ctx, scrollCtrl) {
              return Container(
                decoration: const BoxDecoration(
                  color: SeasColors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: ListView(
                  controller: scrollCtrl,
                  padding: EdgeInsets.fromLTRB(20, 12, 20, 24 + MediaQuery.viewInsetsOf(ctx).bottom),
                  children: [
                    Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                    const SizedBox(height: 14),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text('${detail['dtr_name'] ?? 'DTR'}', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800)),
                        ),
                        if (locked)
                          Container(
                            margin: const EdgeInsets.only(left: 8, top: 4),
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(color: SeasColors.ink950, borderRadius: BorderRadius.circular(99)),
                            child: Text('Locked', style: GoogleFonts.plusJakartaSans(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${detail['dtr_code'] ?? ''} · ${detail['feeder_name'] ?? ''}',
                      style: const TextStyle(color: SeasColors.ink400, fontSize: 13),
                    ),
                    if (loadError != null) ...[
                      const SizedBox(height: 10),
                      Text(loadError!, style: const TextStyle(color: SeasColors.volt, fontSize: 12)),
                    ],
                    const SizedBox(height: 14),
                    _reviewMetaRow('Surveyor', '${detail['surveyor']?['name'] ?? '—'}'),
                    _reviewMetaRow('Status', status),
                    _reviewMetaRow('Entry source', '${detail['entry_source'] ?? '—'}'),
                    _reviewMetaRow('Surveyed', fmtTs(detail['surveyed_at'])),
                    if ((detail['reviewed_at'] ?? '').toString().isNotEmpty) _reviewMetaRow('Reviewed', fmtTs(detail['reviewed_at'])),
                    if ((detail['locked_at'] ?? '').toString().isNotEmpty) _reviewMetaRow('Locked at', fmtTs(detail['locked_at'])),
                    if ((detail['review_remarks'] ?? '').toString().isNotEmpty) _reviewMetaRow('Review remarks', '${detail['review_remarks']}'),
                    const SizedBox(height: 16),
                    Text('Hierarchy', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    _reviewMetaRow('Region', '${detail['region']?['name'] ?? '—'}'),
                    _reviewMetaRow('Circle', '${detail['circle']?['name'] ?? '—'}'),
                    _reviewMetaRow('Division', '${detail['division']?['name'] ?? '—'}'),
                    _reviewMetaRow('Zone', '${detail['zone']?['name'] ?? '—'}'),
                    _reviewMetaRow('Substation', '${detail['substation']?['name'] ?? '—'}'),
                    _reviewMetaRow('Feeder', '${detail['feeder_name'] ?? detail['feeder']?['name'] ?? '—'}'),
                    const SizedBox(height: 16),
                    Text('Field data', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    _reviewMetaRow('Capacity', '${detail['dtr_capacity_kva'] ?? '—'} kVA'),
                    _reviewMetaRow('Condition', '${detail['dtr_condition'] ?? '—'}'),
                    _reviewMetaRow('LT Line Type', '${detail['lt_line_type'] ?? '—'}'),
                    _reviewMetaRow('Smart meter status', '${detail['smart_meter_status'] ?? '—'}'),
                    _reviewMetaRow('Old meter condition', '${detail['old_meter_condition'] ?? '—'}'),
                    _reviewMetaRow('Old MSN', '${detail['old_msn'] ?? '—'}'),
                    _reviewMetaRow('Old meter make', '${detail['old_meter_make'] ?? '—'}'),
                    _reviewMetaRow('New MSN', '${detail['new_msn'] ?? '—'}'),
                    _reviewMetaRow('New meter make', '${detail['new_meter_make'] ?? '—'}'),
                    _reviewMetaRow('New CT ratio', '${detail['new_meter_ct_ratio'] ?? '—'}'),
                    _reviewMetaRow('New MF', '${detail['new_meter_mf'] ?? '—'}'),
                    _reviewMetaRow('GPS', '${detail['latitude'] ?? '—'}, ${detail['longitude'] ?? '—'}'),
                    _reviewMetaRow('GPS accuracy', '${detail['gps_accuracy'] ?? '—'}'),
                    _reviewMetaRow('Observation', '${detail['observation'] ?? '—'}'),
                    const SizedBox(height: 16),
                    Text('Photos', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 148,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          if (overallUrl != null) ...[
                            _sldThumb(overallUrl, 'DTR overall', true, onTap: () => _previewImage(overallUrl)),
                            const SizedBox(width: 10),
                          ],
                          if (meterUrl != null) ...[
                            _sldThumb(meterUrl, 'Smart meter', true, onTap: () => _previewImage(meterUrl)),
                            const SizedBox(width: 10),
                          ],
                          if (ctUrl != null)
                            _sldThumb(ctUrl, 'CT ratio', false, onTap: () => _previewImage(ctUrl)),
                          if (overallUrl == null && meterUrl == null && ctUrl == null)
                            const Padding(
                              padding: EdgeInsets.only(top: 48),
                              child: Text('No photos uploaded.', style: TextStyle(color: SeasColors.ink400, fontSize: 13)),
                            ),
                        ],
                      ),
                    ),
                    if (canUnlock) ...[
                      const SizedBox(height: 14),
                      OutlinedButton.icon(
                        onPressed: () async {
                          try {
                            await api.post('/surveys/$id/unlock', {});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('DTR survey unlocked'), backgroundColor: SeasColors.ink950),
                            );
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        icon: const Icon(Icons.lock_open_rounded),
                        label: const Text('Unlock'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: SeasColors.ink950,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          side: const BorderSide(color: SeasColors.ink200),
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        if (canEdit)
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                if (ctx.mounted) Navigator.pop(ctx);
                                await _editDtrSurvey(id, detail);
                              },
                              icon: const Icon(Icons.edit_rounded, size: 18),
                              label: const Text('Edit'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: SeasColors.volt,
                                side: const BorderSide(color: SeasColors.volt),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                              ),
                            ),
                          ),
                        if (canEdit && canDelete) const SizedBox(width: 8),
                        if (canDelete)
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                final ok = await showDialog<bool>(
                                  context: ctx,
                                  builder: (dctx) => AlertDialog(
                                    title: const Text('Delete DTR survey?'),
                                    content: Text('Permanently delete ${detail['dtr_name'] ?? 'this survey'}?'),
                                    actions: [
                                      TextButton(onPressed: () => Navigator.pop(dctx, false), child: const Text('Cancel')),
                                      FilledButton(
                                        onPressed: () => Navigator.pop(dctx, true),
                                        style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                                        child: const Text('Delete'),
                                      ),
                                    ],
                                  ),
                                );
                                if (ok != true) return;
                                try {
                                  await api.delete('/surveys/$id');
                                  if (ctx.mounted) Navigator.pop(ctx);
                                  await _load();
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('DTR survey deleted'), backgroundColor: SeasColors.ink950),
                                  );
                                } catch (e) {
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                                }
                              },
                              icon: const Icon(Icons.delete_outline_rounded, size: 18),
                              label: const Text('Delete'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: SeasColors.ink950,
                                side: const BorderSide(color: SeasColors.ink200),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                              ),
                            ),
                          ),
                      ],
                    ),
                    if (canApprove && status == 'pending_approval') ...[
                      const SizedBox(height: 18),
                      Text('Decision', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                      const SizedBox(height: 8),
                      SeasTextField(
                        label: 'Remarks',
                        controller: remarks,
                        hint: 'Optional for approve · required for reject',
                        maxLines: 3,
                      ),
                      if (remarkError != null) ...[
                        const SizedBox(height: 6),
                        Text(remarkError!, style: const TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w700, fontSize: 12.5)),
                      ],
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: () async {
                          try {
                            await api.post('/surveys/$id/approve', {'review_remarks': remarks.text.trim()});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.success, padding: const EdgeInsets.symmetric(vertical: 14)),
                        child: const Text('Approve'),
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: () async {
                          if (remarks.text.trim().isEmpty) {
                            setSheet(() => remarkError = 'Rejection remarks are mandatory. Enter a reason before rejecting.');
                            return;
                          }
                          setSheet(() => remarkError = null);
                          try {
                            await api.post('/surveys/$id/reject', {'review_remarks': remarks.text.trim()});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950, padding: const EdgeInsets.symmetric(vertical: 14)),
                        child: const Text('Reject'),
                      ),
                    ] else ...[
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
                    ],
                  ],
                ),
              );
            },
          );
        });
      },
    );
  }

  Future<void> _editDtrSurvey(int id, Map detail) async {
    String? condition = detail['dtr_condition']?.toString();
    String? ltLineType = detail['lt_line_type']?.toString();
    // Legacy OH/OG → Under Ground / Over Ground for the editor.
    final ltU = (ltLineType ?? '').trim().toUpperCase().replaceAll(RegExp(r'\s+'), ' ');
    if (const {'OH', 'OH LINE', 'O.H. LINE', 'OVERHEAD', 'OVERHEAD LINE'}.contains(ltU)) {
      ltLineType = 'Over Ground';
    } else if (const {'OG', 'OG LINE', 'UG', 'UG LINE', 'UNDERGROUND', 'UNDER GROUND'}.contains(ltU)) {
      ltLineType = 'Under Ground';
    }
    String? smartStatus = detail['smart_meter_status']?.toString();
    String? oldCond = detail['old_meter_condition']?.toString();
    String? newMake = detail['new_meter_make']?.toString();
    String? ctRatio = detail['new_meter_ct_ratio']?.toString();
    final capacity = TextEditingController(text: '${detail['dtr_capacity_kva'] ?? ''}');
    final oldMsn = TextEditingController(text: '${detail['old_msn'] ?? ''}');
    final oldMake = TextEditingController(text: '${detail['old_meter_make'] ?? ''}');
    final newMsn = TextEditingController(text: '${detail['new_msn'] ?? ''}');
    final newMf = TextEditingController(text: '${detail['new_meter_mf'] ?? ''}');
    final observation = TextEditingController(text: '${detail['observation'] ?? ''}');
    final lat = TextEditingController(text: '${detail['latitude'] ?? ''}');
    final lng = TextEditingController(text: '${detail['longitude'] ?? ''}');

    final conditions = _optList('dtr_conditions', ['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other']);
    final ltLineTypes = _optList('lt_line_types', ['Under Ground', 'Over Ground']);
    final smartStatuses = _optList('smart_meter_statuses', ['Installed', 'Not Installed', 'Meter Missing']);
    final oldConditions = _optList('old_meter_conditions', ['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed']);
    final newMakes = _optList('new_meter_makes', ['L&T Schneider', 'HPL', 'Visiontek']);
    final ctRatios = _optList('ct_ratios', ['100/5', '150/5', '200/5', '300/5']);

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setModal) {
          return Padding(
            padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
            child: Container(
              constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(ctx).height * 0.92),
              decoration: const BoxDecoration(
                color: SeasColors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
              child: ListView(
                children: [
                  Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                  const SizedBox(height: 14),
                  Text('Edit DTR survey', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 14),
                  SeasTextField(label: 'Capacity (kVA)', controller: capacity),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'DTR condition',
                    hint: 'Select',
                    value: condition,
                    options: conditions.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => condition = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'LT Line Type',
                    hint: 'Under Ground / Over Ground',
                    value: ltLineType,
                    options: ltLineTypes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => ltLineType = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'Smart meter status',
                    hint: 'Select',
                    value: smartStatus,
                    options: smartStatuses.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => smartStatus = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'Old meter condition',
                    hint: 'Select',
                    value: oldCond,
                    options: oldConditions.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => oldCond = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Old MSN', controller: oldMsn),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Old meter make', controller: oldMake),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'New MSN', controller: newMsn),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'New meter make',
                    hint: 'Select',
                    value: newMake,
                    options: newMakes.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => newMake = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasSelectField(
                    label: 'New CT ratio',
                    hint: 'Select',
                    value: ctRatio,
                    options: ctRatios.map((e) => SeasSelectOption(value: e, label: e)).toList(),
                    onSelected: (o) => setModal(() => ctRatio = o.value as String?),
                  ),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'New MF', controller: newMf),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Latitude', controller: lat),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Longitude', controller: lng),
                  const SizedBox(height: 10),
                  SeasTextField(label: 'Observation', controller: observation, maxLines: 3),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    style: FilledButton.styleFrom(backgroundColor: SeasColors.volt, padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('Save corrections'),
                  ),
                ],
              ),
            ),
          );
        });
      },
    );

    if (ok != true) return;
    try {
      await api.put('/surveys/$id', {
        if (capacity.text.trim().isNotEmpty) 'dtr_capacity_kva': int.tryParse(capacity.text.trim()),
        if (condition != null) 'dtr_condition': condition,
        if (ltLineType != null) 'lt_line_type': ltLineType,
        if (smartStatus != null) 'smart_meter_status': smartStatus,
        if (oldCond != null) 'old_meter_condition': oldCond,
        'old_msn': oldMsn.text.trim(),
        'old_meter_make': oldMake.text.trim(),
        'new_msn': newMsn.text.trim(),
        if (newMake != null) 'new_meter_make': newMake,
        if (ctRatio != null) 'new_meter_ct_ratio': ctRatio,
        'new_meter_mf': newMf.text.trim(),
        'observation': observation.text.trim(),
        if (lat.text.trim().isNotEmpty) 'latitude': double.tryParse(lat.text.trim()),
        if (lng.text.trim().isNotEmpty) 'longitude': double.tryParse(lng.text.trim()),
      });
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('DTR survey updated'), backgroundColor: SeasColors.ink950),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Widget _countChip(String label, String value, Color accent) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: accent.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.ink950)),
          Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: accent)),
        ],
      ),
    );
  }

  Future<void> _previewImage(String? url) async {
    if (url == null || url.isEmpty || !mounted) return;
    await showDialog<void>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.black,
        insetPadding: const EdgeInsets.all(16),
        child: Stack(
          children: [
            InteractiveViewer(
              child: AspectRatio(
                aspectRatio: 1,
                child: Image.network(url, fit: BoxFit.contain, errorBuilder: (_, __, ___) {
                  return const Center(child: Icon(Icons.broken_image_outlined, color: Colors.white54, size: 48));
                }),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton(
                onPressed: () => Navigator.pop(ctx),
                icon: const Icon(Icons.close, color: Colors.white),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _reviewMetaRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 110, child: Text(label, style: const TextStyle(color: SeasColors.ink400, fontSize: 12.5))),
          Expanded(child: Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13))),
        ],
      ),
    );
  }

  Widget _sldThumb(String? url, String caption, bool latest, {VoidCallback? onTap}) {
    return SizedBox(
      width: 140,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Material(
              color: SeasColors.canvasSoft,
              borderRadius: BorderRadius.circular(12),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: onTap,
                child: Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: latest ? SeasColors.volt : SeasColors.ink100, width: latest ? 2 : 1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: url == null
                      ? const Center(child: Icon(Icons.image_not_supported_outlined, color: SeasColors.ink400))
                      : Image.network(
                          url,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          height: double.infinity,
                          loadingBuilder: (context, child, progress) {
                            if (progress == null) return child;
                            return const Center(child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)));
                          },
                          errorBuilder: (_, __, ___) => const Center(
                            child: Icon(Icons.broken_image_outlined, color: SeasColors.ink400),
                          ),
                        ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            caption,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 11, fontWeight: latest ? FontWeight.w800 : FontWeight.w600, color: latest ? SeasColors.volt : SeasColors.ink400),
          ),
        ],
      ),
    );
  }

  Widget _reviewListButton({required VoidCallback onPressed}) {
    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(
        backgroundColor: SeasColors.volt,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        minimumSize: const Size(0, 36),
      ),
      child: Text('Review', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Manager',
      title: 'Team Control',
      actions: [
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
        IconButton(onPressed: _assignZones, icon: const Icon(Icons.map_rounded, color: SeasColors.volt), tooltip: 'Assign zones'),
        IconButton(onPressed: _assign, icon: const Icon(Icons.person_add_alt_1_rounded, color: SeasColors.volt), tooltip: 'Assign feeder work'),
      ],
      child: Column(
        children: [
          TabBar(
            controller: tabs,
            isScrollable: true,
            labelColor: SeasColors.volt,
            unselectedLabelColor: SeasColors.ink400,
            indicatorColor: SeasColors.volt,
            labelStyle: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
            tabs: const [
              Tab(text: 'Assignments'),
              Tab(text: 'Zone Assign'),
              Tab(text: 'Feeder SLD'),
            ],
          ),
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : error != null
                    ? SeasEmptyState(title: 'Could not load', subtitle: error)
                    : TabBarView(
                        controller: tabs,
                        children: [
                          _assignmentsTab(),
                          _zoneAssignTab(),
                          _feederSurveysTab(),
                        ],
                      ),
          ),
        ],
      ),
    );
  }

  Widget _assignmentsTab() {
    return RefreshIndicator(
      onRefresh: _load,
      child: assignments.isEmpty
          ? ListView(children: const [SizedBox(height: 80), SeasEmptyState(title: 'No assignments', subtitle: 'Tap + to assign date-wise work to a Field Executive')])
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: assignments.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, i) {
                final a = assignments[i] as Map;
                final status = '${a['status'] ?? 'open'}';
                return SeasCard(
                  child: Row(
                    children: [
                      SeasIconTile(
                        icon: status == 'closed' ? Icons.lock_clock_rounded : Icons.assignment_ind_rounded,
                        bg: status == 'closed' ? SeasColors.ink200 : SeasColors.ink950,
                        fg: status == 'closed' ? SeasColors.ink700 : Colors.white,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${a['assignee']?['name'] ?? 'FE'}', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                            Text(
                              '${a['feeder']?['name'] ?? a['feeder']?['code'] ?? 'Feeder'}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Work date: ${_prettyDate(a['work_date'])}',
                              style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: SeasColors.ink700),
                            ),
                            if ((a['notes'] ?? '').toString().isNotEmpty)
                              Text('${a['notes']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
                          ],
                        ),
                      ),
                      SeasBadge(status, tone: badgeToneForStatus(status)),
                    ],
                  ),
                );
              },
            ),
    );
  }

  Widget _zoneAssignTab() {
    final filteredFeeders = zoneFeeders.where((raw) {
      if (zoneFeederQuery.trim().isEmpty) return true;
      final f = raw as Map;
      final q = zoneFeederQuery.toLowerCase();
      final hay = '${f['code'] ?? ''} ${f['name'] ?? ''} ${f['substation']?['name'] ?? ''}'.toLowerCase();
      return hay.contains(q);
    }).toList();

    final currentAssignments = workAssignments.where((raw) {
      final a = raw as Map;
      final status = '${a['status'] ?? ''}';
      return status == 'open' || status == 'started';
    }).toList();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Assign work by zone', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 4),
          const Text(
            'Select a zone, review its feeders, then assign feeders to a Field Executive. FEs can survey only assigned feeders.',
            style: TextStyle(color: SeasColors.ink400, fontSize: 13, height: 1.35),
          ),
          const SizedBox(height: 14),
          SeasSelectField(
            label: 'Zone',
            hint: 'Search & select zone',
            value: selectedZoneId,
            options: assignableZones
                .map((z) => SeasSelectOption(
                      value: (z['id'] as num).toInt(),
                      label: '${z['name']}',
                      subtitle: '${z['label'] ?? ''}',
                    ))
                .toList(),
            onSelected: (o) {
              final id = o.value as int?;
              if (id != null) _loadZoneFeeders(id);
            },
          ),
          if (selectedZoneId != null) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: SeasColors.voltSoft,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: SeasColors.volt.withValues(alpha: 0.25)),
              ),
              child: Text(
                zoneFeedersLoading
                    ? 'Loading feeders…'
                    : '$zoneFeederCount feeder${zoneFeederCount == 1 ? '' : 's'} in this zone',
                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: SeasColors.volt, fontSize: 14),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              decoration: InputDecoration(
                labelText: 'Filter feeders',
                hintText: 'Code, name, substation…',
                prefixIcon: const Icon(Icons.search_rounded),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
              ),
              onChanged: (v) => setState(() => zoneFeederQuery = v),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Text(
                  '${selectedFeederIds.length} selected · ${filteredFeeders.length} shown',
                  style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: SeasColors.ink400),
                ),
                const Spacer(),
                TextButton(
                  onPressed: filteredFeeders.isEmpty
                      ? null
                      : () => setState(() {
                            for (final raw in filteredFeeders) {
                              final id = ((raw as Map)['id'] as num?)?.toInt();
                              if (id != null) selectedFeederIds.add(id);
                            }
                          }),
                  child: Text('Select all', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: SeasColors.volt)),
                ),
                TextButton(
                  onPressed: selectedFeederIds.isEmpty ? null : () => setState(() => selectedFeederIds.clear()),
                  child: Text('Clear', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: SeasColors.ink400)),
                ),
              ],
            ),
            if (zoneFeedersLoading)
              const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator()))
            else if (filteredFeeders.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: SeasEmptyState(title: 'No feeders', subtitle: 'This zone has no active feeders'),
              )
            else
              ...filteredFeeders.map((raw) {
                final f = raw as Map;
                final id = (f['id'] as num).toInt();
                final checked = selectedFeederIds.contains(id);
                final assignedName = f['assigned_to']?['name'];
                return CheckboxListTile(
                  value: checked,
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  activeColor: SeasColors.volt,
                  title: Text(
                    '${f['code'] ?? ''} · ${f['name'] ?? ''}',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 14),
                  ),
                  subtitle: Text(
                    '${f['substation']?['name'] ?? 'Substation'}${assignedName != null ? ' · assigned: $assignedName' : ''}',
                    style: const TextStyle(fontSize: 11, color: SeasColors.ink400),
                  ),
                  onChanged: (v) => setState(() {
                    if (v == true) {
                      selectedFeederIds.add(id);
                    } else {
                      selectedFeederIds.remove(id);
                    }
                  }),
                );
              }),
            const SizedBox(height: 12),
            SeasSelectField(
              label: 'Field Executive',
              hint: 'Select FE to assign',
              value: assignFeId,
              options: executives
                  .map((e) => SeasSelectOption(value: e['id'], label: '${e['name']}', subtitle: '${e['email']}'))
                  .toList(),
              onSelected: (o) => setState(() => assignFeId = o.value as int?),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: selectedFeederIds.isEmpty || assignFeId == null ? null : _assignSelectedFeeders,
              icon: const Icon(Icons.assignment_ind_rounded),
              label: Text(
                selectedFeederIds.isEmpty ? 'Assign feeders' : 'Assign ${selectedFeederIds.length} feeder${selectedFeederIds.length == 1 ? '' : 's'}',
              ),
              style: FilledButton.styleFrom(
                backgroundColor: SeasColors.volt,
                disabledBackgroundColor: SeasColors.ink200,
                padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
              ),
            ),
          ],
          const SizedBox(height: 22),
          Text('Current assignments', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
          const SizedBox(height: 4),
          Text(
            'Assignment stays until FE uploads SLD, or you Complete it. Daily reminder goes to FE while open.',
            style: GoogleFonts.plusJakartaSans(fontSize: 12, color: SeasColors.ink400),
          ),
          const SizedBox(height: 10),
          if (currentAssignments.isEmpty)
            const SeasEmptyState(title: 'No active assignments', subtitle: 'Assign feeders from a zone above')
          else
            ...currentAssignments.map((raw) {
              final a = raw as Map;
              final status = '${a['status'] ?? 'open'}';
              final canReassign = a['can_reassign'] == true;
              final canComplete = a['can_complete'] == true || status == 'open' || status == 'started';
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SeasCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          SeasIconTile(icon: Icons.electrical_services_rounded, bg: SeasColors.voltSoft, fg: SeasColors.volt),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${a['feeder']?['code'] ?? ''} · ${a['feeder']?['name'] ?? 'Feeder'}',
                                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                                ),
                                Text(
                                  '${a['assignee']?['name'] ?? 'FE'} · ${a['zone']?['name'] ?? 'Zone'}',
                                  style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                          SeasBadge(status, tone: badgeToneForStatus(status)),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          if (canReassign)
                            OutlinedButton(
                              onPressed: () => _reassignAssignment(a),
                              style: OutlinedButton.styleFrom(foregroundColor: SeasColors.volt, side: const BorderSide(color: SeasColors.volt)),
                              child: const Text('Reassign'),
                            ),
                          if (canReassign)
                            TextButton(
                              onPressed: () => _unassignAssignment(a),
                              child: const Text('Unassign'),
                            ),
                          if (canComplete)
                            FilledButton(
                              onPressed: () => _completeAssignment(a),
                              style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950),
                              child: const Text('Complete assign'),
                            ),
                        ],
                      ),
                      if (!canReassign && status == 'started')
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text(
                            'Survey started — stays assigned until SLD or you Complete assign',
                            style: GoogleFonts.plusJakartaSans(fontSize: 11, fontWeight: FontWeight.w700, color: SeasColors.ink400),
                          ),
                        ),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }

  Widget _feederSurveysTab() {
    final pending = feederSurveys.where((s) => '${(s as Map)['status'] ?? ''}' == 'pending_approval').toList();
    return RefreshIndicator(
      onRefresh: _load,
      child: feederSurveys.isEmpty
          ? ListView(children: const [SizedBox(height: 80), SeasEmptyState(title: 'No feeder surveys', subtitle: 'Surveyor feeder → DTR work will appear here')])
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (reviewStats.isNotEmpty) ...[
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _countChip('SLD pending', '${reviewStats['sld_pending'] ?? 0}', const Color(0xFFEA580C)),
                      _countChip('Approved', '${reviewStats['approved'] ?? 0}', SeasColors.success),
                      _countChip('Awaiting review', '${pending.length}', SeasColors.volt),
                    ],
                  ),
                  const SizedBox(height: 12),
                ],
                ...List.generate(feederSurveys.length, (i) {
                  final s = feederSurveys[i] as Map;
                  final status = '${s['status'] ?? ''}';
                  final label = '${s['display_status'] ?? status}';
                  final locked = s['is_locked'] == true;
                  final needsReview = status == 'pending_approval';
                  return Padding(
                    padding: EdgeInsets.only(bottom: i == feederSurveys.length - 1 ? 0 : 10),
                    child: SeasCard(
                      onTap: () => _reviewFeeder(s),
                      child: Row(
                        children: [
                          SeasIconTile(
                            icon: locked ? Icons.lock_rounded : Icons.account_tree_outlined,
                            bg: SeasColors.voltSoft,
                            fg: SeasColors.volt,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${s['feeder_name'] ?? 'Feeder'}',
                                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                                ),
                                Text(
                                  '${s['surveyor']?['name'] ?? 'Surveyor'} · DTRs ${s['dtrs_completed'] ?? 0}/${s['dtrs_expected'] ?? '?'}',
                                  style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              SeasBadge(label, tone: badgeToneForStatus(status)),
                              if (locked) ...[
                                const SizedBox(height: 4),
                                Text('Locked', style: GoogleFonts.plusJakartaSans(fontSize: 10, fontWeight: FontWeight.w800, color: SeasColors.ink700)),
                              ],
                              if (needsReview) ...[
                                const SizedBox(height: 8),
                                _reviewListButton(onPressed: () => _reviewFeeder(s)),
                              ],
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            ),
    );
  }

}
