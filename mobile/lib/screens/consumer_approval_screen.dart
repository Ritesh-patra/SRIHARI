import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../core/api_config.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';

/// Manager / Super Admin: consumer survey approve / reject (remark).
class ConsumerApprovalScreen extends StatefulWidget {
  const ConsumerApprovalScreen({super.key});

  @override
  State<ConsumerApprovalScreen> createState() => _ConsumerApprovalScreenState();
}

class _ConsumerApprovalScreenState extends State<ConsumerApprovalScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> rows = [];
  final selected = <int>{};
  String status = 'pending_approval';
  String? ivrs;
  String? dtrCode;
  String? phase;
  final ivrsCtrl = TextEditingController();
  final dtrCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    ivrsCtrl.dispose();
    dtrCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final q = <String, String>{
        'status': status,
        'per_page': '100',
      };
      if (ivrsCtrl.text.trim().isNotEmpty) q['ivrs'] = ivrsCtrl.text.trim();
      if (dtrCtrl.text.trim().isNotEmpty) q['dtr_code'] = dtrCtrl.text.trim();
      if (phase != null && phase!.isNotEmpty && phase != 'all') q['phase'] = phase!;
      final qs = q.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&');
      final res = await api.get('/consumer-surveys?$qs');
      final data = (res['data'] as List?) ?? [];
      rows = data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      selected.clear();
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
      rows = [];
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _bulk(String action) async {
    if (selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select at least one survey')));
      return;
    }
    String? remark;
    if (action == 'reject') {
      remark = await _askRemark();
      if (!mounted) return;
      if (remark == null) return;
      if (remark.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Remark is required to reject')));
        return;
      }
    }
    if (action == 'delete') {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text('Delete permanently?', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
          content: Text('Delete ${selected.length} survey(s)? This cannot be undone.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
              child: const Text('Delete'),
            ),
          ],
        ),
      );
      if (!mounted) return;
      if (ok != true) return;
    }
    try {
      final res = await api.post('/consumer-surveys/bulk-action', {
        'ids': selected.toList(),
        'action': action,
        if (remark != null) 'remark': remark.trim(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${res['message'] ?? 'Done'}'), backgroundColor: SeasColors.ink950),
      );
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<String?> _askRemark() async {
    final ctrl = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Reject remark', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        content: TextField(
          controller: ctrl,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Why are you rejecting?',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, ctrl.text),
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            child: const Text('Reject'),
          ),
        ],
      ),
    );
  }

  void _openPhoto(String? url) {
    if (url == null || url.isEmpty) return;
    final abs = url.startsWith('http') ? url : ApiConfig.mediaUrl(url);
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        insetPadding: const EdgeInsets.all(16),
        child: InteractiveViewer(
          child: Image.network(abs ?? url, fit: BoxFit.contain),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    int idOf(Map r) => (r['id'] as num).toInt();
    final allSelected = rows.isNotEmpty && rows.every((r) => selected.contains(idOf(r)));

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        title: Text('Consumer Approval', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(onPressed: loading ? null : _load, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        value: status,
                        decoration: const InputDecoration(labelText: 'Status', isDense: true, border: OutlineInputBorder()),
                        items: const [
                          DropdownMenuItem(value: 'pending_approval', child: Text('Pending')),
                          DropdownMenuItem(value: 'approved', child: Text('Approved')),
                          DropdownMenuItem(value: 'rejected', child: Text('Rejected')),
                          DropdownMenuItem(value: 'all', child: Text('All')),
                        ],
                        onChanged: (v) {
                          if (v == null) return;
                          setState(() => status = v);
                          _load();
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        value: phase ?? 'all',
                        decoration: const InputDecoration(labelText: 'Phase', isDense: true, border: OutlineInputBorder()),
                        items: const [
                          DropdownMenuItem(value: 'all', child: Text('All')),
                          DropdownMenuItem(value: '1PH', child: Text('1PH')),
                          DropdownMenuItem(value: '3PH', child: Text('3PH')),
                          DropdownMenuItem(value: '3PH 4CT', child: Text('3PH 4CT')),
                        ],
                        onChanged: (v) {
                          setState(() => phase = v);
                          _load();
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: ivrsCtrl,
                        decoration: const InputDecoration(labelText: 'IVRS', isDense: true, border: OutlineInputBorder()),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: dtrCtrl,
                        decoration: const InputDecoration(labelText: 'DTR code', isDense: true, border: OutlineInputBorder()),
                      ),
                    ),
                    const SizedBox(width: 8),
                    FilledButton(
                      onPressed: _load,
                      style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950),
                      child: const Text('View'),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Checkbox(
                  value: allSelected,
                  onChanged: rows.isEmpty
                      ? null
                      : (v) {
                          setState(() {
                            selected.clear();
                            if (v == true) {
                              for (final r in rows) {
                                selected.add(idOf(r));
                              }
                            }
                          });
                        },
                ),
                const Text('Select all'),
                const Spacer(),
                TextButton(
                  onPressed: selected.isEmpty ? null : () => _bulk('approve'),
                  child: Text('Approve (${selected.length})', style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                TextButton(
                  onPressed: selected.isEmpty ? null : () => _bulk('reject'),
                  child: Text('Reject (${selected.length})', style: TextStyle(fontWeight: FontWeight.w700, color: SeasColors.volt)),
                ),
                TextButton(
                  onPressed: selected.isEmpty ? null : () => _bulk('delete'),
                  child: Text('Delete (${selected.length})', style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFFB91C1C))),
                ),
              ],
            ),
          ),
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
                : error != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(error!, textAlign: TextAlign.center, style: const TextStyle(color: SeasColors.ink400)),
                              const SizedBox(height: 12),
                              FilledButton(onPressed: _load, child: const Text('Retry')),
                            ],
                          ),
                        ),
                      )
                    : rows.isEmpty
                        ? const Center(child: Text('No consumer surveys found', style: TextStyle(color: SeasColors.ink400)))
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.separated(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                              itemCount: rows.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 10),
                              itemBuilder: (ctx, i) {
                                final r = rows[i];
                                final id = idOf(r);
                                final pending = '${r['status']}' == 'pending_approval';
                                final photo = r['meter_photo_url']?.toString();
                                return SeasCard(
                                  padding: const EdgeInsets.all(12),
                                  onTap: () => _openPhoto(photo),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Checkbox(
                                        value: selected.contains(id),
                                        onChanged: (v) {
                                          setState(() {
                                            if (v == true) {
                                              selected.add(id);
                                            } else {
                                              selected.remove(id);
                                            }
                                          });
                                        },
                                      ),
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(10),
                                        child: photo != null && photo.isNotEmpty
                                            ? Image.network(
                                                photo.startsWith('http') ? photo : (ApiConfig.mediaUrl(photo) ?? photo),
                                                width: 64,
                                                height: 64,
                                                fit: BoxFit.cover,
                                                errorBuilder: (_, __, ___) => Container(
                                                  width: 64,
                                                  height: 64,
                                                  color: SeasColors.ink100,
                                                  child: const Icon(Icons.image_not_supported_outlined),
                                                ),
                                              )
                                            : Container(
                                                width: 64,
                                                height: 64,
                                                color: SeasColors.ink100,
                                                child: const Icon(Icons.no_photography_outlined),
                                              ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              r['consumer_name']?.toString() ?? 'Consumer',
                                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              'IVRS ${r['ivrs'] ?? '—'} · MSN ${r['msn'] ?? '—'}',
                                              style: const TextStyle(fontSize: 12, color: SeasColors.ink400),
                                            ),
                                            Text(
                                              'FE: ${r['surveyor']?['name'] ?? '—'}',
                                              style: const TextStyle(fontSize: 12, color: SeasColors.ink400),
                                            ),
                                            Text(
                                              'DTR ${r['dtr_code'] ?? '—'} · ${r['feeder_name'] ?? ''}',
                                              style: const TextStyle(fontSize: 12, color: SeasColors.ink400),
                                            ),
                                            const SizedBox(height: 4),
                                            SeasBadge(
                                              '${r['status']}'.replaceAll('_', ' '),
                                              tone: pending
                                                  ? SeasBadgeTone.volt
                                                  : ('${r['status']}' == 'approved' ? SeasBadgeTone.success : SeasBadgeTone.neutral),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
