import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'api_config.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode = 400, Map<String, dynamic>? body}) : body = body ?? {};
  final String message;
  final int statusCode;
  final Map<String, dynamic> body;

  @override
  String toString() => message;
}

class ApiClient {
  String? _token;

  Future<void> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('seas_token');
  }

  Future<void> saveToken(String token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('seas_token', token);
  }

  Future<void> clearToken() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('seas_token');
    await prefs.remove('seas_user');
  }

  /// Revoke Sanctum token when possible, then always clear local session.
  /// Never hangs the UI — API call is best-effort with a short timeout.
  Future<void> logout() async {
    final token = _token;
    try {
      if (token != null && token.isNotEmpty) {
        await post('/logout').timeout(const Duration(seconds: 4));
      }
    } catch (_) {
      // Offline / expired token / server down — still clear locally.
    }
    await clearToken();
  }

  Future<void> saveUser(Map<String, dynamic> user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('seas_user', jsonEncode(user));
  }

  Future<Map<String, dynamic>?> refreshUser() async {
    final res = await get('/me');
    final user = res['user'];
    if (user is Map) {
      final map = Map<String, dynamic>.from(user);
      await saveUser(map);
      return map;
    }
    return null;
  }

  Future<Map<String, dynamic>> updateProfile({required String name, String? phone}) async {
    final res = await put('/me', {
      'name': name,
      if (phone != null) 'phone': phone,
    });
    final user = res['user'];
    if (user is Map) await saveUser(Map<String, dynamic>.from(user));
    return res;
  }

  Future<Map<String, dynamic>> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    return post('/me/password', {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
  }

  Future<Map<String, dynamic>> updateAvatar(String filePath) async {
    final res = await postMultipart(
      path: '/me/avatar',
      fields: const {},
      filePaths: {'avatar': filePath},
    );
    final user = res['user'];
    if (user is Map) await saveUser(Map<String, dynamic>.from(user));
    return res;
  }

  bool get isLoggedIn => _token != null && _token!.isNotEmpty;
  String? get token => _token;

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Map<String, String> get _authHeaders => {
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Future<Map<String, dynamic>> login(String email, String password) async {
    http.Response res;
    try {
      res = await http
          .post(
            Uri.parse('${ApiConfig.baseUrl}/login'),
            headers: _headers,
            body: jsonEncode({
              'email': email,
              'password': password,
              'device_name': 'seas-flutter',
            }),
          )
          .timeout(const Duration(seconds: 20));
    } on SocketException {
      throw ApiException(
        'Cannot reach server (${ApiConfig.baseUrl}). Check internet, or use a debug build for local Wi‑Fi testing.',
        statusCode: 0,
      );
    } on TimeoutException {
      throw ApiException(
        'Login timed out contacting ${ApiConfig.baseUrl}. Try again.',
        statusCode: 0,
      );
    } on http.ClientException catch (e) {
      throw ApiException(
        'Network error: ${e.message}. Server: ${ApiConfig.baseUrl}',
        statusCode: 0,
      );
    }

    Map<String, dynamic> body;
    try {
      body = res.body.isEmpty ? <String, dynamic>{} : Map<String, dynamic>.from(jsonDecode(res.body) as Map);
    } catch (_) {
      throw ApiException(
        res.statusCode == 404
            ? 'Login API not found (404) at ${ApiConfig.baseUrl}/login'
            : 'Unexpected server response (${res.statusCode}).',
        statusCode: res.statusCode,
      );
    }

    if (res.statusCode >= 400) {
      String msg = 'Login failed';
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          msg = first.first.toString();
        }
      } else if (body['message'] != null) {
        msg = body['message'].toString();
      }
      throw ApiException(msg, statusCode: res.statusCode, body: body);
    }
    await saveToken(body['token'] as String);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('seas_user', jsonEncode(body['user']));
    return body;
  }

  /// GET JSON. Optional [client] lets callers cancel in-flight work via `client.close()`.
  Future<Map<String, dynamic>> get(
    String path, {
    Duration? timeout,
    http.Client? client,
  }) async {
    final uri = Uri.parse('${ApiConfig.baseUrl}$path');
    Future<http.Response> send = client != null
        ? client.get(uri, headers: _headers)
        : http.get(uri, headers: _headers);
    if (timeout != null) {
      send = send.timeout(timeout);
    }
    final res = await send;
    return _decode(res);
  }

  /// Binary download (Excel / files). Returns bytes + suggested filename.
  Future<({List<int> bytes, String filename})> getBytes(String path) async {
    final res = await http.get(
      Uri.parse('${ApiConfig.baseUrl}$path'),
      headers: {
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/octet-stream, */*',
        if (_token != null) 'Authorization': 'Bearer $_token',
      },
    );
    if (res.statusCode >= 400) {
      // Reuse JSON error decoding when possible.
      _decode(res);
      throw ApiException('Download failed (${res.statusCode})', statusCode: res.statusCode);
    }
    var filename = 'download.xlsx';
    final cd = res.headers['content-disposition'] ?? res.headers['Content-Disposition'];
    if (cd != null) {
      final m = RegExp(r'filename[^;=\n]*=\s*"?([^";\n]+)"?', caseSensitive: false).firstMatch(cd);
      if (m != null) {
        filename = Uri.decodeFull(m.group(1)!.trim());
      }
    }
    return (bytes: res.bodyBytes, filename: filename);
  }

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? data]) async {
    final res = await http.post(
      Uri.parse('${ApiConfig.baseUrl}$path'),
      headers: _headers,
      body: data == null ? null : jsonEncode(data),
    );
    return _decode(res);
  }

  Future<Map<String, dynamic>> put(String path, [Map<String, dynamic>? data]) async {
    final res = await http.put(
      Uri.parse('${ApiConfig.baseUrl}$path'),
      headers: _headers,
      body: data == null ? null : jsonEncode(data),
    );
    return _decode(res);
  }

  Future<Map<String, dynamic>> delete(String path) async {
    final res = await http.delete(Uri.parse('${ApiConfig.baseUrl}$path'), headers: _headers);
    return _decode(res);
  }

  /// Multipart create/update survey (draft or submit).
  Future<Map<String, dynamic>> postSurveyMultipart({
    required String path,
    required Map<String, String> fields,
    String? dtrPhotoPath,
    String? meterPhotoPath,
    String? ctPhotoPath,
    List<int>? dtrPhotoBytes,
    List<int>? meterPhotoBytes,
    List<int>? ctPhotoBytes,
  }) async {
    final req = http.MultipartRequest('POST', Uri.parse('${ApiConfig.baseUrl}$path'));
    req.headers.addAll(_authHeaders);
    req.fields.addAll(fields);

    if (dtrPhotoBytes != null) {
      req.files.add(http.MultipartFile.fromBytes('dtr_overall_photo', dtrPhotoBytes, filename: 'dtr.jpg'));
    } else if (dtrPhotoPath != null && File(dtrPhotoPath).existsSync()) {
      req.files.add(await http.MultipartFile.fromPath('dtr_overall_photo', dtrPhotoPath));
    }
    if (meterPhotoBytes != null) {
      req.files.add(http.MultipartFile.fromBytes('smart_meter_photo', meterPhotoBytes, filename: 'meter.jpg'));
    } else if (meterPhotoPath != null && File(meterPhotoPath).existsSync()) {
      req.files.add(await http.MultipartFile.fromPath('smart_meter_photo', meterPhotoPath));
    }
    if (ctPhotoBytes != null) {
      req.files.add(http.MultipartFile.fromBytes('ct_ratio_photo', ctPhotoBytes, filename: 'ct.jpg'));
    } else if (ctPhotoPath != null && File(ctPhotoPath).existsSync()) {
      req.files.add(await http.MultipartFile.fromPath('ct_ratio_photo', ctPhotoPath));
    }

    final streamed = await req.send();
    final res = await http.Response.fromStream(streamed);
    return _decode(res);
  }

  Future<Map<String, dynamic>> postMultipart({
    required String path,
    required Map<String, String> fields,
    Map<String, String>? filePaths,
    Map<String, List<int>>? fileBytes,
  }) async {
    final req = http.MultipartRequest('POST', Uri.parse('${ApiConfig.baseUrl}$path'));
    req.headers.addAll(_authHeaders);
    req.fields.addAll(fields);
    if (filePaths != null) {
      for (final e in filePaths.entries) {
        if (File(e.value).existsSync()) {
          req.files.add(await http.MultipartFile.fromPath(e.key, e.value));
        }
      }
    }
    if (fileBytes != null) {
      for (final e in fileBytes.entries) {
        req.files.add(http.MultipartFile.fromBytes(e.key, e.value, filename: '${e.key}.jpg'));
      }
    }
    final streamed = await req.send();
    final res = await http.Response.fromStream(streamed);
    // #region agent log
    var jsonOk = false;
    try {
      if (res.body.isNotEmpty) {
        jsonDecode(res.body);
        jsonOk = true;
      } else {
        jsonOk = true;
      }
    } catch (_) {
      jsonOk = false;
    }
    http
        .post(
          Uri.parse('http://127.0.0.1:7880/ingest/462b9acf-aae8-43ed-8500-97bbe6dedf80'),
          headers: {'Content-Type': 'application/json', 'X-Debug-Session-Id': 'a2382b'},
          body: jsonEncode({
            'sessionId': 'a2382b',
            'runId': 'pre-fix',
            'hypothesisId': 'H3',
            'location': 'api_client.dart:postMultipart',
            'message': 'multipart response',
            'data': {
              'path': path,
              'statusCode': res.statusCode,
              'jsonOk': jsonOk,
              'bodyLen': res.body.length,
              'fileCount': req.files.length,
              'fieldCount': fields.length,
            },
            'timestamp': DateTime.now().millisecondsSinceEpoch,
          }),
        )
        .catchError((_) => http.Response('', 500));
    // #endregion
    return _decode(res);
  }

  Map<String, dynamic> _decode(http.Response res) {
    dynamic body;
    final raw = res.body;
    final trimmed = raw.trimLeft();
    final looksHtml = trimmed.startsWith('<!') || trimmed.toLowerCase().startsWith('<html');
    try {
      body = raw.isEmpty ? {} : jsonDecode(raw);
    } catch (_) {
      body = <String, dynamic>{
        'message': looksHtml
            ? 'Server returned HTML instead of JSON (often PHP/Laravel crash or wrong document root).'
            : (raw.length > 280 ? '${raw.substring(0, 280)}…' : raw),
      };
    }
    final map = body is Map<String, dynamic>
        ? body
        : body is Map
            ? Map<String, dynamic>.from(body)
            : <String, dynamic>{'data': body};
    if (res.statusCode >= 400) {
      String? detail;
      if (map['errors'] is Map) {
        final errors = map['errors'] as Map;
        final first = errors.values.isNotEmpty ? errors.values.first : null;
        if (first is List && first.isNotEmpty) {
          detail = first.first.toString();
        }
      }
      detail ??= map['message']?.toString();
      if (detail != null && detail.trimLeft().startsWith('{')) {
        detail = null; // avoid dumping nested exception JSON blobs
      }
      if (detail != null && detail.length > 400) {
        detail = '${detail.substring(0, 400)}…';
      }

      // Always include HTTP status so "Server Error" is distinguishable from 401/403/422.
      final label = switch (res.statusCode) {
        401 => 'Unauthorized (401)',
        403 => 'Forbidden (403)',
        404 => 'Not found (404)',
        413 => 'Upload too large (413)',
        422 => 'Validation failed (422)',
        500 => 'Server error (500)',
        502 => 'Bad gateway (502)',
        503 => 'Service unavailable (503)',
        _ => 'Request failed (${res.statusCode})',
      };

      final genericServer = detail == null ||
          detail.trim().isEmpty ||
          detail.trim().toLowerCase() == 'server error';
      final msg = genericServer
          ? (looksHtml
              ? '$label — HTML error page (check cPanel Laravel log / storage permissions / .env).'
              : '$label — no useful API message. Check production logs or phpMyAdmin users.')
          : '$label: $detail';
      throw ApiException(msg, statusCode: res.statusCode, body: map);
    }
    return map;
  }
}

final api = ApiClient();
