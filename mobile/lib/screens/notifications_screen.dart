import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import '../core/api_client.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';
import 'dtr_survey_form.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
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
      final res = await api.get('/notifications');
      items = (res['data'] as List?) ?? [];
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _markAll() async {
    try {
      await api.post('/notifications/read-all');
      await _load();
    } catch (_) {}
  }

  Future<void> _open(Map n) async {
    final id = n['id'];
    if (id != null && n['read_at'] == null) {
      try {
        await api.post('/notifications/$id/read');
      } catch (_) {}
    }

    final title = '${n['title'] ?? ''}';
    final subjectType = '${n['subject_type'] ?? ''}';
    final subjectId = n['subject_id'];
    final isReject = title.toLowerCase().contains('reject');
    final isSurvey = subjectType.contains('DtrSurvey') && subjectId is int;

    if (isReject && isSurvey) {
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => DtrSurveyFormScreen(serverId: subjectId),
      ));
      await _load();
      return;
    }

    await _load();
  }

  String _formatWhen(dynamic raw) {
    if (raw == null) return '';
    final dt = DateTime.tryParse(raw.toString());
    if (dt == null) return raw.toString();
    return DateFormat('d MMM yyyy, h:mm a').format(dt.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Inbox',
      title: 'Notifications',
      onBack: () => Navigator.of(context).pop(),
      actions: [
        TextButton(onPressed: _markAll, child: const Text('Mark all read')),
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
      ],
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? SeasEmptyState(title: 'Could not load', subtitle: error)
              : items.isEmpty
                  ? const SeasEmptyState(title: 'No notifications', subtitle: 'Rejects and new assignments will appear here.')
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (_, i) {
                          final n = items[i] as Map;
                          final unread = n['read_at'] == null;
                          final title = '${n['title'] ?? 'Notice'}';
                          final reject = title.toLowerCase().contains('reject');
                          return SeasCard(
                            onTap: () => _open(n),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                SeasIconTile(
                                  icon: reject ? Icons.replay_rounded : Icons.notifications_rounded,
                                  bg: unread ? SeasColors.voltSoft : SeasColors.ink100,
                                  fg: unread ? SeasColors.volt : SeasColors.ink400,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Expanded(
                                            child: Text(
                                              title,
                                              style: GoogleFonts.plusJakartaSans(
                                                fontWeight: FontWeight.w800,
                                                fontSize: 15,
                                              ),
                                            ),
                                          ),
                                          if (unread)
                                            Container(
                                              height: 8,
                                              width: 8,
                                              decoration: const BoxDecoration(color: SeasColors.volt, shape: BoxShape.circle),
                                            ),
                                        ],
                                      ),
                                      if ((n['body'] ?? '').toString().isNotEmpty) ...[
                                        const SizedBox(height: 4),
                                        Text(
                                          '${n['body']}',
                                          style: const TextStyle(color: SeasColors.ink400, fontSize: 13, height: 1.35),
                                        ),
                                      ],
                                      const SizedBox(height: 6),
                                      Text(
                                        _formatWhen(n['created_at']),
                                        style: const TextStyle(color: SeasColors.ink400, fontSize: 11),
                                      ),
                                      if (reject) ...[
                                        const SizedBox(height: 8),
                                        Text(
                                          'Tap to re-survey this DTR',
                                          style: GoogleFonts.plusJakartaSans(
                                            color: SeasColors.volt,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
