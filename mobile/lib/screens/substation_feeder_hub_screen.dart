import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';
import 'feeder_dtr_hub_screen.dart';
import 'feeder_survey_form.dart';
import 'survey_category_status_screen.dart';

/// Options hub for Substation → Feeder (SEAS brand — not mock blue/green/purple).
class SubstationFeederHubScreen extends StatelessWidget {
  const SubstationFeederHubScreen({super.key, this.userName});

  final String? userName;

  String get _displayName {
    final n = (userName ?? '').trim();
    return n.isEmpty ? 'Field Executive' : n;
  }

  Future<void> _openNew(BuildContext context) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const FeederSurveyFormScreen()),
    );
    if (changed == true && context.mounted) {
      Navigator.of(context).pop(true);
    }
  }

  void _openStatus(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SurveyCategoryStatusScreen(
          title: 'Survey Progress',
          userName: userName,
          popWithResult: false,
        ),
      ),
    );
  }

  Future<void> _openDtrHub(BuildContext context) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => FeederDtrHubScreen(userName: userName)),
    );
    if (changed == true && context.mounted) {
      Navigator.of(context).pop(true);
    }
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
          'Substation → Feeder',
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
            _SfOptionCard(
              number: 1,
              icon: Icons.account_tree_outlined,
              title: 'Feeder Survey',
              description: 'Start a new feeder survey. Verify feeder details and meter information.',
              onTap: () => _openNew(context),
            ),
            const SizedBox(height: 12),
            _SfOptionCard(
              number: 2,
              icon: Icons.insights_outlined,
              title: 'Survey Status',
              description: 'View category-wise progress: Feeder Surveys, DTR Surveys and Consumer Surveys.',
              onTap: () => _openStatus(context),
              showStatusChips: true,
            ),
            const SizedBox(height: 12),
            _SfOptionCard(
              number: 3,
              icon: Icons.bolt_rounded,
              title: 'Continue to DTR Survey',
              description: 'Open Feeder → DTR hub to start or continue DTR surveys when ready.',
              onTap: () => _openDtrHub(context),
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
                        style: GoogleFonts.plusJakartaSans(fontSize: 13, height: 1.45, color: SeasColors.ink800),
                        children: [
                          TextSpan(
                            text: 'Note: ',
                            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: SeasColors.voltDeep),
                          ),
                          const TextSpan(
                            text: 'Complete all required steps carefully. Submitted surveys cannot be edited.',
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

class _SfOptionCard extends StatelessWidget {
  const _SfOptionCard({
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
            padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 48,
                  width: 48,
                  decoration: BoxDecoration(
                    color: SeasColors.voltSoft,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: SeasColors.volt, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: SeasColors.ink950,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              '$number',
                              style: GoogleFonts.plusJakartaSans(
                                fontWeight: FontWeight.w800,
                                fontSize: 11,
                                color: Colors.white,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              title,
                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        description,
                        style: const TextStyle(color: SeasColors.ink400, fontSize: 12.5, height: 1.35),
                      ),
                      if (showStatusChips) ...[
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: const [
                            _StatusChip(label: 'Feeder', color: SeasColors.volt),
                            _StatusChip(label: 'DTR', color: SeasColors.ink800),
                            _StatusChip(label: 'Consumer', color: Color(0xFF059669)),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 4),
                const Icon(Icons.chevron_right_rounded, color: SeasColors.volt),
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
