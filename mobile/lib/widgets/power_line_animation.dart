import 'dart:math' as math;
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import '../theme/seas_colors.dart';

/// Utility poles with animated current flowing along the sagging conductor.
class PowerLineAnimation extends StatefulWidget {
  const PowerLineAnimation({super.key, this.height = 88});
  final double height;

  @override
  State<PowerLineAnimation> createState() => _PowerLineAnimationState();
}

class _PowerLineAnimationState extends State<PowerLineAnimation> with SingleTickerProviderStateMixin {
  late final AnimationController _c;

  @override
  void initState() {
    super.initState();
    _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 2800))..repeat();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: widget.height,
      width: double.infinity,
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, __) => CustomPaint(
          painter: _PowerLinePainter(progress: _c.value),
        ),
      ),
    );
  }
}

class _PowerLinePainter extends CustomPainter {
  _PowerLinePainter({required this.progress});
  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    if (size.width <= 0 || size.height <= 0) return;

    final groundY = size.height * 0.92;
    final leftX = size.width * 0.18;
    final rightX = size.width * 0.82;
    final armY = size.height * 0.22;
    // Conductor hangs slightly below the cross-arm via insulator strings.
    final attachY = armY + 11;

    // Soft brand glow behind the span
    final glowCenter = Offset(size.width * 0.5, size.height * 0.44);
    canvas.drawCircle(
      glowCenter,
      size.width * 0.30,
      Paint()
        ..shader = ui.Gradient.radial(
          glowCenter,
          size.width * 0.30,
          [
            SeasColors.volt.withValues(alpha: 0.09),
            SeasColors.volt.withValues(alpha: 0.025),
            SeasColors.volt.withValues(alpha: 0.0),
          ],
          const [0.0, 0.42, 1.0],
        ),
    );

    final wirePath = Path()
      ..moveTo(leftX, attachY)
      ..quadraticBezierTo(
        size.width * 0.5,
        armY + size.height * 0.34,
        rightX,
        attachY,
      );

    // Soft wire bloom (under poles so poles stay crisp)
    canvas.drawPath(
      wirePath,
      Paint()
        ..color = SeasColors.volt.withValues(alpha: 0.12)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 10
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6)
        ..strokeCap = StrokeCap.round,
    );

    // Left pole with transformer; right pole with lamp detail.
    _drawUtilityPole(
      canvas,
      size,
      x: leftX,
      armY: armY,
      groundY: groundY,
      attachY: attachY,
      showTransformer: true,
    );
    _drawUtilityPole(
      canvas,
      size,
      x: rightX,
      armY: armY,
      groundY: groundY,
      attachY: attachY,
      showLamp: true,
    );

    // Main conductor
    canvas.drawPath(
      wirePath,
      Paint()
        ..color = SeasColors.ink800
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.2
        ..strokeCap = StrokeCap.round,
    );

    // Thin highlight edge for depth
    canvas.drawPath(
      wirePath,
      Paint()
        ..color = Colors.white.withValues(alpha: 0.35)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 0.7
        ..strokeCap = StrokeCap.round,
    );

    // PathMetrics is a single-pass iterable — don't call isEmpty then .first.
    final metricIter = wirePath.computeMetrics().iterator;
    if (!metricIter.moveNext()) return;
    final metric = metricIter.current;
    if (metric.length <= 0) return;

    _drawTravelingCurrent(canvas, metric);

    // Ground line
    canvas.drawLine(
      Offset(size.width * 0.06, groundY),
      Offset(size.width * 0.94, groundY),
      Paint()
        ..color = SeasColors.ink200
        ..strokeWidth = 1.2
        ..strokeCap = StrokeCap.round,
    );
  }

  void _drawUtilityPole(
    Canvas canvas,
    Size size, {
    required double x,
    required double armY,
    required double groundY,
    required double attachY,
    bool showTransformer = false,
    bool showLamp = false,
  }) {
    final poleH = groundY - armY;
    final poleTop = armY - 10;
    final shaftW = (size.width * 0.014).clamp(3.2, 5.2);
    final armHalf = size.width * 0.055;

    final wood = Paint()
      ..color = SeasColors.ink950
      ..style = PaintingStyle.fill;

    // Slight taper: wider at base
    final topHalf = shaftW * 0.42;
    final botHalf = shaftW * 0.58;
    final shaft = Path()
      ..moveTo(x - topHalf, poleTop)
      ..lineTo(x + topHalf, poleTop)
      ..lineTo(x + botHalf, groundY)
      ..lineTo(x - botHalf, groundY)
      ..close();
    canvas.drawPath(shaft, wood);

    // Subtle highlight strip on shaft
    canvas.drawLine(
      Offset(x - topHalf * 0.35, poleTop + 2),
      Offset(x - botHalf * 0.35, groundY - 2),
      Paint()
        ..color = Colors.white.withValues(alpha: 0.12)
        ..strokeWidth = 1.1
        ..strokeCap = StrokeCap.round,
    );

    // Cross-arm (primary)
    const armThick = 2.6;
    final armRect = RRect.fromRectAndRadius(
      Rect.fromCenter(center: Offset(x, armY), width: armHalf * 2, height: armThick),
      const Radius.circular(1.2),
    );
    canvas.drawRRect(armRect, wood);

    // Secondary shorter arm (classic multi-wire look, kept light)
    final arm2Y = armY + 9;
    final arm2Half = armHalf * 0.62;
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromCenter(center: Offset(x, arm2Y), width: arm2Half * 2, height: 2.0),
        const Radius.circular(1.0),
      ),
      Paint()..color = SeasColors.ink800,
    );

    // Brace rods from shaft to outer arm ends
    final bracePaint = Paint()
      ..color = SeasColors.ink700
      ..strokeWidth = 1.05
      ..strokeCap = StrokeCap.round;
    final braceAnchor = Offset(x, armY + poleH * 0.14);
    canvas.drawLine(Offset(x - armHalf + 2, armY + 1), braceAnchor, bracePaint);
    canvas.drawLine(Offset(x + armHalf - 2, armY + 1), braceAnchor, bracePaint);

    // Pole top pin / finial
    canvas.drawLine(
      Offset(x, poleTop),
      Offset(x, poleTop - 5),
      Paint()
        ..color = SeasColors.ink950
        ..strokeWidth = 1.6
        ..strokeCap = StrokeCap.round,
    );
    canvas.drawCircle(Offset(x, poleTop - 6.2), 1.6, Paint()..color = SeasColors.ink950);

    // Disc insulator strings at wire attach points (outer arm)
    _drawInsulatorString(canvas, Offset(x - armHalf * 0.72, armY), attachY);
    _drawInsulatorString(canvas, Offset(x + armHalf * 0.72, armY), attachY);

    // Small pin insulators on secondary arm
    for (final dx in [-arm2Half * 0.55, arm2Half * 0.55]) {
      final p = Offset(x + dx, arm2Y + 3.5);
      canvas.drawCircle(p, 1.7, Paint()..color = SeasColors.ink200);
      canvas.drawCircle(p, 0.9, Paint()..color = SeasColors.ink700);
    }

    if (showTransformer) {
      _drawTransformer(canvas, x, armY + poleH * 0.28, shaftW);
    }
    if (showLamp) {
      _drawStreetLamp(canvas, x + armHalf * 0.15, armY - 1, armHalf);
    }

    // Footing / base pad
    final padW = botHalf * 2.8;
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromCenter(center: Offset(x, groundY), width: padW, height: 3.2),
        const Radius.circular(1.0),
      ),
      Paint()..color = SeasColors.ink800,
    );
  }

  void _drawInsulatorString(Canvas canvas, Offset hangFrom, double attachY) {
    final midY = (hangFrom.dy + attachY) * 0.5;
    final ceramic = Paint()..color = SeasColors.ink100;
    final rim = Paint()
      ..color = SeasColors.ink400
      ..style = PaintingStyle.stroke
      ..strokeWidth = 0.7;

    // Pin from arm
    canvas.drawLine(
      hangFrom,
      Offset(hangFrom.dx, hangFrom.dy + 3),
      Paint()
        ..color = SeasColors.ink700
        ..strokeWidth = 1.0
        ..strokeCap = StrokeCap.round,
    );

    // Stacked disc insulators
    for (var i = 0; i < 2; i++) {
      final cy = hangFrom.dy + 5.5 + i * 3.6;
      final r = Rect.fromCenter(center: Offset(hangFrom.dx, cy), width: 7.2 - i * 0.4, height: 2.8);
      canvas.drawOval(r, ceramic);
      canvas.drawOval(r, rim);
    }

    // Hot attachment eye (brand accent)
    final eye = Offset(hangFrom.dx, attachY);
    canvas.drawCircle(eye, 2.8, Paint()..color = SeasColors.volt.withValues(alpha: 0.22));
    canvas.drawCircle(eye, 1.85, Paint()..color = SeasColors.volt);
    canvas.drawCircle(eye, 0.75, Paint()..color = Colors.white.withValues(alpha: 0.92));

    // Tiny stem into eye
    canvas.drawLine(
      Offset(hangFrom.dx, midY + 2),
      eye,
      Paint()
        ..color = SeasColors.ink700
        ..strokeWidth = 0.9
        ..strokeCap = StrokeCap.round,
    );
  }

  void _drawTransformer(Canvas canvas, double poleX, double cy, double shaftW) {
    const canW = 9.5;
    const canH = 12.0;
    final cx = poleX + shaftW * 0.55 + canW * 0.55;
    final body = RRect.fromRectAndRadius(
      Rect.fromCenter(center: Offset(cx, cy), width: canW, height: canH),
      const Radius.circular(2.2),
    );
    canvas.drawRRect(body, Paint()..color = SeasColors.ink800);
    canvas.drawRRect(
      body,
      Paint()
        ..color = SeasColors.ink950
        ..style = PaintingStyle.stroke
        ..strokeWidth = 0.9,
    );
    // Cooling fins
    for (var i = -1; i <= 1; i++) {
      canvas.drawLine(
        Offset(cx - canW * 0.28, cy + i * 2.8),
        Offset(cx + canW * 0.28, cy + i * 2.8),
        Paint()
          ..color = SeasColors.ink400.withValues(alpha: 0.55)
          ..strokeWidth = 0.8,
      );
    }
    // Bushing on top
    canvas.drawCircle(
      Offset(cx, cy - canH * 0.5 - 1.2),
      1.4,
      Paint()..color = SeasColors.ink200,
    );
    // Mount bracket to pole
    canvas.drawLine(
      Offset(poleX + shaftW * 0.35, cy),
      Offset(cx - canW * 0.5, cy),
      Paint()
        ..color = SeasColors.ink700
        ..strokeWidth = 1.2
        ..strokeCap = StrokeCap.round,
    );
  }

  void _drawStreetLamp(Canvas canvas, double hingeX, double armY, double armHalf) {
    final tip = Offset(hingeX + armHalf * 0.55, armY - 6);
    canvas.drawLine(
      Offset(hingeX, armY),
      tip,
      Paint()
        ..color = SeasColors.ink800
        ..strokeWidth = 1.35
        ..strokeCap = StrokeCap.round,
    );
    // Compact lamp head
    final head = Path()
      ..moveTo(tip.dx - 4.5, tip.dy)
      ..lineTo(tip.dx + 4.5, tip.dy)
      ..lineTo(tip.dx + 2.8, tip.dy + 3.5)
      ..lineTo(tip.dx - 2.8, tip.dy + 3.5)
      ..close();
    canvas.drawPath(head, Paint()..color = SeasColors.ink800);
    canvas.drawCircle(
      Offset(tip.dx, tip.dy + 4.2),
      1.6,
      Paint()..color = SeasColors.volt.withValues(alpha: 0.55),
    );
  }

  void _drawTravelingCurrent(Canvas canvas, ui.PathMetric metric) {
    final len = metric.length;

    // Continuous dash pulse riding the conductor (left → right)
    const dashLen = 16.0;
    const gapLen = 12.0;
    const cycle = dashLen + gapLen;
    final phase = progress * cycle;

    final dashGlow = Paint()
      ..color = SeasColors.volt.withValues(alpha: 0.28)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4.2
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 3)
      ..strokeCap = StrokeCap.round;

    final dashCore = Paint()
      ..color = SeasColors.volt.withValues(alpha: 0.85)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.15
      ..strokeCap = StrokeCap.round;

    var d = -phase;
    while (d < len) {
      final start = d.clamp(0.0, len);
      final end = (d + dashLen).clamp(0.0, len);
      if (end > start) {
        final seg = metric.extractPath(start, end);
        canvas.drawPath(seg, dashGlow);
        canvas.drawPath(seg, dashCore);
      }
      d += cycle;
    }

    // Leading energy packets with soft trails
    const packetCount = 3;
    for (var i = 0; i < packetCount; i++) {
      final t = (progress + i / packetCount) % 1.0;
      final tan = metric.getTangentForOffset(len * t);
      if (tan == null) continue;

      final pulse = 0.62 + 0.38 * math.sin((progress * 2 + i * 0.7) * math.pi * 2);
      final head = tan.position;

      // Comet trail behind the head
      for (var s = 1; s <= 7; s++) {
        var backT = t - s * 0.016;
        if (backT < 0) backT += 1.0;
        final back = metric.getTangentForOffset(len * backT);
        if (back == null) continue;
        final fade = (1.0 - s / 8.0) * 0.62 * pulse;
        canvas.drawCircle(
          back.position,
          2.8 - s * 0.26,
          Paint()..color = SeasColors.volt.withValues(alpha: fade),
        );
      }

      // Soft outer bloom
      canvas.drawCircle(
        head,
        8.5 + pulse * 2.4,
        Paint()
          ..color = SeasColors.volt.withValues(alpha: 0.14 * pulse)
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4.5),
      );
      // Mid ring
      canvas.drawCircle(
        head,
        4.2 + pulse * 1.1,
        Paint()..color = SeasColors.voltGlow.withValues(alpha: 0.55 * pulse),
      );
      // Core
      canvas.drawCircle(
        head,
        2.7 + pulse * 0.8,
        Paint()..color = SeasColors.volt.withValues(alpha: 0.95),
      );
      canvas.drawCircle(
        head,
        1.2,
        Paint()..color = Colors.white.withValues(alpha: 0.96),
      );
    }
  }

  @override
  bool shouldRepaint(covariant _PowerLinePainter oldDelegate) => oldDelegate.progress != progress;
}
