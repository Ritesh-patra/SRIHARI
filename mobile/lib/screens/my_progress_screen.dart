import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../core/seas_date_range.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_glass_header.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';
import 'dtr_survey_form.dart';
import 'feeder_survey_form.dart';

/// Step 1: filters. Step 2: survey list with edit/delete until manager approval.
class MyProgressFilterScreen extends StatefulWidget {
  const MyProgressFilterScreen({super.key});

  @override
  State<MyProgressFilterScreen> createState() => _MyProgressFilterScreenState();
}

class _MyProgressFilterScreenState extends State<MyProgressFilterScreen> {
  String type = 'all';
  String status = 'all';
  late DateTime from;
  late DateTime to;

  @override
  void initState() {
    super.initState();
    to = DateTime.now();
    from = to.subtract(const Duration(days: 30));
  }

  String _fmt(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _pickRange() async {
    final picked = await pickSeasDateRange(
      context,
      initial: DateTimeRange(start: from, end: to),
      helpText: 'Survey date range',
    );
    if (picked == null) return;
    setState(() {
      from = picked.start;
      to = picked.end;
    });
  }

  void _apply() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MyProgressListScreen(
          type: type,
          status: status,
          from: _fmt(from),
          to: _fmt(to),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'My Progress',
        subtitle: 'Apply filters, then view your surveys',
        onBack: () => Navigator.pop(context),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          SeasCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Filter surveys',
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
                ),
                const SizedBox(height: 4),
                const Text(
                  'See how many surveys you completed. Open a row to edit or delete until manager approval.',
                  style: TextStyle(color: SeasColors.ink400, fontSize: 13, height: 1.4),
                ),
                const SizedBox(height: 16),
                SeasSelectField(
                  label: 'Survey type',
                  hint: 'All / Feeder / DTR / Consumer',
                  value: type,
                  options: const [
                    SeasSelectOption(value: 'all', label: 'All'),
                    SeasSelectOption(value: 'feeder', label: 'Feeder'),
                    SeasSelectOption(value: 'dtr', label: 'DTR'),
                    SeasSelectOption(value: 'consumer', label: 'Consumer'),
                  ],
                  onSelected: (o) => setState(() => type = o.value as String),
                ),
                const SizedBox(height: 12),
                SeasSelectField(
                  label: 'Status',
                  hint: 'All statuses',
                  value: status,
                  options: const [
                    SeasSelectOption(value: 'all', label: 'All'),
                    SeasSelectOption(value: 'draft', label: 'Draft'),
                    SeasSelectOption(value: 'pending_approval', label: 'Pending Approval'),
                    SeasSelectOption(value: 'sld_pending', label: 'SLD Pending'),
                    SeasSelectOption(value: 'approved', label: 'Approved / Already Surveyed'),
                    SeasSelectOption(value: 'rejected', label: 'Rejected'),
                  ],
                  onSelected: (o) => setState(() => status = o.value as String),
                ),
                const SizedBox(height: 12),
                Text('Date range', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13)),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: _pickRange,
                  icon: const Icon(Icons.date_range_rounded),
                  label: Text('${_fmt(from)}  →  ${_fmt(to)}'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: SeasColors.ink950,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    side: const BorderSide(color: SeasColors.ink100),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _apply,
                  style: FilledButton.styleFrom(
                    backgroundColor: SeasColors.volt,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: Text(
                    'Apply filters',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class MyProgressListScreen extends StatefulWidget {
  const MyProgressListScreen({
    super.key,
    required this.type,
    required this.status,
    required this.from,
    required this.to,
  });

  final String type;
  final String status;
  final String from;
  final String to;

  @override
  State<MyProgressListScreen> createState() => _MyProgressListScreenState();
}

class _MyProgressListScreenState extends State<MyProgressListScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];

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
      final qs = [
        'type=${Uri.encodeQueryComponent(widget.type)}',
        'status=${Uri.encodeQueryComponent(widget.status)}',
        'from=${Uri.encodeQueryComponent(widget.from)}',
        'to=${Uri.encodeQueryComponent(widget.to)}',
      ].join('&');
      final res = await api.get('/my-progress?$qs');
      items = ((res['data'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
      items = [];
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> _openItem(Map<String, dynamic> row) async {
    final type = '${row['type']}';
    final id = (row['id'] as num?)?.toInt();
    if (id == null) return;
    final canEdit = row['can_edit'] == true;

    if (type == 'dtr') {
      if (!canEdit) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Manager already approved — edit/delete locked.'),
        ));
        return;
      }
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => DtrSurveyFormScreen(serverId: id),
      ));
      _load();
      return;
    }

    if (type == 'feeder') {
      if (!canEdit) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Manager already approved — edit/delete locked.'),
        ));
        return;
      }
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => FeederSurveyFormScreen(serverId: id),
      ));
      _load();
      return;
    }

    if (type == 'consumer') {
      if (row['can_delete'] == true) {
        await _confirmDelete(row);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Manager already approved — cannot edit/delete.'),
        ));
      }
    }
  }

  Future<void> _confirmDelete(Map<String, dynamic> row) async {
    final type = '${row['type']}';
    final id = (row['id'] as num?)?.toInt();
    if (id == null || row['can_delete'] != true) return;

    final ok = await showDialog<bool>(
      context: context,
      builder: (d) => AlertDialog(
        title: Text('Delete this survey?', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        content: Text('Remove "${row['title']}"? You can survey again after delete (only before manager approval).'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            onPressed: () => Navigator.pop(d, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    try {
      if (type == 'dtr') {
        await api.delete('/my-dtr-surveys/$id');
      } else if (type == 'feeder') {
        await api.delete('/my-feeder-surveys/$id');
      } else if (type == 'consumer') {
        await api.delete('/my-consumer-surveys/$id');
      }
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Survey deleted'),
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

  @override
  Widget build(BuildContext context) {
    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'My surveys',
        subtitle: '${widget.from} → ${widget.to} · ${widget.type}',
        onBack: () => Navigator.pop(context),
        trailing: IconButton(
          onPressed: _load,
          icon: const Icon(Icons.refresh_rounded, color: SeasColors.ink950),
        ),
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
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(14)),
                      child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
                    ),
                  Text(
                    '${items.length} survey${items.length == 1 ? '' : 's'}',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Tap to edit · trash to delete (only until manager approval).',
                    style: TextStyle(color: SeasColors.ink400, fontSize: 12),
                  ),
                  const SizedBox(height: 12),
                  if (items.isEmpty)
                    const SeasEmptyState(
                      title: 'No surveys in this filter',
                      subtitle: 'Change filters or complete a field survey.',
                      icon: Icons.insights_outlined,
                    )
                  else
                    ...items.map((row) {
                      final type = '${row['type']}';
                      final canDelete = row['can_delete'] == true;
                      final canEdit = row['can_edit'] == true;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: SeasCard(
                          padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
                          onTap: () => _openItem(row),
                          child: Row(
                            children: [
                              SeasIconTile(
                                icon: type == 'feeder'
                                    ? Icons.electrical_services_rounded
                                    : type == 'consumer'
                                        ? Icons.person_rounded
                                        : Icons.hub_rounded,
                                bg: SeasColors.voltSoft,
                                fg: SeasColors.volt,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${row['title'] ?? type}',
                                      style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      '${row['subtitle'] ?? ''} · ${row['surveyed_at'] ?? ''}',
                                      style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              SeasBadge(
                                '${row['status_label'] ?? row['status'] ?? '—'}',
                                tone: badgeToneForStatus('${row['status'] ?? ''}'),
                              ),
                              if (canDelete)
                                IconButton(
                                  tooltip: 'Delete',
                                  onPressed: () => _confirmDelete(row),
                                  icon: const Icon(Icons.delete_outline_rounded, color: Color(0xFFB91C1C)),
                                )
                              else if (!canEdit)
                                const Padding(
                                  padding: EdgeInsets.only(right: 8),
                                  child: Icon(Icons.lock_outline_rounded, size: 18, color: SeasColors.ink400),
                                ),
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
