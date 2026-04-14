import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../models/rider_session.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? httpClient}) : _httpClient = httpClient ?? http.Client();

  final http.Client _httpClient;

  Uri _uri(String path, [Map<String, String>? query]) {
    final base = Uri.parse(AppConfig.resolvedApiBaseUrl);
    final basePath = base.path.endsWith('/') ? base.path.substring(0, base.path.length - 1) : base.path;
    final normalizedPath = '$basePath/$path';
    return base.replace(path: normalizedPath, queryParameters: query);
  }

  Future<Map<String, dynamic>> postPublic(String path, Map<String, dynamic> body) async {
    try {
      final response = await _httpClient.post(
        _uri(path),
        headers: <String, String>{'Content-Type': 'application/json'},
        body: jsonEncode(body),
      );

      return _decode(response);
    } on SocketException {
      throw ApiException('Unable to reach the server. Check that your phone and PC are on the same network and the backend URL is correct.');
    } on HttpException {
      throw ApiException('The server connection failed.');
    } on FormatException {
      throw ApiException('The server returned an invalid response.');
    } on StateError catch (error) {
      throw ApiException(error.message.toString());
    }
  }

  Future<Map<String, dynamic>> getAuthed(
    String path,
    String token, {
    Map<String, String>? query,
  }) async {
    try {
      final response = await _httpClient.get(
        _uri(path, query),
        headers: _headers(token),
      );

      return _decode(response);
    } on SocketException {
      throw ApiException('Unable to reach the server. Check your network connection and backend URL.');
    } on HttpException {
      throw ApiException('The server connection failed.');
    } on FormatException {
      throw ApiException('The server returned an invalid response.');
    } on StateError catch (error) {
      throw ApiException(error.message.toString());
    }
  }

  Future<Map<String, dynamic>> postAuthed(
    String path,
    String token, {
    Map<String, dynamic>? body,
  }) async {
    try {
      final response = await _httpClient.post(
        _uri(path),
        headers: _headers(token),
        body: jsonEncode(body ?? <String, dynamic>{}),
      );

      return _decode(response);
    } on SocketException {
      throw ApiException('Unable to reach the server. Check your network connection and backend URL.');
    } on HttpException {
      throw ApiException('The server connection failed.');
    } on FormatException {
      throw ApiException('The server returned an invalid response.');
    } on StateError catch (error) {
      throw ApiException(error.message.toString());
    }
  }

  Future<RiderSession> login({
    required String username,
    required String password,
    String deviceName = 'flutter',
  }) async {
    final body = <String, dynamic>{
      'username': username,
      'password': password,
      'device_name': deviceName,
    };

    final json = await postPublic('login', body);
    final session = RiderSession.fromLoginResponse(json);
    if (session.forcePasswordChange) {
      throw ApiException(
        'Password change is required for this account. Use Forgot Password or the web Change Password page first.',
      );
    }

    return session;
  }

  Future<String> forgotPassword({
    required String username,
    required String riderCode,
    required String contactNumber,
  }) async {
    final json = await postPublic('forgot-password', <String, dynamic>{
      'username': username,
      'rider_code': riderCode,
      'contact_number': contactNumber,
    });

    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    return (data['temporary_password'] ?? '').toString();
  }

  Future<void> logout(String token) async {
    await postAuthed('logout', token);
  }

  Future<List<int>> downloadPayrollPdf(String token, int payrollId) async {
    try {
      http.Response response = await _httpClient.get(
        _uri('rider/payroll/$payrollId/pdf'),
        headers: _headers(token),
      );

      // Backward compatibility for older/newer backend route naming.
      if (response.statusCode == 404) {
        response = await _httpClient.get(
          _uri('rider/payrolls/$payrollId/pdf'),
          headers: _headers(token),
        );
      }

      if (response.statusCode >= 400) {
        final body = response.body.trim();
        if (body.isNotEmpty) {
          final parsed = jsonDecode(body);
          if (parsed is Map<String, dynamic>) {
            final msg = (parsed['message'] ?? '').toString().trim();
            if (msg.isNotEmpty) {
              throw ApiException(msg, statusCode: response.statusCode);
            }
          }
        }
        throw ApiException('Unable to download payslip (HTTP ${response.statusCode}).', statusCode: response.statusCode);
      }

      return response.bodyBytes;
    } on SocketException {
      throw ApiException('Unable to reach the server. Check your network connection and backend URL.');
    } on HttpException {
      throw ApiException('The server connection failed.');
    } on FormatException {
      throw ApiException('The server returned an invalid response.');
    } on StateError catch (error) {
      throw ApiException(error.message.toString());
    }
  }

  Map<String, String> _headers(String token) {
    return <String, String>{
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.body.trim().isEmpty) {
      throw ApiException('The server returned an empty response.', statusCode: response.statusCode);
    }

    final json = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 400 || json['status'] == 'error') {
      throw ApiException(
        (json['message'] ??
                (json['errors'] is Map && (json['errors'] as Map).isNotEmpty
                    ? (json['errors'] as Map).values.first
                    : 'Request failed.'))
            .toString(),
        statusCode: response.statusCode,
      );
    }

    return json;
  }
}
