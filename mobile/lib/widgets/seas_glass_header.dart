import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';

/// Premium glass app header — white / soft red wash (not flat black).
class SeasGlassHeader extends StatelessWidget implements PreferredSizeWidget {
  const SeasGlassHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.onBack,
    this.trailing,
  });

  final String title;
  final String? subtitle;
  final VoidCallback? onBack;
  final Widget? trailing;

  @override
  Size get preferredSize => const Size.fromHeight(72);

  @override
  Widget build(BuildContext context) {
    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Colors.white.withValues(alpha: 0.92),
                const Color(0xFFFFF5F5).withValues(alpha: 0.88),
                Colors.white.withValues(alpha: 0.85),
              ],
            ),
            border: Border(bottom: BorderSide(color: SeasColors.ink200.withValues(alpha: 0.65))),
            boxShadow: [
              BoxShadow(color: SeasColors.volt.withValues(alpha: 0.06), blurRadius: 24, offset: const Offset(0, 8)),
            ],
          ),
          child: SafeArea(
            bottom: false,
            child: SizedBox(
              height: 56,
              child: Row(
                children: [
                  if (onBack != null)
                    IconButton(
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
                    )
                  else
                    const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 17,
                            color: SeasColors.ink950,
                            letterSpacing: -0.3,
                          ),
                        ),
                        if (subtitle != null)
                          Text(
                            subtitle!,
                            style: TextStyle(color: SeasColors.ink400, fontSize: 11, fontWeight: FontWeight.w500),
                          ),
                      ],
                    ),
                  ),
                  if (trailing != null) trailing! else const SizedBox(width: 8),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Soft canvas with subtle red ambient glow.
class SeasPremiumScaffold extends StatelessWidget {
  const SeasPremiumScaffold({
    super.key,
    required this.header,
    required this.body,
    this.bottom,
  });

  final PreferredSizeWidget header;
  final Widget body;
  final Widget? bottom;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SeasColors.canvas,
      appBar: header,
      body: Stack(
        children: [
          Positioned(
            top: -80,
            right: -60,
            child: IgnorePointer(
              child: Container(
                width: 220,
                height: 220,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(colors: [
                    SeasColors.volt.withValues(alpha: 0.12),
                    SeasColors.volt.withValues(alpha: 0),
                  ]),
                ),
              ),
            ),
          ),
          Positioned(
            bottom: 40,
            left: -40,
            child: IgnorePointer(
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(colors: [
                    SeasColors.ink950.withValues(alpha: 0.06),
                    Colors.transparent,
                  ]),
                ),
              ),
            ),
          ),
          Column(
            children: [
              Expanded(child: body),
              if (bottom != null) bottom!,
            ],
          ),
        ],
      ),
    );
  }
}
