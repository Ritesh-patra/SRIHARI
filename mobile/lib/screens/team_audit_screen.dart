import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../core/file_download.dart';
import '../core/seas_date_range.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';

/// Manager / Super Admin: which FE surveyed which feeder / DTR / consumers.
class TeamAuditScreen extends StatefulWidget {
  const TeamAuditScreen({super.key});

  @override
  State<TeamAuditScreen> createState() => _TeamAuditScreenState();
}

class _TeamAuditScreenState extends State<TeamAuditScreen> {
  bool loading = true;
  bool exporting = false;
  String? error;
  List<Map<String, dynamic>> rows = [];
  Map totals = {};
  late DateTime from;
  late DateTime to;

  @override
  void initState() {
    super.initState();
    to = DateTime.now();
    from = to.subtract(const Duration(days: 30));
    _load();
  }

  String _fmt(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await api.get('/team-audit?from=${_fmt(from)}&to=${_fmt(to)}');
      totals = Map<String, dynamic>.from((res['totals'] as Map?) ?? {});
      rows = ((res['data'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
      rows = [];
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _pickRange() async {
    final picked = await pickSeasDateRange(
      context,
      initial: DateTimeRange(start: from, end: to),
    );
    if (picked == null) return;
    setState(() {
      from = picked.start;
      to = picked.end;
    });
    await _load();
  }

  Future<void> _download({int? userId}) async {
    setState(() => exporting = true);
    try {
      var path = '/team-audit/export?from=${_fmt(from)}&to=${_fmt(to)}';
      if (userId != null) path += '&user_id=$userId';
      final file = await api.getBytes(path);
      final saved = await saveDownloadBytes(file.bytes, file.filename);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(saved == null ? 'Excel downloaded' : 'Excel saved: $saved'),
          backgroundColor: SeasColors.ink950,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => exporting = false);
    }
  }

  void _openDetail(Map<String, dynamic> row) {
    final id = (row['user'] is Map) ? (row['user']['id'] as num?)?.toInt() : null;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _TeamAuditDetail(
          userId: id,
          seedRow: row,
          from: _fmt(from),
          to: _fmt(to),
          onDownload: () => _download(userId: id),
        ),
      ),
    );
  }

  Widget _glassChip(String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.75)),
        boxShadow: const [BoxShadow(color: Color(0x14000000), blurRadius: 12, offset: Offset(0, 6))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18)),
          Text(label, style: const TextStyle(fontSize: 10, color: SeasColors.ink400, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        title: Text('Team Activity', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(onPressed: _pickRange, icon: const Icon(Icons.date_range_rounded)),
          IconButton(
            onPressed: exporting || loading ? null : () => _download(),
            icon: exporting
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.download_rounded),
            tooltip: 'Download Excel',
          ),
          IconButton(onPressed: loading ? null : _load, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : error != null
              ? Center(child: Text(error!, textAlign: TextAlign.center))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    children: [
                      Text(
                        '${_fmt(from)} → ${_fmt(to)}',
                        style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _glassChip('People', '${totals['people'] ?? rows.length}'),
                          _glassChip('Feeders', '${totals['feeder'] ?? 0}'),
                          _glassChip('DTRs', '${totals['dtr'] ?? 0}'),
                          _glassChip('Consumers', '${totals['consumer'] ?? 0}'),
                        ],
                      ),
                      const SizedBox(height: 16),
                      Text('Per Field Executive', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
                      const SizedBox(height: 8),
                      if (rows.isEmpty)
                        const SeasEmptyState(title: 'No activity', subtitle: 'No surveys in this date range')
                      else
                        ...rows.map((r) {
                          final user = Map<String, dynamic>.from((r['user'] as Map?) ?? {});
                          final dtrs = ((r['dtr_names'] as List?) ?? []).cast<dynamic>().map((e) => '$e').toList();
                          final feeders = ((r['feeder_names'] as List?) ?? []).cast<dynamic>().map((e) => '$e').toList();
                          final consumerTotal = r['consumer_total'] ?? 0;
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: SeasCard(
                              onTap: () => _openDetail(r),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(user['name']?.toString() ?? 'FE', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
                                  Text('${user['email'] ?? ''}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                                  const SizedBox(height: 8),
                                  Text(
                                    'Feeder ${r['feeder_total'] ?? 0} · DTR ${r['dtr_total'] ?? 0} · Consumer $consumerTotal',
                                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                                  ),
                                  if (feeders.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Text('Feeders surveyed', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12, color: SeasColors.volt)),
                                    const SizedBox(height: 4),
                                    ...feeders.take(3).map((n) => Text('• $n', style: const TextStyle(fontSize: 12))),
                                    if (feeders.length > 3)
                                      Text('+${feeders.length - 3} more · tap for full list', style: const TextStyle(fontSize: 11, color: SeasColors.ink400)),
                                  ],
                                  if (dtrs.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Text('DTRs surveyed', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12, color: SeasColors.volt)),
                                    const SizedBox(height: 4),
                                    ...dtrs.take(3).map((n) => Text('• $n', style: const TextStyle(fontSize: 12))),
                                    if (dtrs.length > 3)
                                      Text('+${dtrs.length - 3} more · tap for full list', style: const TextStyle(fontSize: 11, color: SeasColors.ink400)),
                                  ],
                                  if (consumerTotal is num && consumerTotal > 0) ...[
                                    const SizedBox(height: 8),
                                    Text(
                                      'Consumers: $consumerTotal surveyed · tap for full names / IVRS',
                                      style: const TextStyle(fontSize: 12, color: SeasColors.ink400),
                                    ),
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

class _TeamAuditDetail extends StatefulWidget {
  const _TeamAuditDetail({
    required this.userId,
    required this.seedRow,
    required this.from,
    required this.to,
    required this.onDownload,
  });

  final int? userId;
  final Map<String, dynamic> seedRow;
  final String from;
  final String to;
  final Future<void> Function() onDownload;

  @override
  State<_TeamAuditDetail> createState() => _TeamAuditDetailState();
}

class _TeamAuditDetailState extends State<_TeamAuditDetail> {
  late Map<String, dynamic> row;
  bool loading = false;
  String? error;

  @override
  void initState() {
    super.initState();
    row = Map<String, dynamic>.from(widget.seedRow);
    _refresh();
  }

  Future<void> _refresh() async {
    final id = widget.userId;
    if (id == null) return;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await api.get('/team-audit/$id?from=${widget.from}&to=${widget.to}');
      final summary = Map<String, dynamic>.from((res['summary'] as Map?) ?? res);
      if (summary['user'] == null && res['surveyor'] is Map) {
        summary['user'] = res['surveyor'];
      }
      if (mounted) setState(() => row = summary);
    } catch (e) {
      if (mounted) setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String _label(Map d) {
    final raw = '${d['status'] ?? ''}';
    return '${d['status_label'] ?? _human(raw)}';
  }

  String _human(String status) {
    switch (status) {
      case 'pending_approval':
        return 'Pending Approval';
      case 'sld_pending':
        return 'SLD Verification Pending';
      case 'draft':
        return 'Draft / Pending DTR';
      case 'approved':
        return 'Approved';
      case 'rejected':
        return 'Rejected';
      case 'completed':
        return 'Completed';
      case 'saved':
        return 'Saved';
      case 'not_accessible':
        return 'Not Accessible';
      default:
        return status.isEmpty ? '—' : status.replaceAll('_', ' ');
    }
  }

  Widget _sectionHeader(String number, String title, int count) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10, top: 4),
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(
        color: SeasColors.ink950,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
            height: 28,
            width: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: SeasColors.volt,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Text(
              number,
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: Colors.white, fontSize: 13),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              title,
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15, color: Colors.white),
            ),
          ),
          Text(
            '$count',
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.volt),
          ),
        ],
      ),
    );
  }

  Widget _countTile(String label, int count, Color accent) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: SeasColors.ink100),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$count', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 20, color: accent)),
            const SizedBox(height: 2),
            Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = Map<String, dynamic>.from((row['user'] as Map?) ?? {});
    final dtrs = ((row['dtrs'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    final feeders = ((row['feeders'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    final consumers = ((row['consumers'] as List?) ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    final feederTotal = (row['feeder_total'] as num?)?.toInt() ?? feeders.length;
    final dtrTotal = (row['dtr_total'] as num?)?.toInt() ?? dtrs.length;
    final consumerTotal = (row['consumer_total'] as num?)?.toInt() ?? consumers.length;
    final name = user['name']?.toString() ?? 'Surveyor';

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        title: Text(name, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(onPressed: loading ? null : _refresh, icon: const Icon(Icons.refresh_rounded)),
          IconButton(onPressed: widget.onDownload, icon: const Icon(Icons.download_rounded), tooltip: 'Download Excel'),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('$name · Team Activity', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18)),
          const SizedBox(height: 4),
          Text('${widget.from} → ${widget.to}', style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600)),
          if (loading) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(color: SeasColors.volt, minHeight: 2),
          ],
          if (error != null) ...[
            const SizedBox(height: 8),
            Text(error!, style: const TextStyle(color: SeasColors.volt, fontSize: 12)),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              _countTile('Feeders', feederTotal, SeasColors.ink950),
              const SizedBox(width: 8),
              _countTile('DTRs', dtrTotal, SeasColors.volt),
              const SizedBox(width: 8),
              _countTile('Consumers', consumerTotal, SeasColors.success),
            ],
          ),
          const SizedBox(height: 18),
          _sectionHeader('1', 'Feeders surveyed', feederTotal),
          if (feeders.isEmpty)
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: Text('No feeders surveyed in this date range.', style: TextStyle(color: SeasColors.ink400)),
            )
          else
            ...feeders.map((f) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: SeasCard(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${f['feeder_name'] ?? 'Feeder'}',
                          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                        ),
                        Text('Code: ${f['feeder_code'] ?? '—'}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                        const SizedBox(height: 4),
                        Text('${_label(f)} · ${f['surveyed_at'] ?? ''}', style: const TextStyle(fontSize: 11, color: SeasColors.ink400)),
                      ],
                    ),
                  ),
                )),
          const SizedBox(height: 10),
          _sectionHeader('2', 'DTRs surveyed', dtrTotal),
          if (dtrs.isEmpty)
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: Text('No DTRs surveyed in this date range.', style: TextStyle(color: SeasColors.ink400)),
            )
          else
            ...dtrs.map((d) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: SeasCard(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${d['dtr_name'] ?? 'DTR'}',
                          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                        ),
                        Text('Code: ${d['dtr_code'] ?? '—'}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                        Text(
                          'Feeder: ${d['feeder_name'] ?? '—'}'
                          '${(d['feeder_code'] ?? '').toString().isNotEmpty ? ' (${d['feeder_code']})' : ''}',
                          style: const TextStyle(fontSize: 12, color: SeasColors.ink400),
                        ),
                        const SizedBox(height: 4),
                        Text('${_label(d)} · ${d['surveyed_at'] ?? ''}', style: const TextStyle(fontSize: 11, color: SeasColors.ink400)),
                      ],
                    ),
                  ),
                )),
          const SizedBox(height: 10),
          _sectionHeader('3', 'Consumers surveyed', consumerTotal),
          if (consumers.isEmpty)
            const Text('No consumers surveyed in this date range.', style: TextStyle(color: SeasColors.ink400))
          else ...[
            if (consumerTotal > consumers.length)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  'Showing full list: ${consumers.length} of $consumerTotal',
                  style: const TextStyle(fontSize: 12, color: SeasColors.ink400),
                ),
              ),
            ...consumers.map((c) {
              final dtrBit = [
                if ('${c['dtr_name'] ?? ''}'.trim().isNotEmpty) '${c['dtr_name']}',
                if ('${c['dtr_code'] ?? ''}'.trim().isNotEmpty) '(${c['dtr_code']})',
              ].join(' ');
              final feederBit = [
                if ('${c['feeder_name'] ?? ''}'.trim().isNotEmpty) '${c['feeder_name']}',
                if ('${c['feeder_code'] ?? ''}'.trim().isNotEmpty) '(${c['feeder_code']})',
              ].join(' ');
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: SeasCard(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${c['consumer_name'] ?? 'Consumer'}',
                        style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                      ),
                      Text('IVRS: ${c['ivrs'] ?? '—'}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                      if ((c['msn'] ?? '').toString().trim().isNotEmpty)
                        Text('MSN: ${c['msn']}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                      Text('DTR: ${dtrBit.trim().isEmpty ? '—' : dtrBit}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                      Text('Feeder: ${feederBit.trim().isEmpty ? '—' : feederBit}', style: const TextStyle(fontSize: 12, color: SeasColors.ink400)),
                      const SizedBox(height: 4),
                      Text('${_label(c)} · ${c['surveyed_at'] ?? ''}', style: const TextStyle(fontSize: 11, color: SeasColors.ink400)),
                    ],
                  ),
                ),
              );
            }),
          ],
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}
