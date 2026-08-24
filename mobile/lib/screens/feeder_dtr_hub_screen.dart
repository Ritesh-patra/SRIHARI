import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../core/location_gate.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';
import 'feeder_survey_form.dart';
import 'feeder_survey_status_screen.dart';

/// Feeder → DTR hub: Feeder Survey · Continue · Status (SLD).
class FeederDtrHubScreen extends StatefulWidget {
  const FeederDtrHubScreen({
    super.key,
    this.userName,
  });

  final String? userName;

  @override
  State<FeederDtrHubScreen> createState() => _FeederDtrHubScreenState();
}

class _FeederDtrHubScreenState extends State<FeederDtrHubScreen> {
  bool locating = false;

  String get _displayName {
    final n = (widget.userName ?? '').trim();
    return n.isEmpty ? 'Field Executive' : n;
  }

  Future<void> _openNewSurvey() async {
    if (locating) return;
    setState(() => locating = true);
    try {
      final pos = await ensureLocationForFeederSurvey(context);
      if (pos == null || !mounted) return;
      final changed = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => FeederSurveyFormScreen(
            initialLat: pos.latitude,
            initialLng: pos.longitude,
            initialAccuracy: pos.accuracy,
          ),
        ),
      );
      if (changed == true && mounted) {
        Navigator.of(context).pop(true);
      }
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  void _openContinue() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ContinueFeederSurveyScreen()),
    );
  }

  void _openStatus() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const FeederSurveyStatusScreen()),
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
          'Feeder to DTR Audit',
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
              icon: Icons.account_tree_outlined,
              title: 'Feeder Survey',
              description: locating
                  ? 'Checking location…'
                  : 'Start a new feeder survey. Location is required before the form opens.',
              onTap: locating ? () {} : _openNewSurvey,
            ),
            const SizedBox(height: 12),
            _HubOptionCard(
              number: 2,
              icon: Icons.play_circle_outline_rounded,
              title: 'Continue Feeder Survey',
              description: 'Resume an incomplete feeder survey saved as draft or previously started.',
              onTap: _openContinue,
            ),
            const SizedBox(height: 12),
            _HubOptionCard(
              number: 3,
              icon: Icons.insights_outlined,
              title: 'Feeder Survey Status',
              description: 'Track your feeder surveys: Pending DTR → SLD upload → Manager SLD approval.',
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
                                'Feeder SLD path: submit feeder details → survey DTRs → Finish DTR → upload SLD for manager approval. DTR survey can also start without feeder survey.',
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
                            _StatusChip(label: 'Pending DTR', color: SeasColors.warning),
                            _StatusChip(label: 'SLD Pending', color: Color(0xFFEA580C)),
                            _StatusChip(label: 'Pending Approval', color: SeasColors.volt),
                            _StatusChip(label: 'Approved', color: SeasColors.success),
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

/// Resume draft / incomplete feeder surveys.
class ContinueFeederSurveyScreen extends StatefulWidget {
  const ContinueFeederSurveyScreen({super.key});

  @override
  State<ContinueFeederSurveyScreen> createState() => _ContinueFeederSurveyScreenState();
}

class _ContinueFeederSurveyScreenState extends State<ContinueFeederSurveyScreen> {
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
      final res = await api.get('/feeder-surveys?per_page=100');
      final raw = (res['data'] as List?) ?? [];
      items = raw.whereType<Map>().where((s) {
        final st = '${s['status'] ?? ''}'.toLowerCase();
        return st == 'draft' || st == 'rejected';
      }).toList();
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  String _labelFor(Map s) {
    final st = '${s['status'] ?? ''}'.toLowerCase();
    if (st == 'draft') return 'Pending DTR Survey';
    if (st == 'rejected') return 'Rejected';
    return '${s['display_status'] ?? s['status'] ?? 'draft'}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: SeasColors.white,
        elevation: 0,
        title: Text(
          'Continue Feeder Survey',
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : items.isEmpty
              ? SeasEmptyState(
                  title: error != null ? 'Could not load drafts' : 'No drafts',
                  subtitle: error ?? 'Start a new Feeder Survey from the hub.',
                  icon: Icons.play_circle_outline_rounded,
                )
              : RefreshIndicator(
                  color: SeasColors.volt,
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                    itemCount: items.length,
                    itemBuilder: (_, i) {
                      final s = items[i] as Map;
                      final status = _labelFor(s);
                      final name = '${s['feeder_name'] ?? 'Feeder'}';
                      final code = '${s['feeder_code'] ?? ''}'.trim();
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: SeasCard(
                          padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                          onTap: () async {
                            final id = (s['id'] as num?)?.toInt();
                            await Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => FeederSurveyFormScreen(serverId: id)),
                            );
                            _load();
                          },
                          child: Row(
                            children: [
                              SeasIconTile(icon: Icons.edit_note_rounded, bg: SeasColors.voltSoft, fg: SeasColors.volt),
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
                                      '${s['substation_name'] ?? 'Substation'}',
                                      style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              SeasBadge(status, tone: badgeToneForStatus('${s['status']}')),
                              const SizedBox(width: 4),
                              const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
