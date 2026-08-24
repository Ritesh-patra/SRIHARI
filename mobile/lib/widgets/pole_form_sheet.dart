import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import '../core/location_gate.dart';
import '../theme/seas_colors.dart';
import 'seas_select.dart';

/// Result of the add/edit pole bottom sheet (caller posts to API).
class PoleFormResult {
  const PoleFormResult({
    required this.poleNo,
    required this.sourceType,
    required this.housesConnected,
    this.previousPoleId,
    this.latitude,
    this.longitude,
    this.photoPath,
    this.photoBytes,
  });

  final String poleNo;
  final String sourceType;
  final int housesConnected;
  final int? previousPoleId;
  final String? latitude;
  final String? longitude;
  final String? photoPath;
  final Uint8List? photoBytes;

  bool get hasPhoto => photoBytes != null || photoPath != null;

  Map<String, dynamic> toPayload() => {
        'pole_no': poleNo,
        'source_type': sourceType,
        if (previousPoleId != null) 'previous_pole_id': previousPoleId,
        'houses_connected': housesConnected,
        if (latitude != null && latitude!.trim().isNotEmpty) 'latitude': latitude,
        if (longitude != null && longitude!.trim().isNotEmpty) 'longitude': longitude,
      };

  /// Multipart form fields (photo is attached separately by the caller).
  Map<String, String> toMultipartFields() =>
      toPayload().map((key, value) => MapEntry(key, '$value'));
}

String suggestNextPoleNo(List poles) {
  var maxN = 0;
  String prefix = 'A';
  var width = 1;
  var usePad = false;
  var found = false;

  for (final p in poles) {
    final raw = '${p['pole_no']}'.trim();
    final m = RegExp(r'^(.*?)(\d+)$').firstMatch(raw);
    if (m == null) continue;
    final digits = m.group(2)!;
    final n = int.tryParse(digits) ?? 0;
    if (found && n <= maxN) continue;
    maxN = n;
    prefix = m.group(1) ?? 'A';
    width = digits.length;
    usePad = digits.startsWith('0') || prefix.toLowerCase().contains('pole');
    found = true;
  }

  if (!found) return 'A1';
  final next = maxN + 1;
  final numStr = usePad ? next.toString().padLeft(width, '0') : '$next';
  return '$prefix$numStr';
}

Map<String, dynamic>? latestPole(List poles) {
  if (poles.isEmpty) return null;
  Map<String, dynamic>? best;
  var bestN = -1;
  var bestId = -1;
  for (final p in poles) {
    final map = Map<String, dynamic>.from(p as Map);
    final id = (map['id'] as num?)?.toInt() ?? 0;
    final raw = '${map['pole_no']}'.trim();
    final m = RegExp(r'(\d+)$').firstMatch(raw);
    final n = m != null ? (int.tryParse(m.group(1)!) ?? -1) : -1;
    if (n > bestN || (n == bestN && id > bestId) || (best == null && id > bestId)) {
      bestN = n;
      bestId = id;
      best = map;
    }
  }
  return best;
}

/// Add / edit pole sheet. Pass [initialLatitude]/[initialLongitude] from map pin to skip GPS gate.
Future<PoleFormResult?> showPoleFormSheet({
  required BuildContext context,
  required List poles,
  Map<String, dynamic>? existing,
  String? initialLatitude,
  String? initialLongitude,
  bool captureGpsIfMissing = true,
}) async {
  final editing = existing != null;
  final noCtrl = TextEditingController(text: editing ? '${existing['pole_no']}' : suggestNextPoleNo(poles));
  final housesCtrl = TextEditingController(
    text: editing ? '${existing['houses_connected'] ?? 0}' : '0',
  );

  String? latitude = editing ? existing['latitude']?.toString() : initialLatitude;
  String? longitude = editing ? existing['longitude']?.toString() : initialLongitude;

  if (!editing &&
      captureGpsIfMissing &&
      (latitude == null || longitude == null || latitude.isEmpty || longitude.isEmpty)) {
    final pos = await ensureDeviceLocation(context, purpose: 'add a pole');
    if (pos == null || !context.mounted) {
      noCtrl.dispose();
      housesCtrl.dispose();
      return null;
    }
    latitude = pos.latitude.toStringAsFixed(7);
    longitude = pos.longitude.toStringAsFixed(7);
  }

  final latest = editing ? null : latestPole(poles);
  String sourceType;
  int? previousPoleId;
  if (editing) {
    sourceType = '${existing['source_type'] ?? 'dtr'}';
    if (sourceType != 'previous_pole') sourceType = 'dtr';
    previousPoleId = existing['previous_pole_id'] as int?;
    if (previousPoleId == null && existing['previous_pole'] is Map) {
      previousPoleId = existing['previous_pole']['id'] as int?;
    }
  } else if (latest != null) {
    sourceType = 'previous_pole';
    previousPoleId = (latest['id'] as num?)?.toInt();
  } else {
    sourceType = 'dtr';
    previousPoleId = null;
  }

  final otherPoles = poles.where((p) => !editing || p['id'] != existing['id']).toList();
  final suggestedPrevLabel = latest == null ? null : '${latest['pole_no']}';

  final picker = ImagePicker();
  String? photoPath;
  Uint8List? photoBytes;

  final ok = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) {
      return StatefulBuilder(builder: (ctx, setModal) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
          child: Container(
            decoration: BoxDecoration(
              color: SeasColors.white.withValues(alpha: 0.97),
              borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
              border: Border.all(color: SeasColors.ink100),
              boxShadow: SeasShadows.seasLg,
            ),
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)),
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  editing ? 'Edit Pole' : 'Add Pole',
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 20, color: SeasColors.ink950),
                ),
                const SizedBox(height: 4),
                Text(
                  editing
                      ? 'Correct pole number, source, or expected consumers.'
                      : 'Pin / GPS location locked. Set pole number, source, and expected houses.',
                  style: const TextStyle(color: SeasColors.ink400, fontSize: 13),
                ),
                if (!editing) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: SeasColors.voltSoft,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: SeasColors.volt.withValues(alpha: 0.25)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.place_rounded, size: 18, color: SeasColors.volt),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Location: ${latitude ?? '—'}, ${longitude ?? '—'}',
                                style: GoogleFonts.plusJakartaSans(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 12,
                                  color: SeasColors.voltDeep,
                                ),
                              ),
                            ),
                            TextButton(
                              onPressed: () async {
                                try {
                                  final pos = await Geolocator.getCurrentPosition(
                                    locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
                                  );
                                  setModal(() {
                                    latitude = pos.latitude.toStringAsFixed(7);
                                    longitude = pos.longitude.toStringAsFixed(7);
                                  });
                                } catch (_) {}
                              },
                              child: const Text('GPS', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
                            ),
                          ],
                        ),
                        if (suggestedPrevLabel != null) ...[
                          const SizedBox(height: 6),
                          Text(
                            'Suggested previous pole: $suggestedPrevLabel',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w600,
                              fontSize: 12,
                              color: SeasColors.voltDeep,
                            ),
                          ),
                        ] else
                          Text(
                            'First pole on this DTR — source defaults to DTR',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w600,
                              fontSize: 12,
                              color: SeasColors.voltDeep,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                SeasTextField(label: 'Pole Number', controller: noCtrl, hint: 'A1'),
                const SizedBox(height: 12),
                SeasTextField(
                  label: 'Expected houses / consumers connected *',
                  controller: housesCtrl,
                  hint: 'e.g. 8',
                  keyboardType: TextInputType.number,
                ),
                const SizedBox(height: 12),
                Text('Pole Source', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13)),
                const SizedBox(height: 8),
                Row(children: [
                  Expanded(
                    child: _SourceChip(
                      label: 'From DTR',
                      selected: sourceType == 'dtr',
                      onTap: () => setModal(() {
                        sourceType = 'dtr';
                        previousPoleId = null;
                      }),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _SourceChip(
                      label: 'Previous Pole',
                      selected: sourceType == 'previous_pole',
                      onTap: () => setModal(() {
                        sourceType = 'previous_pole';
                        previousPoleId ??= (latest?['id'] as num?)?.toInt() ??
                            (otherPoles.isNotEmpty ? (otherPoles.last['id'] as num?)?.toInt() : null);
                      }),
                    ),
                  ),
                ]),
                if (sourceType == 'previous_pole') ...[
                  const SizedBox(height: 12),
                  SeasSelectField(
                    label: 'Select Previous Pole',
                    hint: otherPoles.isEmpty ? 'Add another pole first' : 'Choose source pole',
                    value: previousPoleId,
                    options: otherPoles.map((p) => SeasSelectOption(value: p['id'], label: '${p['pole_no']}')).toList(),
                    onSelected: (o) => setModal(() => previousPoleId = o.value as int?),
                  ),
                ],
                const SizedBox(height: 12),
                Text('Pole Photo (optional)', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13)),
                const SizedBox(height: 8),
                InkWell(
                  onTap: () async {
                    final x = await picker.pickImage(
                      source: ImageSource.camera,
                      imageQuality: 72,
                      maxWidth: 1600,
                    );
                    if (x == null) return;
                    Uint8List? bytes;
                    try {
                      bytes = await x.readAsBytes();
                    } catch (_) {}
                    setModal(() {
                      photoPath = x.path;
                      photoBytes = bytes;
                    });
                  },
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    height: 110,
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: SeasColors.canvasSoft,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: SeasColors.ink100),
                      image: photoBytes != null
                          ? DecorationImage(image: MemoryImage(photoBytes!), fit: BoxFit.cover)
                          : null,
                    ),
                    alignment: Alignment.center,
                    child: photoBytes == null
                        ? Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.photo_camera_outlined, color: SeasColors.volt),
                              const SizedBox(height: 6),
                              Text(
                                'Pole ka photo lein (optional)',
                                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 12),
                              ),
                            ],
                          )
                        : null,
                  ),
                ),
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: () => Navigator.pop(ctx, true),
                  style: FilledButton.styleFrom(
                    backgroundColor: SeasColors.volt,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: Text(editing ? 'Update Pole' : 'Save Pole'),
                ),
              ],
            ),
          ),
        );
      });
    },
  );

  final poleNo = noCtrl.text.trim().toUpperCase();
  final houses = int.tryParse(housesCtrl.text.trim()) ?? 0;
  WidgetsBinding.instance.addPostFrameCallback((_) {
    noCtrl.dispose();
    housesCtrl.dispose();
  });

  if (ok != true) return null;
  if (sourceType == 'previous_pole' && previousPoleId == null) {
    return null;
  }
  if (houses < 0) return null;
  if (!editing && (latitude == null || longitude == null || latitude!.isEmpty || longitude!.isEmpty)) {
    return null;
  }

  return PoleFormResult(
    poleNo: poleNo,
    sourceType: sourceType,
    housesConnected: houses,
    previousPoleId: previousPoleId,
    latitude: latitude,
    longitude: longitude,
    photoPath: photoPath,
    photoBytes: photoBytes,
  );
}

class _SourceChip extends StatelessWidget {
  const _SourceChip({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? SeasColors.volt : SeasColors.canvasSoft,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(color: selected ? Colors.white : SeasColors.ink950, fontWeight: FontWeight.w700),
          ),
        ),
      ),
    );
  }
}
