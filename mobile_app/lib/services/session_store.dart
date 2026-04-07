import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/rider_session.dart';

class SessionStore {
  const SessionStore();

  static const String _sessionKey = 'rider_session';

  Future<RiderSession?> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_sessionKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    final json = jsonDecode(raw) as Map<String, dynamic>;
    return RiderSession.fromJson(json);
  }

  Future<void> saveSession(RiderSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_sessionKey, jsonEncode(session.toJson()));
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_sessionKey);
  }
}
