import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';
import 'dtr_survey_form.dart';

/// DTR → Consumer hub: Consumer Survey · DTR Survey · DTR Survey Status.
class DtrConsumerHubScreen extends StatefulWidget {
  const DtrConsumerHubScreen({
    super.key,
    this.userName,
    this.onOpenConsumer,
  });

  final String? userName;
  final VoidCallback? onOpenConsumer;

  @override
  State<DtrConsumerHubScreen> createState() => _DtrConsumerHubScreenState();
}

class _DtrConsumerHubScreenState extends State<DtrConsumerHubScreen> {
  String get _displayName {
    final n = (widget.userName ?? '').trim();
    return n.isEmpty ? 'Field Executive' : n;
  }

  void _openConsumer() {
    if (widget.onOpenConsumer != null) {
      widget.onOpenConsumer!();
      return;
    }
    Navigator.of(context).pop('consumer');
  }

  Future<void> _openDtrSurvey() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => const DtrSurveyFormScreen(autofetch: false),
      ),
    );
    if (changed == true && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  void _openStatus() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const DtrSurveyStatusScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: SeasColors.white,
        elevation: 0,
        centerTitle: false,
        title: Text(
          'DTR to Consumer Audit',
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
          children: [
            Text(
              'Welcome, $_displayName',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 22,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.4,
                color: SeasColors.ink950,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Please select an option to continue.',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: SeasColors.ink400,
              ),
            ),
            const SizedBox(height: 22),
            _HubOptionCard(
              number: 1,
              icon: Icons.groups_outlined,
              title: 'Consumer Survey',
              description:
                  'Survey consumers on DTRs you already submitted (unlocks after DTR submit) — verify details, capture meter photos and complete the consumer audit.',
              onTap: _openConsumer,
            ),
            const SizedBox(height: 12),
            _HubOptionCard(
              number: 2,
              icon: Icons.electrical_services_rounded,
              title: 'DTR Survey',
              description:
                  'Verify DTR details, Smart Meter information, capture required photos and submit. No feeder survey required — Consumer Survey unlocks after submit.',
              onTap: _openDtrSurvey,
            ),
            const SizedBox(height: 12),
            _HubOptionCard(
              number: 3,
              icon: Icons.insights_outlined,
              title: 'DTR Survey Status',
              description:
                  'View DTRs you already surveyed — drafts, submitted and rejected — and continue Consumer Survey when ready.',
              onTap: _openStatus,
              showStatusChips: true,
            ),
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.fromLTRB(14, 14, 16, 14),
              decoration: BoxDecoration(
                color: SeasColors.voltSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: SeasColors.volt.withValues(alpha: 0.18)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    height: 28,
                    width: 28,
                    decoration: BoxDecoration(
                      color: SeasColors.white,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: const Icon(Icons.info_outline_rounded, size: 16, color: SeasColors.volt),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: RichText(
                      text: TextSpan(
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 13,
                          height: 1.45,
                          color: SeasColors.ink800,
                        ),
                        children: [
                          TextSpan(
                            text: 'Note: ',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w800,
                              color: SeasColors.voltDeep,
                            ),
                          ),
                          const TextSpan(
                            text:
                                'Complete Consumer Survey on DTRs you already submitted, or start a standalone DTR Survey here. Consumer unlocks after DTR submit — no manager approval. Track status under DTR Survey Status.',
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HubOptionCard extends StatelessWidget {
  const _HubOptionCard({
    required this.number,
    required this.icon,
    required this.title,
    required this.description,
    required this.onTap,
    this.showStatusChips = false,
  });

  final int number;
  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;
  final bool showStatusChips;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: SeasColors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          decoration: BoxDecoration(
            color: SeasColors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0x14000000)),
            boxShadow: SeasShadows.card,
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 16, 10, 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Container(
                  height: 56,
                  width: 56,
                  decoration: BoxDecoration(
                    color: SeasColors.voltSoft,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(icon, color: SeasColors.volt, size: 28),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            height: 22,
                            width: 22,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: SeasColors.ink950,
                              borderRadius: BorderRadius.circular(7),
                            ),
                            child: Text(
                              '$number',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: SeasColors.white,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              title,
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: SeasColors.ink950,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        description,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 12.5,
                          height: 1.4,
                          fontWeight: FontWeight.w500,
                          color: SeasColors.ink400,
                        ),
                      ),
                      if (showStatusChips) ...[
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: const [
                            _StatusChip(label: 'Pending', color: SeasColors.warning),
                            _StatusChip(label: 'Approved', color: SeasColors.success),
                            _StatusChip(label: 'Rejected', color: SeasColors.volt),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 4),
                const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400, size: 26),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Text(
        label,
        style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 10, color: color),
      ),
    );
  }
}

/// List submitted DTR surveys with Pending / Approved / Rejected filters.
class DtrSurveyStatusScreen extends StatefulWidget {
  const DtrSurveyStatusScreen({super.key});

  @override
  State<DtrSurveyStatusScreen> createState() => _DtrSurveyStatusScreenState();
}

class _DtrSurveyStatusScreenState extends State<DtrSurveyStatusScreen> {
  List items = [];
  bool loading = true;
  String? error;
  String filter = 'all';

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
      final res = await api.get('/surveys?per_page=100');
      items = (res['data'] as List?) ?? [];
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String _statusOf(Map s) => '${s['status'] ?? ''}'.toLowerCase();

  String _labelFor(Map s) {
    final st = _statusOf(s);
    return switch (st) {
      'pending_approval' => 'Submitted',
      'approved' => 'Ready for Consumer',
      'rejected' => 'Rejected',
      'draft' => 'Draft',
      'completed' => 'Consumer Done',
      _ => st.isEmpty ? '—' : st.replaceAll('_', ' '),
    };
  }

  bool _matches(Map s) {
    final st = _statusOf(s);
    return switch (filter) {
      'approved' => st == 'approved' || st == 'pending_approval',
      'rejected' => st == 'rejected',
      'draft' => st == 'draft',
      _ => true,
    };
  }

  int _count(String key) {
    if (key == 'all') return items.length;
    return items.whereType<Map>().where((s) {
      final st = _statusOf(s);
      return switch (key) {
        'approved' => st == 'approved' || st == 'pending_approval',
        'rejected' => st == 'rejected',
        'draft' => st == 'draft',
        _ => false,
      };
    }).length;
  }

  List get _filtered => items.whereType<Map>().where(_matches).toList();

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
          'DTR Survey Status',
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
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _FilterChip(
                        label: 'All (${_count('all')})',
                        selected: filter == 'all',
                        color: SeasColors.ink800,
                        onTap: () => setState(() => filter = 'all'),
                      ),
                      _FilterChip(
                        label: 'Draft (${_count('draft')})',
                        selected: filter == 'draft',
                        color: SeasColors.warning,
                        onTap: () => setState(() => filter = 'draft'),
                      ),
                      _FilterChip(
                        label: 'Ready (${_count('approved')})',
                        selected: filter == 'approved',
                        color: SeasColors.success,
                        onTap: () => setState(() => filter = 'approved'),
                      ),
                      _FilterChip(
                        label: 'Rejected (${_count('rejected')})',
                        selected: filter == 'rejected',
                        color: SeasColors.volt,
                        onTap: () => setState(() => filter = 'rejected'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (list.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 48),
                      child: SeasEmptyState(
                        title: 'No DTR surveys',
                        subtitle: 'Submit a DTR Survey from the hub — your surveyed DTRs appear here.',
                        icon: Icons.insights_outlined,
                      ),
                    )
                  else
                    ...list.map((raw) {
                      final s = raw as Map;
                      final status = _statusOf(s);
                      final label = _labelFor(s);
                      final canEdit = status == 'draft' || status == 'rejected';
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: SeasCard(
                          padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                          onTap: canEdit
                              ? () async {
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => DtrSurveyFormScreen(
                                        serverId: (s['id'] as num?)?.toInt(),
                                        autofetch: false,
                                      ),
                                    ),
                                  );
                                  _load();
                                }
                              : null,
                          child: Row(
                            children: [
                              SeasIconTile(
                                icon: Icons.electrical_services_rounded,
                                bg: SeasColors.voltSoft,
                                fg: SeasColors.volt,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${s['dtr_name'] ?? 'DTR'}',
                                      style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      '${s['feeder_name'] ?? 'Feeder'}${s['entry_source'] == 'standalone' ? ' · Standalone' : ''}',
                                      style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              SeasBadge(label, tone: badgeToneForStatus(status)),
                              if (canEdit) ...[
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

class _FilterChip extends StatelessWidget {
  const _FilterChip({
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
      color: selected ? color.withValues(alpha: 0.12) : SeasColors.white,
      borderRadius: BorderRadius.circular(99),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(99),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(99),
            border: Border.all(color: selected ? color.withValues(alpha: 0.45) : SeasColors.ink100),
          ),
          child: Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              fontWeight: FontWeight.w700,
              fontSize: 12,
              color: selected ? color : SeasColors.ink400,
            ),
          ),
        ),
      ),
    );
  }
}
