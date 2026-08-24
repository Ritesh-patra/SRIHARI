import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_glass_header.dart';

/// Premium DTR completion success — shown after Finish DTR confirms.
class DtrFinishSuccessScreen extends StatefulWidget {
  const DtrFinishSuccessScreen({
    super.key,
    required this.summary,
  });

  /// Keys: dtr_label, total_poles, total_consumers, survey_date (or survey_date_iso)
  final Map<String, dynamic> summary;

  @override
  State<DtrFinishSuccessScreen> createState() => _DtrFinishSuccessScreenState();
}

class _DtrFinishSuccessScreenState extends State<DtrFinishSuccessScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _scale;
  late final Animation<double> _fade;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _scale = CurvedAnimation(parent: _ctrl, curve: const Interval(0, 0.55, curve: Curves.elasticOut));
    _fade = CurvedAnimation(parent: _ctrl, curve: const Interval(0.25, 1, curve: Curves.easeOut));
    _ctrl.forward();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  String get _dtrLabel {
    final label = widget.summary['dtr_label']?.toString().trim();
    if (label != null && label.isNotEmpty && label != '-') return label;
    final code = widget.summary['dtr_code']?.toString() ?? '';
    final name = widget.summary['dtr_name']?.toString() ?? '';
    if (code.isNotEmpty && name.isNotEmpty) return '$code - $name';
    return code.isNotEmpty ? code : (name.isNotEmpty ? name : '—');
  }

  String get _surveyDate {
    final formatted = widget.summary['survey_date']?.toString();
    if (formatted != null && formatted.isNotEmpty) return formatted;
    final iso = widget.summary['survey_date_iso']?.toString();
    if (iso != null && iso.isNotEmpty) {
      final dt = DateTime.tryParse(iso);
      if (dt != null) return DateFormat('d MMM yyyy').format(dt.toLocal());
    }
    return DateFormat('d MMM yyyy').format(DateTime.now());
  }

  void _goDashboard() {
    Navigator.of(context).popUntil((r) => r.isFirst);
  }

  void _selectNextDtr() {
    // Clear field stack back to home Consumer tab (approved DTR list).
    Navigator.of(context).popUntil((r) => r.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    final poles = widget.summary['total_poles'] ?? 0;
    final consumers = widget.summary['total_consumers'] ?? 0;

    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Completed',
        subtitle: 'DTR consumer survey',
        onBack: _goDashboard,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    children: [
                      const SizedBox(height: 24),
                      FadeTransition(
                        opacity: _fade,
                        child: ScaleTransition(
                          scale: _scale,
                          child: const _SuccessBadge(),
                        ),
                      ),
                      const SizedBox(height: 22),
                      FadeTransition(
                        opacity: _fade,
                        child: Text(
                          'DTR Survey Completed Successfully!',
                          textAlign: TextAlign.center,
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 22,
                            height: 1.25,
                            color: SeasColors.success,
                            letterSpacing: -0.3,
                          ),
                        ),
                      ),
                      const SizedBox(height: 22),
                      FadeTransition(
                        opacity: _fade,
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                          decoration: BoxDecoration(
                            color: SeasColors.white,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(color: SeasColors.ink100),
                            boxShadow: SeasShadows.card,
                          ),
                          child: Column(
                            children: [
                              _SummaryRow(
                                icon: Icons.factory_outlined,
                                label: 'DTR',
                                value: _dtrLabel,
                              ),
                              _SummaryRow(
                                icon: Icons.cell_tower_rounded,
                                label: 'Total Poles',
                                value: '$poles',
                              ),
                              _SummaryRow(
                                icon: Icons.groups_rounded,
                                label: 'Total Consumers',
                                value: '$consumers',
                              ),
                              _SummaryRow(
                                icon: Icons.calendar_today_rounded,
                                label: 'Survey Date',
                                value: _surveyDate,
                                last: true,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _goDashboard,
                      icon: const Icon(Icons.dashboard_outlined, size: 18),
                      label: const Text('Go to Dashboard'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.ink950,
                        side: const BorderSide(color: SeasColors.ink200, width: 1.4),
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _selectNextDtr,
                      icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                      label: const Text('Select Next DTR'),
                      style: FilledButton.styleFrom(
                        backgroundColor: SeasColors.volt,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SuccessBadge extends StatelessWidget {
  const _SuccessBadge();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 120,
      width: 120,
      child: Stack(
        alignment: Alignment.center,
        clipBehavior: Clip.none,
        children: [
          ..._confetti(),
          Container(
            height: 88,
            width: 88,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFF10B981), Color(0xFF047857)],
              ),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF10B981).withValues(alpha: 0.35),
                  blurRadius: 24,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: const Icon(Icons.check_rounded, color: Colors.white, size: 48),
          ),
        ],
      ),
    );
  }

  List<Widget> _confetti() {
    const pieces = <(double, double, Color, double)>[
      (-48, -28, SeasColors.volt, 0.35),
      (42, -34, Color(0xFF3B82F6), 0.55),
      (-38, 30, Color(0xFFF59E0B), 0.2),
      (46, 24, Color(0xFF8B5CF6), 0.7),
      (-10, -52, Color(0xFF10B981), 0.1),
      (8, 48, SeasColors.voltGlow, 0.9),
      (-54, 4, Color(0xFF6366F1), 0.4),
      (54, -6, Color(0xFFF97316), 0.15),
    ];
    return [
      for (final p in pieces)
        Positioned(
          left: 60 + p.$1,
          top: 60 + p.$2,
          child: Transform.rotate(
            angle: p.$4 * math.pi,
            child: Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: p.$3,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
        ),
    ];
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.icon,
    required this.label,
    required this.value,
    this.last = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: BoxDecoration(
        border: last ? null : const Border(bottom: BorderSide(color: SeasColors.ink100)),
      ),
      child: Row(
        children: [
          Container(
            height: 36,
            width: 36,
            decoration: BoxDecoration(
              color: SeasColors.voltSoft,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 18, color: SeasColors.volt),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14, color: SeasColors.ink950),
            ),
          ),
        ],
      ),
    );
  }
}
