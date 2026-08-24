import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../core/msn_extractor.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_glass_header.dart';

/// Camera barcode scan → extract MSN only (ignore IMEI / LOA noise).
class MsnScanScreen extends StatefulWidget {
  const MsnScanScreen({super.key});

  @override
  State<MsnScanScreen> createState() => _MsnScanScreenState();
}

class _MsnScanScreenState extends State<MsnScanScreen> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );
  bool _handled = false;
  String? _hint;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;
    for (final b in capture.barcodes) {
      final raw = b.rawValue;
      if (raw == null || raw.isEmpty) continue;
      final msn = MsnExtractor.extract(raw);
      if (msn == null) {
        setState(() => _hint = 'IMEI / other code ignored — aim at meter S.No. barcode');
        continue;
      }
      _handled = true;
      Navigator.pop(context, msn);
      return;
    }
  }

  @override
  Widget build(BuildContext context) {
    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Scan Meter MSN',
        subtitle: 'Only serial number is captured',
        onBack: () => Navigator.pop(context),
      ),
      body: Column(
        children: [
          Expanded(
            child: ClipRRect(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  MobileScanner(controller: _controller, onDetect: _onDetect),
                  IgnorePointer(
                    child: CustomPaint(painter: _ScanFramePainter()),
                  ),
                  Positioned(
                    left: 20,
                    right: 20,
                    bottom: 28,
                    child: Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.92),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: SeasColors.ink100),
                        boxShadow: SeasShadows.card,
                      ),
                      child: Column(
                        children: [
                          Text(
                            'Aim at the meter S.No. barcode near the bottom',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _hint ?? 'Example MSN: PL00213258 — IMEI is ignored',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
              child: OutlinedButton(
                onPressed: () async {
                  final ctrl = TextEditingController();
                  final ok = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Enter MSN manually'),
                      content: TextField(controller: ctrl, autofocus: true, decoration: const InputDecoration(hintText: 'PL00213258')),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                        FilledButton(
                          onPressed: () => Navigator.pop(ctx, true),
                          style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                          child: const Text('Use'),
                        ),
                      ],
                    ),
                  );
                  if (ok == true && mounted) {
                    final msn = MsnExtractor.extract(ctrl.text) ?? ctrl.text.trim().toUpperCase();
                    if (msn.isNotEmpty && mounted) Navigator.pop(context, msn);
                  }
                },
                child: const Text('Type MSN instead'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ScanFramePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final overlay = Paint()..color = Colors.black.withValues(alpha: 0.45);
    final cut = RRect.fromRectAndRadius(
      Rect.fromCenter(center: Offset(size.width / 2, size.height * 0.42), width: size.width * 0.72, height: size.width * 0.42),
      const Radius.circular(18),
    );
    final path = Path()
      ..addRect(Offset.zero & size)
      ..addRRect(cut)
      ..fillType = PathFillType.evenOdd;
    canvas.drawPath(path, overlay);
    final border = Paint()
      ..color = SeasColors.volt
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3;
    canvas.drawRRect(cut, border);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
