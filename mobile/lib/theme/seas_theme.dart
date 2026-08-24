import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'seas_colors.dart';

ThemeData buildSeasTheme() {
  final base = GoogleFonts.plusJakartaSansTextTheme();
  final text = base.apply(
    bodyColor: SeasColors.ink900,
    displayColor: SeasColors.ink900,
  ).copyWith(
    displayLarge: base.displayLarge?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -1.2),
    headlineLarge: base.headlineLarge?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.8),
    headlineMedium: base.headlineMedium?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.5),
    titleLarge: base.titleLarge?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.3),
    titleMedium: base.titleMedium?.copyWith(fontWeight: FontWeight.w700),
    labelLarge: base.labelLarge?.copyWith(fontWeight: FontWeight.w700),
    bodySmall: base.bodySmall?.copyWith(color: SeasColors.ink400),
  );

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    scaffoldBackgroundColor: SeasColors.canvas,
    colorScheme: const ColorScheme.light(
      primary: SeasColors.volt,
      onPrimary: SeasColors.white,
      secondary: SeasColors.ink950,
      onSecondary: SeasColors.white,
      surface: SeasColors.white,
      onSurface: SeasColors.ink900,
      error: SeasColors.volt,
    ),
    textTheme: text,
    appBarTheme: AppBarTheme(
      elevation: 0,
      scrolledUnderElevation: 0,
      backgroundColor: SeasColors.white.withValues(alpha: 0.92),
      foregroundColor: SeasColors.ink900,
      surfaceTintColor: Colors.transparent,
      titleTextStyle: GoogleFonts.plusJakartaSans(
        fontWeight: FontWeight.w800,
        fontSize: 18,
        color: SeasColors.ink900,
        letterSpacing: -0.3,
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      height: 68,
      elevation: 0,
      backgroundColor: SeasColors.white,
      indicatorColor: SeasColors.voltSoft,
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return GoogleFonts.plusJakartaSans(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: selected ? SeasColors.volt : SeasColors.ink400,
        );
      }),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return IconThemeData(color: selected ? SeasColors.volt : SeasColors.ink400, size: 22);
      }),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: SeasColors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: SeasColors.ink200),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: SeasColors.ink200),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: SeasColors.volt, width: 1.5),
      ),
      labelStyle: const TextStyle(fontWeight: FontWeight.w600, color: SeasColors.ink400),
      hintStyle: const TextStyle(color: Color(0xFFA3A3A3)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: SeasColors.volt,
        foregroundColor: SeasColors.white,
        elevation: 0,
        shadowColor: SeasColors.volt.withValues(alpha: 0.35),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, letterSpacing: 0.4),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: SeasColors.ink900,
        side: const BorderSide(color: SeasColors.ink200),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
      ),
    ),
    cardTheme: CardThemeData(
      color: SeasColors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: SeasColors.white.withValues(alpha: 0.8)),
      ),
      margin: EdgeInsets.zero,
    ),
    dividerColor: SeasColors.ink100,
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: SeasColors.volt),
    datePickerTheme: DatePickerThemeData(
      rangeSelectionBackgroundColor: const Color(0x33E10600),
      dayForegroundColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) return SeasColors.white;
        return SeasColors.ink900;
      }),
      dayBackgroundColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.selected)) return SeasColors.volt;
        return Colors.transparent;
      }),
    ),
  );
}
