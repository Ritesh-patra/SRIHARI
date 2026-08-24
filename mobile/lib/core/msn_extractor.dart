/// Extract Meter Serial Number (MSN) from barcode / QR payload.
/// Meter stickers often encode LOA, IMEI, firmware — we only want MSN (e.g. PL00213258).
class MsnExtractor {
  static final _msnToken = RegExp(
    r'\b((?:PL|PH|SM|VT|SE|HP)[A-Z0-9]{5,14})\b',
    caseSensitive: false,
  );
  static final _imei = RegExp(r'^\d{15}$');
  static final _pureMsn = RegExp(r'^[A-Za-z]{1,3}\d{6,12}$');

  /// Returns cleaned MSN or null if payload looks like IMEI / unrelated.
  static String? extract(String raw) {
    final text = raw.trim().toUpperCase().replaceAll(RegExp(r'[\s\-_]'), '');
    if (text.isEmpty) return null;

    // Exact MSN-shaped string
    if (_pureMsn.hasMatch(text) && !_imei.hasMatch(text)) {
      return text;
    }

    // Prefer branded meter serial tokens inside longer payloads
    final match = _msnToken.firstMatch(raw.toUpperCase());
    if (match != null) {
      final v = match.group(1)!.toUpperCase();
      if (!_imei.hasMatch(v)) return v;
    }

    // Reject pure IMEI
    if (_imei.hasMatch(text)) return null;

    // Fallback: alphanumeric 8–14 that is not all digits of length 15
    if (RegExp(r'^[A-Z0-9]{8,14}$').hasMatch(text) && !RegExp(r'^\d{14,15}$').hasMatch(text)) {
      return text;
    }

    return null;
  }
}
