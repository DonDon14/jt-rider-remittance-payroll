import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../models/admin_session.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class PaginatedResponse {
  const PaginatedResponse({
    required this.items,
    required this.meta,
  });

  final List<Map<String, dynamic>> items;
  final Map<String, dynamic> meta;
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

  Future<AdminSession> login({
    required String username,
    required String password,
    String deviceName = 'flutter-admin',
  }) async {
    final json = await postPublic('login', {
      'username': username,
      'password': password,
      'device_name': deviceName,
    });

    final session = AdminSession.fromLoginResponse(json);
    if (session.role != 'admin') {
      throw ApiException('This mobile app is for admin accounts only.');
    }
    if (session.forcePasswordChange) {
      throw ApiException(
        'Password change is required for this account. Use Forgot Password or the web Change Password page first.',
      );
    }

    return session;
  }

  Future<String> forgotPassword({
    required String username,
    required String recoveryKey,
  }) async {
    final json = await postPublic('forgot-password', <String, dynamic>{
      'username': username,
      'recovery_key': recoveryKey,
    });

    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    return (data['temporary_password'] ?? '').toString();
  }

  Future<void> logout(String token) async {
    await postAuthed('logout', token);
  }

  Future<PaginatedResponse> fetchPendingSubmissions(String token, {int page = 1}) async {
    final json = await getAuthed('admin/pending-submissions', token, query: _pageQuery(page));
    return _paginated(json);
  }

  Future<Map<String, dynamic>> fetchPendingSubmissionDetail(String token, int id) async {
    final json = await getAuthed('admin/pending-submissions/$id', token);
    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<Map<String, dynamic>> approveSubmission(String token, int id, double commissionRate) async {
    final json = await postAuthed('admin/pending-submissions/$id/approve', token, body: {
      'commission_rate': commissionRate,
    });
    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<void> rejectSubmission(String token, int id, String rejectionNote) async {
    await postAuthed('admin/pending-submissions/$id/reject', token, body: {
      'rejection_note': rejectionNote,
    });
  }

  Future<PaginatedResponse> fetchPendingRemittances(String token, {int page = 1}) async {
    final json = await getAuthed('admin/pending-remittances', token, query: _pageQuery(page));
    return _paginated(json);
  }

  Future<Map<String, dynamic>> fetchRemittanceDetail(String token, int deliveryRecordId) async {
    final json = await getAuthed('admin/remittances/$deliveryRecordId', token);
    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<Map<String, dynamic>> saveRemittance(
    String token,
    int deliveryRecordId, {
    required Map<String, dynamic> body,
  }) async {
    final json = await postAuthed('admin/remittances/$deliveryRecordId/collect', token, body: body);
    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<Map<String, dynamic>> deletePendingRemittance(String token, int deliveryRecordId) async {
    final json = await postAuthed('admin/remittances/$deliveryRecordId/delete', token);
    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<PaginatedResponse> fetchShortages(String token, {int page = 1}) async {
    final json = await getAuthed('admin/shortages', token, query: _pageQuery(page));
    return _paginated(json);
  }

  Future<Map<String, dynamic>> collectShortage(
    String token,
    int remittanceId, {
    required String paymentDate,
    required String amount,
    String notes = '',
  }) async {
    final json = await postAuthed('admin/shortages/$remittanceId/collect', token, body: {
      'payment_date': paymentDate,
      'amount': amount,
      'notes': notes,
    });

    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<PaginatedResponse> fetchPayrolls(String token, {int page = 1, String status = ''}) async {
    final query = _pageQuery(page);
    if (status.isNotEmpty) {
      query['payroll_status'] = status;
    }

    final json = await getAuthed('admin/payrolls', token, query: query);
    return _paginated(json);
  }

  Future<List<Map<String, dynamic>>> fetchRiders(String token) async {
    final json = await getAuthed('admin/riders', token);
    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    return ((data['items'] as List<dynamic>? ?? <dynamic>[]))
        .whereType<Map<String, dynamic>>()
        .toList();
  }

  Future<Map<String, dynamic>> generatePayroll(
    String token, {
    required int riderId,
    required String payrollMonth,
    required String cutoffPeriod,
  }) async {
    final json = await postAuthed('admin/payrolls/generate', token, body: {
      'rider_id': riderId,
      'payroll_month': payrollMonth,
      'cutoff_period': cutoffPeriod,
    });
    return (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
  }

  Future<void> releasePayroll(
    String token,
    int id, {
    required String payoutMethod,
    required String payoutReference,
  }) async {
    await postAuthed('admin/payrolls/$id/release', token, body: {
      'payout_method': payoutMethod,
      'payout_reference': payoutReference,
    });
  }

  Future<Map<String, dynamic>> postPublic(String path, Map<String, dynamic> body) async {
    try {
      final response = await _httpClient.post(
        _uri(path),
        headers: const <String, String>{'Content-Type': 'application/json'},
        body: jsonEncode(body),
      );

      return _decode(response);
    } on SocketException {
      throw ApiException('Unable to reach the server. Check the backend URL and your network connection.');
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
      throw ApiException('Unable to reach the server. Check the backend URL and your network connection.');
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
      throw ApiException('Unable to reach the server. Check the backend URL and your network connection.');
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

  Map<String, String> _pageQuery(int page) {
    return <String, String>{
      'page': '$page',
      'per_page': '20',
    };
  }

  PaginatedResponse _paginated(Map<String, dynamic> json) {
    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final items = ((data['items'] as List<dynamic>? ?? <dynamic>[]))
        .whereType<Map<String, dynamic>>()
        .toList();
    final meta = (data['meta'] as Map<String, dynamic>? ?? <String, dynamic>{});

    return PaginatedResponse(items: items, meta: meta);
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
