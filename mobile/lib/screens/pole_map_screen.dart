import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:latlong2/latlong.dart';
import '../core/api_client.dart';
import '../core/location_gate.dart';
import '../core/pole_map_export.dart';
import '../theme/seas_colors.dart';
import '../widgets/pole_form_sheet.dart';
import '../widgets/seas_glass_header.dart';

/// Map pinning for poles under a DTR — OSM tiles (no Google API key).
class PoleMapScreen extends StatefulWidget {
  const PoleMapScreen({
    super.key,
    required this.dtrSurvey,
    this.startInPinMode = true,
  });

  final Map<String, dynamic> dtrSurvey;
  final bool startInPinMode;

  @override
  State<PoleMapScreen> createState() => _PoleMapScreenState();
}

class _PoleMapScreenState extends State<PoleMapScreen> {
  final MapController _map = MapController();
  bool loading = true;
  String? error;
  List poles = [];
  LatLng? userPos;
  LatLng? draftPin;
  bool pinMode = true;
  bool locating = false;

  int get surveyId => widget.dtrSurvey['id'] as int;

  String get dtrName => '${widget.dtrSurvey['dtr_name'] ?? '—'}';
  String get dtrCode => '${widget.dtrSurvey['dtr_code'] ?? '—'}';

  @override
  void initState() {
    super.initState();
    pinMode = widget.startInPinMode;
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    await Future.wait([_load(), _goMyLocation(silent: true)]);
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final res = await api.get('/consumer/$surveyId/poles');
      poles = (res['poles'] as List?) ?? [];
      if (res['survey'] is Map) {
        widget.dtrSurvey.addAll(Map<String, dynamic>.from(res['survey'] as Map));
      }
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    }
    if (mounted) setState(() => loading = false);
    _fitToContent();
  }

  List<_PolePoint> get _polePoints {
    final out = <_PolePoint>[];
    for (final raw in poles) {
      final p = Map<String, dynamic>.from(raw as Map);
      final lat = _toDouble(p['latitude']);
      final lng = _toDouble(p['longitude']);
      if (lat == null || lng == null) continue;
      out.add(_PolePoint(
        id: (p['id'] as num?)?.toInt() ?? 0,
        poleNo: '${p['pole_no'] ?? ''}',
        point: LatLng(lat, lng),
        data: p,
      ));
    }
    return out;
  }

  double? _toDouble(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    return double.tryParse('$v');
  }

  Future<void> _goMyLocation({bool silent = false}) async {
    setState(() => locating = true);
    try {
      Position? pos;
      if (silent) {
        try {
          final serviceOn = await Geolocator.isLocationServiceEnabled();
          var perm = await Geolocator.checkPermission();
          if (serviceOn &&
              (perm == LocationPermission.whileInUse || perm == LocationPermission.always)) {
            pos = await Geolocator.getCurrentPosition(
              locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
            );
          }
        } catch (_) {}
      } else {
        pos = await ensureDeviceLocation(context, purpose: 'show your location on the map');
      }
      if (pos != null && mounted) {
        final ll = LatLng(pos.latitude, pos.longitude);
        setState(() => userPos = ll);
        _map.move(ll, 17);
      }
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  void _fitToContent() {
    final pts = _polePoints.map((e) => e.point).toList();
    if (userPos != null) pts.add(userPos!);
    if (draftPin != null) pts.add(draftPin!);
    if (pts.isEmpty) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (pts.length == 1) {
        _map.move(pts.first, 17);
        return;
      }
      final bounds = LatLngBounds.fromPoints(pts);
      _map.fitCamera(CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(48)));
    });
  }

  void _onMapTap(TapPosition tap, LatLng latlng) {
    if (!pinMode) return;
    setState(() => draftPin = latlng);
  }

  void _onMapLongPress(TapPosition tap, LatLng latlng) {
    setState(() {
      pinMode = true;
      draftPin = latlng;
    });
  }

  Future<void> _confirmDraftPin() async {
    final pin = draftPin;
    if (pin == null) return;
    final result = await showPoleFormSheet(
      context: context,
      poles: poles,
      initialLatitude: pin.latitude.toStringAsFixed(7),
      initialLongitude: pin.longitude.toStringAsFixed(7),
      captureGpsIfMissing: false,
    );
    if (result == null || !mounted) return;
    if (result.sourceType == 'previous_pole' && result.previousPoleId == null) {
      setState(() => error = 'Please select a previous pole.');
      return;
    }
    try {
      if (result.hasPhoto) {
        await api.postMultipart(
          path: '/consumer/$surveyId/poles',
          fields: result.toMultipartFields(),
          filePaths: result.photoPath == null ? null : {'photo': result.photoPath!},
          fileBytes: result.photoPath != null || result.photoBytes == null
              ? null
              : {'photo': result.photoBytes!},
        );
      } else {
        await api.post('/consumer/$surveyId/poles', result.toPayload());
      }
      setState(() => draftPin = null);
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('Pole ${result.poleNo} pinned on map'),
        backgroundColor: SeasColors.ink950,
      ));
    } catch (e) {
      if (mounted) setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _pinAtMyLocation() async {
    await _goMyLocation();
    if (userPos == null || !mounted) return;
    setState(() {
      pinMode = true;
      draftPin = userPos;
    });
  }

  Future<void> _downloadPins() async {
    final withCoords = poles.where((p) {
      final m = Map<String, dynamic>.from(p as Map);
      return _toDouble(m['latitude']) != null && _toDouble(m['longitude']) != null;
    }).toList();
    if (withCoords.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('No poles with coordinates yet — drop pins first.'),
        backgroundColor: SeasColors.voltDeep,
      ));
      return;
    }
    try {
      final base = await PoleMapExport.downloadAll(
        withCoords,
        dtrName: dtrName,
        dtrCode: dtrCode,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('Downloaded $base.csv + .html + .geojson'),
        backgroundColor: SeasColors.ink950,
        duration: const Duration(seconds: 4),
      ));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString().replaceFirst('Exception: ', '')),
        backgroundColor: SeasColors.voltDeep,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    final points = _polePoints;
    final center = draftPin ?? userPos ?? (points.isNotEmpty ? points.last.point : const LatLng(22.9734, 78.6569));

    return SeasPremiumScaffold(
      header: SeasGlassHeader(
        title: 'Pole Map',
        subtitle: '$dtrName · OSM pins',
        onBack: () => Navigator.pop(context, true),
        trailing: IconButton(
          tooltip: 'Download pins',
          onPressed: _downloadPins,
          icon: const Icon(Icons.download_rounded, color: SeasColors.ink950),
        ),
      ),
      bottom: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (draftPin != null)
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: SeasColors.ink950,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.push_pin_rounded, color: SeasColors.volt, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Pin ${draftPin!.latitude.toStringAsFixed(5)}, ${draftPin!.longitude.toStringAsFixed(5)}',
                          style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12),
                        ),
                      ),
                      TextButton(
                        onPressed: () => setState(() => draftPin = null),
                        child: const Text('Clear', style: TextStyle(color: Colors.white70)),
                      ),
                      FilledButton(
                        onPressed: _confirmDraftPin,
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.volt, visualDensity: VisualDensity.compact),
                        child: const Text('Confirm pin'),
                      ),
                    ],
                  ),
                ),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: locating ? null : () => _goMyLocation(),
                      icon: locating
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: SeasColors.volt))
                          : const Icon(Icons.my_location_rounded, size: 18),
                      label: const Text('My Location'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.ink950,
                        side: const BorderSide(color: SeasColors.ink100),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () {
                        setState(() => pinMode = !pinMode);
                        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                          content: Text(pinMode ? 'Tap map to drop pin' : 'Pin mode off — pan/zoom only'),
                          backgroundColor: SeasColors.ink950,
                          duration: const Duration(seconds: 2),
                        ));
                      },
                      icon: Icon(pinMode ? Icons.push_pin_rounded : Icons.push_pin_outlined, size: 18),
                      label: Text(pinMode ? 'Drop Pin ON' : 'Drop Pin'),
                      style: FilledButton.styleFrom(
                        backgroundColor: pinMode ? SeasColors.volt : SeasColors.ink950,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _pinAtMyLocation,
                      icon: const Icon(Icons.add_location_alt_rounded, size: 18),
                      label: const Text('Pin at GPS'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: SeasColors.voltDeep,
                        side: BorderSide(color: SeasColors.volt.withValues(alpha: 0.35)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _downloadPins,
                      icon: const Icon(Icons.map_outlined, size: 18),
                      label: const Text('Download Map'),
                      style: FilledButton.styleFrom(
                        backgroundColor: SeasColors.ink950,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
      body: loading && poles.isEmpty
          ? const Center(child: CircularProgressIndicator(color: SeasColors.volt))
          : Stack(
              children: [
                FlutterMap(
                  mapController: _map,
                  options: MapOptions(
                    initialCenter: center,
                    initialZoom: 16,
                    onTap: _onMapTap,
                    onLongPress: _onMapLongPress,
                    interactionOptions: const InteractionOptions(
                      flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
                    ),
                  ),
                  children: [
                    TileLayer(
                      urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'com.srihari.seas.seas_mobile',
                      maxZoom: 19,
                    ),
                    if (userPos != null)
                      MarkerLayer(
                        markers: [
                          Marker(
                            point: userPos!,
                            width: 44,
                            height: 44,
                            child: Container(
                              decoration: BoxDecoration(
                                color: const Color(0xFF2563EB),
                                shape: BoxShape.circle,
                                border: Border.all(color: Colors.white, width: 3),
                                boxShadow: const [
                                  BoxShadow(color: Color(0x662563EB), blurRadius: 12, offset: Offset(0, 4)),
                                ],
                              ),
                              child: const Icon(Icons.person_pin_circle, color: Colors.white, size: 22),
                            ),
                          ),
                        ],
                      ),
                    MarkerLayer(
                      markers: [
                        for (final p in points)
                          Marker(
                            point: p.point,
                            width: 72,
                            height: 56,
                            alignment: Alignment.topCenter,
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: SeasColors.volt,
                                    borderRadius: BorderRadius.circular(8),
                                    boxShadow: [
                                      BoxShadow(color: SeasColors.volt.withValues(alpha: 0.4), blurRadius: 8, offset: const Offset(0, 3)),
                                    ],
                                  ),
                                  child: Text(
                                    p.poleNo,
                                    style: GoogleFonts.plusJakartaSans(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 11,
                                    ),
                                  ),
                                ),
                                const Icon(Icons.location_on, color: SeasColors.volt, size: 28),
                              ],
                            ),
                          ),
                      ],
                    ),
                    if (draftPin != null)
                      MarkerLayer(
                        markers: [
                          Marker(
                            point: draftPin!,
                            width: 48,
                            height: 48,
                            child: const Icon(Icons.place_rounded, color: SeasColors.ink950, size: 44),
                          ),
                        ],
                      ),
                  ],
                ),
                Positioned(
                  left: 12,
                  right: 12,
                  top: 12,
                  child: Column(
                    children: [
                      if (error != null)
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          margin: const EdgeInsets.only(bottom: 8),
                          decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(12)),
                          child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600, fontSize: 12)),
                        ),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.94),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: SeasColors.ink100),
                          boxShadow: SeasShadows.card,
                        ),
                        child: Text(
                          pinMode
                              ? 'Tap map to drop pin · ${points.length} poles shown · long-press also works'
                              : 'Pin mode off · ${points.length} poles · turn Drop Pin ON to add',
                          style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w600, color: SeasColors.ink700),
                        ),
                      ),
                    ],
                  ),
                ),
                Positioned(
                  right: 12,
                  bottom: 12,
                  child: Material(
                    color: Colors.white,
                    shape: const CircleBorder(),
                    elevation: 2,
                    child: IconButton(
                      tooltip: 'Fit all pins',
                      onPressed: _fitToContent,
                      icon: const Icon(Icons.zoom_out_map_rounded, color: SeasColors.ink950),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _PolePoint {
  const _PolePoint({
    required this.id,
    required this.poleNo,
    required this.point,
    required this.data,
  });
  final int id;
  final String poleNo;
  final LatLng point;
  final Map<String, dynamic> data;
}
