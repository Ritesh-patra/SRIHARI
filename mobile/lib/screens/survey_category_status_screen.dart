import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../theme/seas_colors.dart';
import 'feeder_dtr_hub_screen.dart';

/// Category-wise survey progress (Feeder / DTR / Consumer) after feeder submit or from hubs.
class SurveyCategoryStatusScreen extends StatefulWidget {
  const SurveyCategoryStatusScreen({
    super.key,
    this.title = 'Survey Progress',
    this.successMessage,
    this.userName,
    this.popWithResult = true,
  });

  final String title;
  final String? successMessage;
  final String? userName;
  final bool popWithResult;

  @override
  State<SurveyCategoryStatusScreen> createState() => _SurveyCategoryStatusScreenState();
}

class _SurveyCategoryStatusScreenState extends State<SurveyCategoryStatusScreen> {
  bool loading = true;
  String? error;
  Map stats = {};

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
      final res = await api.get('/dashboard');
      stats = (res['stats'] as Map?) ?? {};
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  int _n(Object? v) => int.tryParse('$v') ?? 0;

  Future<void> _openDtrHub() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => FeederDtrHubScreen(userName: widget.userName)),
    );
    if (changed == true && mounted) {
      Navigator.of(context).pop(true);
    } else {
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final pending = _n(stats['pending']);
    final rejected = _n(stats['rejected']);
    final approved = _n(stats['approved']);
    final completed = _n(stats['completed']);

    final dtrDone = approved + completed;
    final dtrTotal = pending + rejected + approved + completed;
    final consumerDone = completed;
    final consumerTotal = approved + completed;

    final feederDone = _n(stats['feeder_submitted']);
    final feederTotal = _n(stats['feeder_total']);
    // Fallback if older API without feeder_* keys
    final feederDoneSafe = feederTotal == 0 && feederDone == 0 ? _n(stats['feeder_approved']) : feederDone;
    final feederTotalSafe = feederTotal == 0 && feederDone == 0
        ? _n(stats['feeder_pending']) + _n(stats['feeder_rejected']) + _n(stats['feeder_approved'])
        : feederTotal;

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: AppBar(
        backgroundColor: SeasColors.ink950,
        foregroundColor: SeasColors.white,
        elevation: 0,
        title: Text(
          widget.title,
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 17),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => Navigator.of(context).pop(widget.popWithResult),
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
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
                children: [
                  if (widget.successMessage != null) ...[
                    Container(
                      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                      decoration: BoxDecoration(
                        color: SeasColors.successSoft,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: SeasColors.success.withValues(alpha: 0.25)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.check_circle_outline_rounded, color: SeasColors.success, size: 22),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              widget.successMessage!,
                              style: GoogleFonts.plusJakartaSans(
                                fontWeight: FontWeight.w700,
                                fontSize: 13.5,
                                height: 1.4,
                                color: SeasColors.ink800,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                  ],
                  Text(
                    'Your survey status',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.3,
                      color: SeasColors.ink950,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Progress by category — Feeder, DTR and Consumer surveys.',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w500,
                      color: SeasColors.ink400,
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontSize: 13)),
                    ),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                    decoration: BoxDecoration(
                      color: SeasColors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: SeasColors.ink100),
                      boxShadow: SeasShadows.card,
                    ),
                    child: Column(
                      children: [
                        _CategoryProgressRow(
                          label: 'Feeder Surveys',
                          done: feederDoneSafe,
                          total: feederTotalSafe == 0 ? feederDoneSafe : feederTotalSafe,
                          accent: SeasColors.volt,
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          child: Divider(height: 1, thickness: 1, color: SeasColors.ink100.withValues(alpha: 0.9)),
                        ),
                        _CategoryProgressRow(
                          label: 'DTR Surveys',
                          done: dtrDone,
                          total: dtrTotal,
                          accent: SeasColors.ink950,
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          child: Divider(height: 1, thickness: 1, color: SeasColors.ink100.withValues(alpha: 0.9)),
                        ),
                        _CategoryProgressRow(
                          label: 'Consumer Surveys',
                          done: consumerDone,
                          total: consumerTotal,
                          accent: SeasColors.voltDeep,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 22),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: _openDtrHub,
                      style: FilledButton.styleFrom(
                        backgroundColor: SeasColors.ink950,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: Text(
                        'Continue to Feeder → DTR',
                        style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).pop(widget.popWithResult),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.ink950,
                        side: const BorderSide(color: SeasColors.ink900, width: 1.2),
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: Text(
                        'Back to Home',
                        style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

class _CategoryProgressRow extends StatelessWidget {
  const _CategoryProgressRow({
    required this.label,
    required this.done,
    required this.total,
    required this.accent,
  });

  final String label;
  final int done;
  final int total;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final ratio = total <= 0 ? 0.0 : (done / total).clamp(0.0, 1.0);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                  color: SeasColors.ink900,
                ),
              ),
            ),
            Text(
              '$done of $total done',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: accent,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            value: ratio,
            minHeight: 6,
            color: accent,
            backgroundColor: SeasColors.ink100,
          ),
        ),
      ],
    );
  }
}
