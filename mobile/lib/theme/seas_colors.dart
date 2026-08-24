import 'package:flutter/material.dart';

/// Mirrors web `tailwind.config.js` SEAS tokens.
class SeasColors {
  static const volt = Color(0xFFE10600);
  static const voltDeep = Color(0xFFB10500);
  static const voltSoft = Color(0xFFFFE8E6);
  static const voltGlow = Color(0xFFFF3B30);

  static const ink950 = Color(0xFF0A0A0A);
  static const ink900 = Color(0xFF111111);
  static const ink800 = Color(0xFF1A1A1A);
  static const ink700 = Color(0xFF2A2A2A);
  static const ink400 = Color(0xFF737373);
  static const ink200 = Color(0xFFD4D4D4);
  static const ink100 = Color(0xFFE5E7EB);
  static const ink50 = Color(0xFFF3F4F6);

  static const canvas = Color(0xFFE8ECF1);
  static const canvasSoft = Color(0xFFF0F3F7);
  static const white = Color(0xFFFFFFFF);

  static const success = Color(0xFF047857);
  static const successSoft = Color(0xFFECFDF5);
  static const warning = Color(0xFFB45309);
  static const warningSoft = Color(0xFFFFFBEB);
}

class SeasShadows {
  static List<BoxShadow> get card => const [
        BoxShadow(color: Color(0x0A0F172A), blurRadius: 2, offset: Offset(0, 1)),
        BoxShadow(color: Color(0x140F172A), blurRadius: 24, offset: Offset(0, 8)),
      ];

  static List<BoxShadow> get seasLg => const [
        BoxShadow(color: Color(0x140F172A), blurRadius: 16, offset: Offset(0, 8)),
        BoxShadow(color: Color(0x2E0F172A), blurRadius: 48, offset: Offset(0, 24)),
      ];

  static List<BoxShadow> get glow => const [
        BoxShadow(color: Color(0x47E10600), blurRadius: 32, offset: Offset(0, 10)),
      ];
}
