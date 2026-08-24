import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../core/pole_map_export.dart';
import '../theme/seas_colors.dart';
import '../widgets/pole_form_sheet.dart';
import '../widgets/seas_glass_header.dart';
import '../widgets/seas_widgets.dart';
import 'consumer_identify_screen.dart';
import 'consumer_survey_success_screen.dart';
import 'pole_map_screen.dart';

/// Pole list under approved DTR — A1, A2… surveyed count saves as you go.
class PoleSelectionScreen extends StatefulWidget {
  const PoleSelectionScreen({super.key, required this.dtrSurvey});
  final Map<String, dynamic> dtrSurvey;

  @override
  State<PoleSelectionScreen> createState() => _PoleSelectionScreenState();
}

class _PoleSelectionScreenState extends State<PoleSelectionScreen> with SingleTickerProviderStateMixin {
  bool loading = true;
  String? error;
  List poles = [];
  Map<String, dynamic> stats = {};
  String query = '';
  late final AnimationController _pulse;

  int get surveyId => widget.dtrSurvey['id'] as int;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 1600))..repeat(reverse: true);
    _load();
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await api.get('/consumer/$surveyId/poles');
      poles = (res['poles'] as List?) ?? [];
      stats = Map<String, dynamic>.from((res['stats'] as Map?) ?? {});
      if (res['survey'] is Map) {
        widget.dtrSurvey.addAll(Map<String, dynamic>.from(res['survey'] as Map));
      }
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    }
    if (mounted) setState(() => loading = false);
  }

  List get filtered {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return poles;
    return poles.where((p) => '${p['pole_no']}'.toLowerCase().contains(q)).toList();
  }

  Future<void> _openAddPole() async {
    await Navigator.push<Object?>(
      context,
      PageRouteBuilder(
        pageBuilder: (_, a, __) => FadeTransition(
          opacity: a,
          child: PoleMapScreen(dtrSurvey: widget.dtrSurvey, startInPinMode: true),
        ),
        transitionDuration: const Duration(milliseconds: 280),
      ),
    );
    await _load();
  }

  Future<void> _openPoleMap({bool pinMode = false}) async {
    await Navigator.push<Object?>(
      context,
      MaterialPageRoute(
        builder: (_) => PoleMapScreen(dtrSurvey: widget.dtrSurvey, startInPinMode: pinMode),
      ),
    );
    await _load();
  }

  Future<void> _openEditPole(Map pole) => _openPoleForm(existing: Map<String, dynamic>.from(pole));

  Future<void> _downloadPins() async {
    final withCoords = poles.where((p) {
      final m = Map<String, dynamic>.from(p as Map);
      final lat = m['latitude'];
      final lng = m['longitude'];
      return lat != null && lng != null && '$lat'.isNotEmpty && '$lng'.isNotEmpty;
    }).toList();
    if (withCoords.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('No pins yet — Add Pole on map pe pehle pin drop karo.'),
        backgroundColor: SeasColors.voltDeep,
      ));
      return;
    }
    try {
      final dtrName = '${widget.dtrSurvey['dtr_name'] ?? ''}';
      final dtrCode = '${widget.dtrSurvey['dtr_code'] ?? ''}';
      final base = await PoleMapExport.downloadAll(withCoords, dtrName: dtrName, dtrCode: dtrCode);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('Downloaded $base.csv + HTML map + GeoJSON'),
        backgroundColor: SeasColors.ink950,
      ));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString().replaceFirst('Exception: ', '')),
        backgroundColor: SeasColors.voltDeep,
      ));
    }
  }

  /// Long-press ~3s → Back / Delete sheet → confirm delete.
  Future<void> _onPoleLongPress(Map pole) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
              const SizedBox(height: 14),
              Text(
                'Pole ${pole['pole_no']}',
                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18),
              ),
              const SizedBox(height: 6),
              const Text(
                'Long-press options — go back or delete this pole.',
                style: TextStyle(color: SeasColors.ink400, fontSize: 13),
              ),
              const SizedBox(height: 16),
              OutlinedButton(
                onPressed: () => Navigator.pop(ctx, 'back'),
                child: const Text('Back'),
              ),
              const SizedBox(height: 10),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                onPressed: () => Navigator.pop(ctx, 'delete'),
                child: const Text('Delete Pole'),
              ),
            ],
          ),
        ),
      ),
    );
    if (action != 'delete' || !mounted) return;

    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: Text('Are you sure?', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        content: Text('Delete pole ${pole['pole_no']}? Linked consumer surveys on this pole will also be removed.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('No')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            onPressed: () => Navigator.pop(d, true),
            child: const Text('Yes, delete'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    try {
      await api.delete('/consumer/$surveyId/poles/${pole['id']}');
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('Pole ${pole['pole_no']} deleted'),
        backgroundColor: SeasColors.ink950,
      ));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString().replaceFirst('Exception: ', '')),
        backgroundColor: SeasColors.voltDeep,
      ));
    }
  }

  Future<void> _openPoleForm({Map<String, dynamic>? existing}) async {
    final editing = existing != null;
    final result = await showPoleFormSheet(
      context: context,
      poles: poles,
      existing: existing,
      captureGpsIfMissing: !editing,
    );
    if (result == null || !mounted) return;
    if (result.sourceType == 'previous_pole' && result.previousPoleId == null) {
      setState(() => error = 'Please select a previous pole.');
      return;
    }
    if (result.housesConnected < 0) {
      setState(() => error = 'Expected houses / consumers cannot be negative.');
      return;
    }
    if (!editing &&
        (result.latitude == null ||
            result.longitude == null ||
            result.latitude!.isEmpty ||
            result.longitude!.isEmpty)) {
      setState(() => error = 'GPS / map pin location required to add a pole.');
      return;
    }
    try {
      final path = editing ? '/consumer/$surveyId/poles/${existing['id']}' : '/consumer/$surveyId/poles';
      if (result.hasPhoto) {
        await api.postMultipart(
          path: path,
          fields: result.toMultipartFields(),
          filePaths: result.photoPath == null ? null : {'photo': result.photoPath!},
          fileBytes: result.photoPath != null || result.photoBytes == null
              ? null
              : {'photo': result.photoBytes!},
        );
      } else if (editing) {
        await api.put(path, result.toPayload());
      } else {
        await api.post(path, result.toPayload());
      }
      await _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(editing ? 'Pole updated' : 'Pole added'),
          backgroundColor: SeasColors.ink950,
        ));
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _manageSurveysOnPole(Map pole) async {
    final poleId = (pole['id'] as num?)?.toInt();
    if (poleId == null) return;
    try {
      final res = await api.get('/consumer/$surveyId/my-surveys?pole_id=$poleId');
      final rows = ((res['data'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
      if (!mounted) return;
      if (rows.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('No surveys on this pole yet')));
        return;
      }
      await showModalBottomSheet<void>(
        context: context,
        backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
        builder: (ctx) {
          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Surveys on ${pole['pole_no']}',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Delete if you submitted the wrong consumer — then verify again.',
                    style: TextStyle(fontSize: 12, color: SeasColors.ink400),
                  ),
                  const SizedBox(height: 10),
                  ...rows.map((r) {
                    final canDelete = r['can_delete'] == true;
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('${r['consumer_name'] ?? 'Consumer'}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      subtitle: Text('IVRS ${r['ivrs'] ?? '—'} · ${r['status'] ?? ''}'),
                      trailing: canDelete
                          ? IconButton(
                              icon: const Icon(Icons.delete_outline_rounded, color: Color(0xFFB91C1C)),
                              onPressed: () async {
                                final ok = await showDialog<bool>(
                                  context: ctx,
                                  builder: (d) => AlertDialog(
                                    title: const Text('Delete this survey?'),
                                    content: Text('Remove ${r['consumer_name'] ?? 'consumer'} verification? You can survey again.'),
                                    actions: [
                                      TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancel')),
                                      FilledButton(
                                        onPressed: () => Navigator.pop(d, true),
                                        style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                                        child: const Text('Delete'),
                                      ),
                                    ],
                                  ),
                                );
                                if (ok != true) return;
                                try {
                                  await api.delete('/my-consumer-surveys/${r['id']}');
                                  if (ctx.mounted) Navigator.pop(ctx);
                                  await _load();
                                  if (mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(content: Text('Survey deleted'), backgroundColor: SeasColors.ink950),
                                    );
                                  }
                                } catch (e) {
                                  if (mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                                  }
                                }
                              },
                            )
                          : Text('${r['status']}', style: const TextStyle(fontSize: 11, color: SeasColors.ink400)),
                    );
                  }),
                ],
              ),
            ),
          );
        },
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _openPole(Map pole) async {
    final result = await Navigator.push<Object?>(
      context,
      PageRouteBuilder(
        pageBuilder: (_, a, __) => FadeTransition(
          opacity: a,
          child: ConsumerIdentifyScreen(
            dtrSurvey: widget.dtrSurvey,
            pole: Map<String, dynamic>.from(pole),
          ),
        ),
        transitionDuration: const Duration(milliseconds: 280),
      ),
    );
    await _load();
    if (!mounted) return;
    if (result == ConsumerSurveyNav.nextPole) {
      final currentId = (pole['id'] as num?)?.toInt();
      final idx = poles.indexWhere((p) => (p['id'] as num?)?.toInt() == currentId);
      if (idx >= 0 && idx + 1 < poles.length) {
        await _openPole(Map<String, dynamic>.from(poles[idx + 1] as Map));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('No next pole — use Add Pole.'),
          backgroundColor: SeasColors.ink950,
        ));
      }
    } else if (result == ConsumerSurveyNav.addPole) {
      // Stay on Pole Selection list — user can Add Pole from the bottom CTA.
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Pole list — tap Add Pole to create, or select a pole to survey.'),
          backgroundColor: SeasColors.ink950,
        ));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final dtrName = '${widget.dtrSurvey['dtr_name'] ?? '—'}';
    final dtrCode = '${widget.dtrSurvey['dtr_code'] ?? '—'}';
    final feeder = '${widget.dtrSurvey['feeder_name'] ?? widget.dtrSurvey['feeder']?['name'] ?? '—'}';
    final surveyed = stats['surveyed_consumers'] ?? 0;
    final expectedHouses = stats['total_houses'] ?? 0;
    final list = filtered;

    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Pole Selection',
        subtitle: 'Black · Red · White field OS',
        onBack: () => Navigator.pop(context),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              tooltip: 'Pole map',
              onPressed: () => _openPoleMap(pinMode: false),
              icon: const Icon(Icons.map_outlined, color: SeasColors.ink950),
            ),
            IconButton(
              tooltip: 'Download pins',
              onPressed: _downloadPins,
              icon: const Icon(Icons.download_rounded, color: SeasColors.ink950),
            ),
            IconButton(
              onPressed: () => Navigator.popUntil(context, (r) => r.isFirst),
              icon: const Icon(Icons.home_outlined, color: SeasColors.ink950),
            ),
          ],
        ),
      ),
      bottom: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _openAddPole,
                  icon: const Icon(Icons.add_location_alt_rounded),
                  label: const Text('Add Pole on Map'),
                  style: FilledButton.styleFrom(
                    backgroundColor: SeasColors.volt,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    elevation: 0,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _downloadPins,
                  icon: const Icon(Icons.download_rounded, size: 18),
                  label: const Text('Download Map / Pins'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: SeasColors.ink950,
                    side: const BorderSide(color: SeasColors.ink100),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : RefreshIndicator(
              color: SeasColors.volt,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
                children: [
                  if (error != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(14)),
                      child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
                    ),
                  FadeTransition(
                    opacity: Tween(begin: 0.92, end: 1.0).animate(_pulse),
                    child: Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(22),
                        gradient: const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [SeasColors.white, Color(0xFFFFF8F8)],
                        ),
                        border: Border.all(color: SeasColors.ink100),
                        boxShadow: SeasShadows.card,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: SeasColors.ink950,
                                borderRadius: BorderRadius.circular(14),
                                boxShadow: SeasShadows.glow,
                              ),
                              child: const Icon(Icons.factory_outlined, color: Colors.white, size: 20),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(dtrName, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
                                  Text('$dtrCode · $feeder', style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                                ],
                              ),
                            ),
                          ]),
                          const SizedBox(height: 14),
                          Row(children: [
                            _MiniStat(label: 'Poles', value: '${poles.length}'),
                            const SizedBox(width: 8),
                            _MiniStat(label: 'Expected', value: '$expectedHouses'),
                            const SizedBox(width: 8),
                            _MiniStat(label: 'Surveyed', value: '$surveyed', accent: true),
                          ]),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    onChanged: (v) => setState(() => query = v),
                    decoration: InputDecoration(
                      hintText: 'Search A1, A2…',
                      prefixIcon: const Icon(Icons.search, color: SeasColors.ink400),
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text('Poles (${poles.length})', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                  const SizedBox(height: 4),
                  const Text(
                    'Add Pole opens map — drop pins; list long-press (~3s) still deletes. Download pins as CSV + HTML map.',
                    style: TextStyle(color: SeasColors.ink400, fontSize: 12),
                  ),
                  const SizedBox(height: 12),
                  if (list.isEmpty)
                    const SeasEmptyState(
                      title: 'No poles yet',
                      subtitle: 'Tap Add Pole on Map — drop a pin for A1.',
                      icon: Icons.electrical_services_outlined,
                    )
                  else
                    ...List.generate(list.length, (i) {
                      final p = list[i] as Map;
                      final surveyedOnPole = p['surveyed_count'] ?? 0;
                      final expected = p['houses_connected'] ?? 0;
                      final poleNo = '${p['pole_no']}'.trim();
                      final title = _poleTitle(poleNo);
                      final source = p['source_type'] == 'previous_pole'
                          ? 'From ${p['previous_pole']?['pole_no'] ?? 'pole'}'
                          : 'From DTR';
                      return TweenAnimationBuilder<double>(
                        tween: Tween(begin: 0, end: 1),
                        duration: Duration(milliseconds: 280 + (i * 40)),
                        curve: Curves.easeOutCubic,
                        builder: (_, v, child) => Opacity(
                          opacity: v,
                          child: Transform.translate(offset: Offset(0, 12 * (1 - v)), child: child),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Material(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(18),
                            elevation: 0,
                            child: Container(
                              padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(18),
                                border: Border.all(color: SeasColors.ink100),
                                boxShadow: const [
                                  BoxShadow(color: Color(0x080F172A), blurRadius: 12, offset: Offset(0, 4)),
                                ],
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: _LongPress3s(
                                      onTap: () => _openPole(Map<String, dynamic>.from(p)),
                                      onLongPress: () => _onPoleLongPress(Map<String, dynamic>.from(p)),
                                      child: Row(
                                        children: [
                                          _PoleIconBadge(index: i),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(title, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                                                const SizedBox(height: 2),
                                                Text(
                                                  '$source · Expected $expected · Surveyed $surveyedOnPole',
                                                  style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                                ),
                                              ],
                                            ),
                                          ),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                            decoration: BoxDecoration(
                                              color: SeasColors.voltSoft,
                                              borderRadius: BorderRadius.circular(99),
                                            ),
                                            child: Text(
                                              '$surveyedOnPole/$expected',
                                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: SeasColors.volt, fontSize: 12),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                  if ((surveyedOnPole as num?)?.toInt() != null && (surveyedOnPole as num).toInt() > 0)
                                    IconButton(
                                      tooltip: 'Delete wrong survey',
                                      onPressed: () => _manageSurveysOnPole(Map<String, dynamic>.from(p)),
                                      icon: const Icon(Icons.playlist_remove_rounded, size: 22, color: Color(0xFFB91C1C)),
                                      visualDensity: VisualDensity.compact,
                                    ),
                                  IconButton(
                                    tooltip: 'Edit pole',
                                    onPressed: () => _openEditPole(Map<String, dynamic>.from(p)),
                                    icon: const Icon(Icons.edit_outlined, size: 20, color: SeasColors.ink950),
                                    visualDensity: VisualDensity.compact,
                                  ),
                                  const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400),
                                ],
                              ),
                            ),
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }

  String _poleTitle(String poleNo) {
    final n = poleNo.trim();
    if (n.isEmpty) return 'Pole';
    if (n.toLowerCase().startsWith('pole')) return n;
    return 'Pole $n';
  }
}

/// Hold for 3 seconds to trigger [onLongPress] (PDF: long press to delete pole).
class _LongPress3s extends StatefulWidget {
  const _LongPress3s({required this.child, required this.onTap, required this.onLongPress});
  final Widget child;
  final VoidCallback onTap;
  final VoidCallback onLongPress;

  @override
  State<_LongPress3s> createState() => _LongPress3sState();
}

class _LongPress3sState extends State<_LongPress3s> {
  Timer? _timer;
  bool _fired = false;

  void _start() {
    _fired = false;
    _timer?.cancel();
    _timer = Timer(const Duration(seconds: 3), () {
      _fired = true;
      widget.onLongPress();
    });
  }

  void _cancel() {
    _timer?.cancel();
    _timer = null;
  }

  @override
  void dispose() {
    _cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        if (!_fired) widget.onTap();
      },
      onTapDown: (_) => _start(),
      onTapUp: (_) => _cancel(),
      onTapCancel: _cancel,
      child: widget.child,
    );
  }
}

class _PoleIconBadge extends StatelessWidget {
  const _PoleIconBadge({required this.index});
  final int index;

  @override
  Widget build(BuildContext context) {
    final icons = [
      Icons.cell_tower_rounded,
      Icons.electrical_services_rounded,
      Icons.bolt_rounded,
      Icons.hub_rounded,
    ];
    final icon = icons[index % icons.length];
    return Container(
      height: 52,
      width: 52,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [SeasColors.ink950, Color(0xFF3A0A0A), SeasColors.voltDeep],
        ),
        boxShadow: [
          BoxShadow(color: SeasColors.volt.withValues(alpha: 0.28), blurRadius: 14, offset: const Offset(0, 6)),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned(
            right: 6,
            top: 6,
            child: Container(
              height: 8,
              width: 8,
              decoration: BoxDecoration(
                color: SeasColors.volt,
                shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: SeasColors.volt.withValues(alpha: 0.6), blurRadius: 6)],
              ),
            ),
          ),
          Icon(icon, color: Colors.white, size: 24),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value, this.accent = false});
  final String label;
  final String value;
  final bool accent;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
        decoration: BoxDecoration(
          color: accent ? SeasColors.voltSoft : SeasColors.canvasSoft,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 20, color: accent ? SeasColors.volt : SeasColors.ink950)),
            Text(label, style: TextStyle(color: accent ? SeasColors.voltDeep : SeasColors.ink400, fontSize: 11, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}
