import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/api_client.dart';
import '../theme/seas_colors.dart';
import 'dtr_survey_form.dart';
import 'feeder_survey_status_screen.dart';

/// Success after each DTR submit under Feeder→DTR flow.
class DtrFeederSuccessScreen extends StatefulWidget {
  const DtrFeederSuccessScreen({
    super.key,
    required this.prefill,
    this.feederSurveyId,
    this.dtrLabel,
    this.message,
  });

  final Map<String, dynamic> prefill;
  final int? feederSurveyId;
  final String? dtrLabel;
  final String? message;

  @override
  State<DtrFeederSuccessScreen> createState() => _DtrFeederSuccessScreenState();
}

class _DtrFeederSuccessScreenState extends State<DtrFeederSuccessScreen> {
  bool finishing = false;
  int dtrsExpected = 0;
  int dtrsCompleted = 0;
  late int? resolvedFeederSurveyId;

  @override
  void initState() {
    super.initState();
    resolvedFeederSurveyId = widget.feederSurveyId;
    final fid = widget.prefill['feeder_survey_id'];
    resolvedFeederSurveyId ??= fid is int ? fid : int.tryParse('$fid');
  }

  /// Finish DTR only when Feeder→DTR context (feeder survey id / feeder id) exists.
  bool get _canFinishDtr {
    if (resolvedFeederSurveyId != null) return true;
    final feederId = widget.prefill['feeder_id'];
    return feederId != null && '$feederId'.trim().isNotEmpty && '$feederId' != 'null';
  }

  void _goDashboard() {
    Navigator.of(context).popUntil((r) => r.isFirst);
  }

  Future<void> _nextDtr() async {
    await Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => DtrSurveyFormScreen(
          prefill: widget.prefill,
          fromFeederFlow: true,
          feederSurveyId: resolvedFeederSurveyId ?? widget.feederSurveyId,
        ),
      ),
    );
  }

  Future<void> _finishFeederDtr() async {
    var surveyId = resolvedFeederSurveyId;
    final feederIdRaw = widget.prefill['feeder_id'];
    final feederId = feederIdRaw is int ? feederIdRaw : int.tryParse('$feederIdRaw');

    if (feederId != null) {
      try {
        final res = await api.get('/feeder-surveys/status?feeder_id=$feederId');
        surveyId ??= (res['survey_id'] as num?)?.toInt();
        dtrsExpected = (res['dtrs_expected'] as num?)?.toInt() ?? dtrsExpected;
        dtrsCompleted = (res['dtrs_completed'] as num?)?.toInt() ?? dtrsCompleted;
        resolvedFeederSurveyId = surveyId;
      } catch (_) {}
    }

    if (surveyId == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text(
          'DTR survey is saved. Finish DTR / SLD upload needs a feeder survey first — submit feeder details when ready (optional for DTR work).',
        ),
        backgroundColor: SeasColors.volt,
      ));
      return;
    }

    if (!mounted) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Finish DTR work?', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
        content: Text(
          'Are you sure?\n\n'
          'DTRs expected on feeder: $dtrsExpected\n'
          'DTRs completed by you: $dtrsCompleted\n\n'
          'Next step: SLD Verification — upload the feeder SLD image before manager approval.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            child: const Text('Yes, Finish'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => finishing = true);
    try {
      final res = await api.post('/feeder-surveys/$surveyId/finish-dtr', {});
      if (!mounted) return;
      final surveyRaw = res['survey'];
      final survey = surveyRaw is Map
          ? Map<String, dynamic>.from(surveyRaw)
          : <String, dynamic>{'id': surveyId};
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(
          res['message']?.toString() ??
              'DTR work finished. SLD Verification — upload required.',
        ),
        backgroundColor: SeasColors.ink950,
      ));
      // Feeder → DTR done → SLD verification (same feeder SLD step as after feeder field work).
      await Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => FeederSurveyStatusScreen(
            initialFilter: 'sld_pending',
            autoOpenSldSurvey: survey,
            bannerMessage:
                'SLD Verification — upload required. Feeder→DTR field work is complete; submit the SLD image to send for approval.',
          ),
        ),
        (r) => r.isFirst,
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString().replaceFirst('Exception: ', '')),
        backgroundColor: SeasColors.volt,
      ));
    } finally {
      if (mounted) setState(() => finishing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final feederName = '${widget.prefill['feeder_name'] ?? ''}'.trim();
    final feederCode = '${widget.prefill['feeder_code'] ?? ''}'.trim();
    final feeder = feederName.isNotEmpty && feederCode.isNotEmpty
        ? '$feederName · $feederCode'
        : (feederName.isNotEmpty ? feederName : (feederCode.isNotEmpty ? feederCode : 'Feeder'));

    return Scaffold(
      backgroundColor: SeasColors.white,
      body: SafeArea(
        child: finishing
            ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
            : Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 4, 8, 0),
                    child: Row(
                      children: [
                        IconButton(
                          onPressed: _goDashboard,
                          icon: const Icon(Icons.arrow_back_rounded, color: SeasColors.ink950),
                        ),
                        Expanded(
                          child: Text(
                            'DTR Submitted',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                              color: SeasColors.ink950,
                            ),
                          ),
                        ),
                        if (_canFinishDtr)
                          TextButton(
                            onPressed: finishing ? null : _finishFeederDtr,
                            style: TextButton.styleFrom(
                              foregroundColor: SeasColors.volt,
                              padding: const EdgeInsets.symmetric(horizontal: 10),
                            ),
                            child: Text(
                              'FINISH DTR',
                              style: GoogleFonts.plusJakartaSans(
                                fontWeight: FontWeight.w800,
                                fontSize: 12,
                                letterSpacing: 0.3,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
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
                                  'DTR Survey Submitted Successfully!',
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
                                  widget.message ??
                                      'Continue with the next DTR on this feeder, or return to the dashboard. Use Finish DTR (top right) when all DTRs are done — SLD verification is required next.',
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
                                      _Kv(label: 'Feeder', value: feeder),
                                      _Kv(
                                        label: 'DTR',
                                        value: (widget.dtrLabel ?? '').trim().isEmpty ? '—' : widget.dtrLabel!,
                                        last: true,
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton.icon(
                              onPressed: _nextDtr,
                              icon: const Icon(Icons.arrow_forward_rounded, size: 20),
                              label: Text(
                                'Continue to Next DTR',
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
                              onPressed: _goDashboard,
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
                ],
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
