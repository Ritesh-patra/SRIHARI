import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';

/// Shared "Are you sure?" confirm before Submit / destructive actions.
Future<bool> confirmSubmit(
  BuildContext context, {
  String title = 'Are you sure?',
  String message = 'Do you want to submit this now?',
  String confirmLabel = 'Yes, Submit',
  String cancelLabel = 'Cancel',
}) async {
  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) => AlertDialog(
      title: Text(title, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: Text(cancelLabel),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, true),
          style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return ok == true;
}
