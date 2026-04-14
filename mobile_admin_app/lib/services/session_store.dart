import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/admin_session.dart';

class SessionStore {
  const SessionStore();

  static const String _sessionKey = 'admin_session';
  static const FlutterSecureStorage _storage = FlutterSecureStorage();

  Future<AdminSession?> loadSession() async {
    final raw = await _storage.read(key: _sessionKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    final json = jsonDecode(raw) as Map<String, dynamic>;
    return AdminSession.fromJson(json);
  }

  Future<void> saveSession(AdminSession session) async {
    await _storage.write(key: _sessionKey, value: jsonEncode(session.toJson()));
  }

  Future<void> clearSession() async {
    await _storage.delete(key: _sessionKey);
  }
}
