import 'dart:convert';

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
    final base = Uri.parse(AppConfig.apiBaseUrl);
    final normalizedPath = '${base.path.replaceFirst(RegExp(r'/$'), '')}/$path';
    return base.replace(path: normalizedPath, queryParameters: query);
  }

  Future<Map<String, dynamic>> postPublic(String path, Map<String, dynamic> body) async {
    final response = await _httpClient.post(
      _uri(path),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );

    return _decode(response);
  }

  Future<Map<String, dynamic>> getAuthed(
    String path,
    String token, {
    Map<String, String>? query,
  }) async {
    final response = await _httpClient.get(
      _uri(path, query),
      headers: _headers(token),
    );

    return _decode(response);
  }

  Future<Map<String, dynamic>> postAuthed(
    String path,
    String token, {
    Map<String, dynamic>? body,
  }) async {
    final response = await _httpClient.post(
      _uri(path),
      headers: _headers(token),
      body: jsonEncode(body ?? <String, dynamic>{}),
    );

    return _decode(response);
  }

  Future<RiderSession> login({
    required String username,
    required String password,
    String deviceName = 'flutter',
  }) async {
    final json = await postPublic('login', {
      'username': username,
      'password': password,
      'device_name': deviceName,
    });

    return RiderSession.fromLoginResponse(json);
  }

  Future<void> logout(String token) async {
    await postAuthed('logout', token);
  }

  Map<String, String> _headers(String token) => {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      };

  Map<String, dynamic> _decode(http.Response response) {
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
