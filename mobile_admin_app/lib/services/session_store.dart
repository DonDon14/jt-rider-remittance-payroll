import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/admin_session.dart';

class SessionStore {
  const SessionStore();

  static const String _sessionKey = 'admin_session';

  Future<AdminSession?> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_sessionKey);
    if (raw == null || raw.isEmpty) {
      return null;
    }

    final json = jsonDecode(raw) as Map<String, dynamic>;
    return AdminSession.fromJson(json);
  }

  Future<void> saveSession(AdminSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_sessionKey, jsonEncode(session.toJson()));
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_sessionKey);
  }
}
