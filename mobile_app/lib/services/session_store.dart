import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/rider_session.dart';

class SessionStore {
  const SessionStore();

  static const String _sessionKey = 'rider_session';
  static const FlutterSecureStorage _storage = FlutterSecureStorage();

  Future<RiderSession?> loadSession() async {
    final raw = await _storage.read(key: _sessionKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    final json = jsonDecode(raw) as Map<String, dynamic>;
    return RiderSession.fromJson(json);
  }

  Future<void> saveSession(RiderSession session) async {
    await _storage.write(key: _sessionKey, value: jsonEncode(session.toJson()));
  }

  Future<void> clearSession() async {
    await _storage.delete(key: _sessionKey);
  }
}
