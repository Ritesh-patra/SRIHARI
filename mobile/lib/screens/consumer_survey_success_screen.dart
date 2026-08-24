import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_glass_header.dart';
import 'dtr_finish_success_screen.dart';

/// Result codes popped to Identify → Pole selection.
class ConsumerSurveyNav {
  static const nextConsumer = 'next_consumer';
  static const nextPole = 'next_pole';
  static const addPole = 'add_pole';
}

/// Shown after a single consumer survey is saved — Save & Next + Finish DTR.
class ConsumerSurveySuccessScreen extends StatefulWidget {
  const ConsumerSurveySuccessScreen({
    super.key,
    required this.dtrSurvey,
    required this.pole,
    this.message,
  });

  final Map<String, dynamic> dtrSurvey;
  final Map<String, dynamic> pole;
  final String? message;

  @override
  State<ConsumerSurveySuccessScreen> createState() => _ConsumerSurveySuccessScreenState();
}

class _ConsumerSurveySuccessScreenState extends State<ConsumerSurveySuccessScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;
  bool finishing = false;
  bool loadingPoles = true;
  bool isLastPole = false;

  int get surveyId => widget.dtrSurvey['id'] as int;
  int get poleId => (widget.pole['id'] as num?)?.toInt() ?? 0;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat(reverse: true);
    _checkLastPole();
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  Future<void> _checkLastPole() async {
    try {
      final res = await api.get('/consumer/$surveyId/poles');
      final poles = (res['poles'] as List?) ?? [];
      var last = true;
      if (poles.isNotEmpty) {
        final idx = poles.indexWhere((p) => (p['id'] as num?)?.toInt() == poleId);
        last = idx < 0 || idx >= poles.length - 1;
      }
      if (mounted) {
        setState(() {
          isLastPole = last;
          loadingPoles = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          isLastPole = true;
          loadingPoles = false;
        });
      }
    }
  }

  Future<void> _finishDtr() async {
    final stats = await _loadFinishStats();
    if (!mounted) return;

    final confirmed = await showFinishDtrConfirmDialog(
      context,
      totalPoles: stats.$1,
      totalConsumers: stats.$2,
    );
    if (confirmed != true || !mounted) return;

    setState(() => finishing = true);
    try {
      final res = await api.post('/consumer/$surveyId/finish');
      final summary = Map<String, dynamic>.from((res['summary'] as Map?) ?? {});
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => DtrFinishSuccessScreen(summary: summary),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString().replaceFirst('Exception: ', '')),
        backgroundColor: SeasColors.voltDeep,
      ));
    } finally {
      if (mounted) setState(() => finishing = false);
    }
  }

  Future<(int, int)> _loadFinishStats() async {
    try {
      final res = await api.get('/consumer/$surveyId/poles');
      final poles = (res['poles'] as List?) ?? [];
      final stats = Map<String, dynamic>.from((res['stats'] as Map?) ?? {});
      final totalPoles = (stats['total_poles'] as num?)?.toInt() ?? poles.length;
      final expected = (stats['total_houses'] as num?)?.toInt() ??
          poles.fold<int>(0, (n, p) => n + ((p['houses_connected'] as num?)?.toInt() ?? 0));
      return (totalPoles, expected);
    } catch (_) {
      return (0, 0);
    }
  }

  void _nextConsumer() => Navigator.pop(context, ConsumerSurveyNav.nextConsumer);

  void _nextOrAddPole() {
    // Always return to Pole Selection list (not MSN search / identify).
    Navigator.pop(context, isLastPole ? ConsumerSurveyNav.addPole : ConsumerSurveyNav.nextPole);
  }

  @override
  Widget build(BuildContext context) {
    final poleNo = '${widget.pole['pole_no'] ?? '—'}';
    final dtr = '${widget.dtrSurvey['dtr_code'] ?? ''} · ${widget.dtrSurvey['dtr_name'] ?? ''}'.trim();

    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Save & Next',
        subtitle: 'Consumer survey saved',
        onBack: () => Navigator.pop(context, ConsumerSurveyNav.nextConsumer),
        trailing: TextButton(
          onPressed: finishing ? null : _finishDtr,
          style: TextButton.styleFrom(
            foregroundColor: SeasColors.volt,
            padding: const EdgeInsets.symmetric(horizontal: 10),
          ),
          child: Text(
            'FINISH DTR',
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12, letterSpacing: 0.3),
          ),
        ),
      ),
      body: finishing
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : Padding(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
              child: Column(
                children: [
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        ScaleTransition(
                          scale: Tween(begin: 0.96, end: 1.04).animate(
                            CurvedAnimation(parent: _pulse, curve: Curves.easeInOut),
                          ),
                          child: Container(
                            height: 96,
                            width: 96,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: const LinearGradient(
                                colors: [Color(0xFF10B981), Color(0xFF047857)],
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF10B981).withValues(alpha: 0.3),
                                  blurRadius: 28,
                                  offset: const Offset(0, 12),
                                ),
                              ],
                            ),
                            child: const Icon(Icons.check_rounded, color: Colors.white, size: 52),
                          ),
                        ),
                        const SizedBox(height: 22),
                        Text(
                          'Consumer Survey Saved',
                          textAlign: TextAlign.center,
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 22,
                            color: SeasColors.ink950,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          widget.message ?? 'Submitted for manager approval.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: SeasColors.ink400, fontSize: 14),
                        ),
                        const SizedBox(height: 18),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: SeasColors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: SeasColors.ink100),
                          ),
                          child: Column(
                            children: [
                              _kv('DTR', dtr.isEmpty ? '—' : dtr),
                              _kv('Pole', poleNo, last: true),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: _nextConsumer,
                      style: FilledButton.styleFrom(
                        backgroundColor: SeasColors.ink950,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: Column(
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                'NEXT CONSUMER',
                                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15),
                              ),
                              const SizedBox(width: 8),
                              const Icon(Icons.arrow_forward_rounded, size: 20),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Survey next consumer on this pole',
                            style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: loadingPoles ? null : _nextOrAddPole,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.volt,
                        side: const BorderSide(color: SeasColors.volt, width: 1.5),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: loadingPoles
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2, color: SeasColors.volt),
                            )
                          : Column(
                              children: [
                                Text(
                                  isLastPole ? 'ADD POLE & VIEW POLE LIST' : 'NEXT POLE',
                                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  isLastPole
                                      ? 'Open pole selection to add or view poles'
                                      : 'Move to the next pole on this DTR',
                                  style: const TextStyle(color: SeasColors.ink400, fontSize: 11),
                                ),
                              ],
                            ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _kv(String label, String value, {bool last = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: last ? null : const Border(bottom: BorderSide(color: SeasColors.ink100)),
      ),
      child: Row(
        children: [
          Text(label, style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600, fontSize: 13)),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}

/// Confirm Finish DTR — brand red / black / white (not mock blue).
Future<bool?> showFinishDtrConfirmDialog(
  BuildContext context, {
  required int totalPoles,
  required int totalConsumers,
}) {
  return showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) {
      return Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 28),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                height: 64,
                width: 64,
                decoration: BoxDecoration(
                  color: SeasColors.voltSoft,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(color: SeasColors.volt.withValues(alpha: 0.2), blurRadius: 16, offset: const Offset(0, 6)),
                  ],
                ),
                child: const Icon(Icons.priority_high_rounded, color: SeasColors.volt, size: 34),
              ),
              const SizedBox(height: 14),
              Text(
                'Finish DTR Survey?',
                textAlign: TextAlign.center,
                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.ink950),
              ),
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                decoration: BoxDecoration(
                  color: SeasColors.canvasSoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  children: [
                    _ConfirmStatRow(icon: Icons.flag_rounded, label: 'Total Poles Surveyed', value: '$totalPoles'),
                    const Divider(height: 1),
                    _ConfirmStatRow(icon: Icons.people_alt_rounded, label: 'Total Consumers (expected)', value: '$totalConsumers'),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'Are you sure you want to finish this DTR survey?',
                textAlign: TextAlign.center,
                style: TextStyle(color: SeasColors.ink400, fontSize: 13, height: 1.35),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.ink950,
                        side: const BorderSide(color: SeasColors.ink900),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: Text('NO', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      onPressed: () => Navigator.pop(ctx, true),
                      style: FilledButton.styleFrom(
                        backgroundColor: SeasColors.volt,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: Text('YES, FINISH', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    },
  );
}

class _ConfirmStatRow extends StatelessWidget {
  const _ConfirmStatRow({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          Icon(icon, size: 18, color: SeasColors.volt),
          const SizedBox(width: 10),
          Expanded(child: Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600))),
          Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: SeasColors.volt)),
        ],
      ),
    );
  }
}
