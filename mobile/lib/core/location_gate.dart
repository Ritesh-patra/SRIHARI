import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';

/// Ensure location permission + GPS fix (Feeder Survey, Add Pole, etc.).
/// Returns position on success; shows error and returns null on failure.
Future<Position?> ensureDeviceLocation(
  BuildContext context, {
  String purpose = 'continue',
}) async {
  try {
    final serviceOn = await Geolocator.isLocationServiceEnabled();
    if (!serviceOn) {
      if (context.mounted) {
        await _showLocationError(
          context,
          'Location services are turned off. Please enable GPS / location and try again.',
        );
      }
      return null;
    }

    var perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) {
      perm = await Geolocator.requestPermission();
    }
    if (perm == LocationPermission.denied) {
      if (context.mounted) {
        await _showLocationError(
          context,
          'Location permission denied. Allow location access to $purpose.',
        );
      }
      return null;
    }
    if (perm == LocationPermission.deniedForever) {
      if (context.mounted) {
        await _showLocationError(
          context,
          'Location permission permanently denied. Enable it in browser/app settings, then try again.',
        );
      }
      return null;
    }

    if (context.mounted) {
      showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(
          child: Card(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  CircularProgressIndicator(color: SeasColors.volt),
                  SizedBox(height: 14),
                  Text('Getting GPS location…', style: TextStyle(fontWeight: FontWeight.w600)),
                ],
              ),
            ),
          ),
        ),
      );
    }

    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      if (context.mounted) Navigator.of(context, rootNavigator: true).pop();
      return pos;
    } catch (_) {
      if (context.mounted) Navigator.of(context, rootNavigator: true).pop();
      if (context.mounted) {
        await _showLocationError(
          context,
          'Could not get GPS location. Check signal / permissions and try again.',
        );
      }
      return null;
    }
  } catch (e) {
    if (context.mounted) {
      await _showLocationError(
        context,
        'Location error: ${e.toString().replaceFirst('Exception: ', '')}',
      );
    }
    return null;
  }
}

/// Backward-compatible alias used by Feeder → DTR hub.
Future<Position?> ensureLocationForFeederSurvey(BuildContext context) {
  return ensureDeviceLocation(context, purpose: 'start Feeder Survey');
}

Future<void> _showLocationError(BuildContext context, String message) async {
  await showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text('Location required', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
      content: Text(message),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(ctx),
          style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
          child: const Text('OK'),
        ),
      ],
    ),
  );
}
