import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';
import 'dtr_survey_form.dart';

/// Success after feeder basic details are saved (draft) — Start DTR or Dashboard.
class FeederSurveySuccessScreen extends StatelessWidget {
  const FeederSurveySuccessScreen({
    super.key,
    required this.survey,
    this.message,
  });

  final Map<String, dynamic> survey;
  final String? message;

  Map<String, dynamic> get _prefill => {
        'region_id': survey['region_id'],
        'circle_id': survey['circle_id'],
        'division_id': survey['division_id'],
        'zone_id': survey['zone_id'],
        'substation_id': survey['substation_id'],
        'feeder_id': survey['feeder_id'],
        'feeder_code': survey['feeder_code'],
        'feeder_name': survey['feeder_name'],
      };

  String get _feederLabel {
    final name = '${survey['feeder_name'] ?? ''}'.trim();
    final code = '${survey['feeder_code'] ?? ''}'.trim();
    if (name.isNotEmpty && code.isNotEmpty) return '$name · $code';
    if (name.isNotEmpty) return name;
    if (code.isNotEmpty) return code;
    return 'Feeder';
  }

  void _goDashboard(BuildContext context) {
    Navigator.of(context).popUntil((r) => r.isFirst);
  }

  Future<void> _startDtr(BuildContext context) async {
    await Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => DtrSurveyFormScreen(
          prefill: _prefill,
          fromFeederFlow: true,
          feederSurveyId: (survey['id'] as num?)?.toInt(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ss = '${survey['substation_name'] ?? ''}'.trim();

    return Scaffold(
      backgroundColor: SeasColors.white,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
          child: Column(
            children: [
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      height: 96,
                      width: 96,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: SeasColors.success,
                        boxShadow: [
                          BoxShadow(
                            color: SeasColors.success.withValues(alpha: 0.28),
                            blurRadius: 24,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: const Icon(Icons.check_rounded, color: Colors.white, size: 52),
                    ),
                    const SizedBox(height: 22),
                    Text(
                      'Feeder Details Submitted Successfully!',
                      textAlign: TextAlign.center,
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 22,
                        height: 1.25,
                        color: SeasColors.success,
                        letterSpacing: -0.3,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      message ?? 'Basic details saved as draft (Pending DTR Survey). Continue with DTRs, then Finish DTR and upload SLD for approval.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: SeasColors.ink400, fontSize: 13.5, height: 1.4),
                    ),
                    const SizedBox(height: 22),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                      decoration: BoxDecoration(
                        color: SeasColors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: SeasColors.ink100),
                        boxShadow: SeasShadows.card,
                      ),
                      child: Column(
                        children: [
                          _Kv(label: 'Feeder', value: _feederLabel),
                          if (ss.isNotEmpty) _Kv(label: 'Substation', value: ss, last: true) else _Kv(label: 'Status', value: 'Pending DTR Survey', last: true),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => _startDtr(context),
                  icon: const Icon(Icons.bolt_rounded, size: 20),
                  label: Text(
                    'Start DTR Survey',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15),
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: SeasColors.volt,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () => _goDashboard(context),
                  icon: const Icon(Icons.dashboard_outlined, size: 18),
                  label: Text(
                    'Go to Dashboard',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: SeasColors.ink950,
                    side: const BorderSide(color: SeasColors.ink900, width: 1.3),
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Kv extends StatelessWidget {
  const _Kv({required this.label, required this.value, this.last = false});
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
          Text(label, style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600, fontSize: 13)),
          const SizedBox(width: 12),
          Expanded(
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
