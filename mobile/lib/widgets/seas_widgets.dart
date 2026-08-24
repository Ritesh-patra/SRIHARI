import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';

class SeasLogoMark extends StatelessWidget {
  const SeasLogoMark({super.key, this.size = 48, this.dark = false});
  final double size;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: size,
      width: size,
      decoration: BoxDecoration(
        color: dark ? SeasColors.ink950 : SeasColors.volt,
        borderRadius: BorderRadius.circular(size * 0.32),
        boxShadow: dark ? SeasShadows.seasLg : SeasShadows.glow,
      ),
      child: Icon(
        Icons.bolt_rounded,
        color: dark ? SeasColors.volt : SeasColors.white,
        size: size * 0.52,
      ),
    );
  }
}

class SeasCard extends StatelessWidget {
  const SeasCard({super.key, required this.child, this.padding, this.onTap});
  final Widget child;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    // Material (not DecoratedBox-only) so nested ListTiles paint ink correctly.
    final radius = BorderRadius.circular(18);
    final card = Container(
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: radius,
        boxShadow: SeasShadows.card,
      ),
      child: Material(
        color: SeasColors.white,
        shape: RoundedRectangleBorder(
          borderRadius: radius,
          side: BorderSide(color: SeasColors.white.withValues(alpha: 0.85)),
        ),
        clipBehavior: Clip.antiAlias,
        child: onTap == null
            ? Padding(padding: padding ?? const EdgeInsets.all(18), child: child)
            : InkWell(
                onTap: onTap,
                borderRadius: radius,
                child: Padding(padding: padding ?? const EdgeInsets.all(18), child: child),
              ),
      ),
    );
    return card;
  }
}

class SeasEyebrow extends StatelessWidget {
  const SeasEyebrow(this.text, {super.key, this.light = false});
  final String text;
  final bool light;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: GoogleFonts.plusJakartaSans(
        fontSize: 11,
        fontWeight: FontWeight.w800,
        letterSpacing: 2.2,
        color: light ? SeasColors.white.withValues(alpha: 0.45) : SeasColors.volt,
      ),
    );
  }
}

class SeasBadge extends StatelessWidget {
  const SeasBadge(this.label, {super.key, this.tone = SeasBadgeTone.neutral});
  final String label;
  final SeasBadgeTone tone;

  @override
  Widget build(BuildContext context) {
    late Color bg;
    late Color fg;
    switch (tone) {
      case SeasBadgeTone.volt:
        bg = SeasColors.voltSoft;
        fg = SeasColors.voltDeep;
      case SeasBadgeTone.success:
        bg = SeasColors.successSoft;
        fg = SeasColors.success;
      case SeasBadgeTone.warning:
        bg = SeasColors.warningSoft;
        fg = SeasColors.warning;
      case SeasBadgeTone.dark:
        bg = SeasColors.ink950;
        fg = SeasColors.white;
      case SeasBadgeTone.neutral:
        bg = SeasColors.ink100;
        fg = SeasColors.ink700;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(
        label,
        style: GoogleFonts.plusJakartaSans(fontSize: 11, fontWeight: FontWeight.w800, color: fg),
      ),
    );
  }
}

enum SeasBadgeTone { neutral, volt, success, warning, dark }

SeasBadgeTone badgeToneForStatus(String? status) {
  final s = (status ?? '').toLowerCase();
  if (s.contains('approv') || s.contains('complet') || s.contains('done')) return SeasBadgeTone.success;
  if (s.contains('closed') || s.contains('cancel') || s.contains('reassign')) return SeasBadgeTone.dark;
  if (s.contains('reject') || s.contains('pending_approval') || s.contains('submit')) return SeasBadgeTone.volt;
  if (s.contains('started') || s.contains('sld') || s.contains('draft') || s.contains('rework') || s.contains('progress') || s.contains('pending') || s == 'open') {
    return SeasBadgeTone.warning;
  }
  return SeasBadgeTone.neutral;
}

class SeasPrimaryButton extends StatelessWidget {
  const SeasPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.expand = true,
  });
  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final btn = DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        boxShadow: onPressed == null || loading ? null : SeasShadows.glow,
      ),
      child: FilledButton(
        onPressed: loading ? null : onPressed,
        child: loading
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
            : Text(label),
      ),
    );
    return expand ? SizedBox(width: double.infinity, child: btn) : btn;
  }
}

class SeasIconTile extends StatelessWidget {
  const SeasIconTile({super.key, required this.icon, this.bg = SeasColors.ink950, this.fg = SeasColors.white});
  final IconData icon;
  final Color bg;
  final Color fg;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 42,
      width: 42,
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(14)),
      child: Icon(icon, color: fg, size: 20),
    );
  }
}

class SeasEmptyState extends StatelessWidget {
  const SeasEmptyState({super.key, required this.title, this.subtitle, this.icon = Icons.inbox_outlined});
  final String title;
  final String? subtitle;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 64,
              width: 64,
              decoration: BoxDecoration(color: SeasColors.white, borderRadius: BorderRadius.circular(20), boxShadow: SeasShadows.card),
              child: Icon(icon, color: SeasColors.ink400, size: 28),
            ),
            const SizedBox(height: 16),
            Text(title, textAlign: TextAlign.center, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
            if (subtitle != null) ...[
              const SizedBox(height: 6),
              Text(subtitle!, textAlign: TextAlign.center, style: const TextStyle(color: SeasColors.ink400, fontSize: 13)),
            ],
          ],
        ),
      ),
    );
  }
}

class SeasPageScaffold extends StatelessWidget {
  const SeasPageScaffold({
    super.key,
    required this.title,
    required this.child,
    this.eyebrow,
    this.onBack,
    this.actions,
    this.floatingActionButton,
  });
  final String title;
  final String? eyebrow;
  final VoidCallback? onBack;
  final Widget child;
  final List<Widget>? actions;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: true,
      child: Scaffold(
        backgroundColor: SeasColors.canvas,
        floatingActionButton: floatingActionButton,
        body: Column(
          children: [
            Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: SeasColors.white.withValues(alpha: 0.92),
                border: const Border(bottom: BorderSide(color: Color(0x14000000))),
                boxShadow: const [BoxShadow(color: Color(0x0A000000), blurRadius: 12, offset: Offset(0, 2))],
              ),
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: EdgeInsets.fromLTRB(onBack != null ? 8 : 20, 12, 12, 14),
                  child: Row(
                    children: [
                      if (onBack != null) ...[
                        IconButton(
                          tooltip: 'Back',
                          onPressed: onBack,
                          icon: Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: SeasColors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: SeasColors.ink100),
                              boxShadow: SeasShadows.card,
                            ),
                            child: const Icon(Icons.arrow_back_rounded, size: 18, color: SeasColors.ink950),
                          ),
                        ),
                        const SizedBox(width: 4),
                      ],
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (eyebrow != null) SeasEyebrow(eyebrow!),
                            Text(title, style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -0.4)),
                          ],
                        ),
                      ),
                      ...?actions,
                    ],
                  ),
                ),
              ),
            ),
            Expanded(child: child),
          ],
        ),
      ),
    );
  }
}
