import 'package:flutter/material.dart';
import '../theme/seas_colors.dart';

/// Themed date-range picker — readable in-range days (no solid black blocks).
Future<DateTimeRange?> pickSeasDateRange(
  BuildContext context, {
  required DateTimeRange initial,
  DateTime? firstDate,
  DateTime? lastDate,
  String helpText = 'Select range',
  String saveText = 'Save',
}) {
  // Soft brand tint — never use dark/black secondary (theme secondary is ink950).
  const softRange = Color(0x33E10600); // ~20% SEAS red
  const softRangeHover = Color(0x44E10600);

  return showDateRangePicker(
    context: context,
    firstDate: firstDate ?? DateTime(2024),
    lastDate: lastDate ?? DateTime.now().add(const Duration(days: 1)),
    initialDateRange: initial,
    helpText: helpText,
    saveText: saveText,
    builder: (ctx, child) {
      final base = Theme.of(ctx);
      return Theme(
        data: base.copyWith(
          // M3 range days often tint from primaryContainer / secondary — keep light.
          colorScheme: base.colorScheme.copyWith(
            primary: SeasColors.volt,
            onPrimary: Colors.white,
            primaryContainer: SeasColors.voltSoft,
            onPrimaryContainer: SeasColors.ink900,
            secondary: SeasColors.voltSoft,
            onSecondary: SeasColors.ink900,
            secondaryContainer: softRange,
            onSecondaryContainer: SeasColors.ink900,
            surface: SeasColors.white,
            onSurface: SeasColors.ink900,
            surfaceContainerHighest: SeasColors.voltSoft,
            outline: SeasColors.ink200,
          ),
          datePickerTheme: DatePickerThemeData(
            backgroundColor: SeasColors.white,
            headerBackgroundColor: SeasColors.volt,
            headerForegroundColor: Colors.white,
            rangePickerBackgroundColor: SeasColors.white,
            rangePickerHeaderBackgroundColor: SeasColors.volt,
            rangePickerHeaderForegroundColor: Colors.white,
            rangeSelectionBackgroundColor: softRange,
            rangeSelectionOverlayColor: WidgetStateProperty.resolveWith((states) {
              if (states.contains(WidgetState.selected)) {
                return SeasColors.volt;
              }
              return softRangeHover;
            }),
            dayBackgroundColor: WidgetStateProperty.resolveWith((states) {
              if (states.contains(WidgetState.selected)) {
                return SeasColors.volt;
              }
              return Colors.transparent;
            }),
            dayForegroundColor: WidgetStateProperty.resolveWith((states) {
              if (states.contains(WidgetState.selected)) {
                return Colors.white;
              }
              if (states.contains(WidgetState.disabled)) {
                return SeasColors.ink200;
              }
              return SeasColors.ink900;
            }),
            todayForegroundColor: WidgetStateProperty.all(SeasColors.volt),
            todayBackgroundColor: WidgetStateProperty.all(Colors.transparent),
            todayBorder: const BorderSide(color: SeasColors.volt, width: 1.2),
            dayOverlayColor: WidgetStateProperty.resolveWith((states) {
              if (states.contains(WidgetState.selected)) {
                return SeasColors.volt.withValues(alpha: 0.12);
              }
              return SeasColors.volt.withValues(alpha: 0.08);
            }),
            dayShape: WidgetStateProperty.all(const CircleBorder()),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            rangePickerShape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          ),
          textButtonTheme: TextButtonThemeData(
            style: TextButton.styleFrom(
              foregroundColor: SeasColors.volt,
              textStyle: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        ),
        child: child!,
      );
    },
  );
}
