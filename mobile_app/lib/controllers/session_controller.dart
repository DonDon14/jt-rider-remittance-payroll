import 'package:flutter/foundation.dart';

import '../models/rider_session.dart';
import '../services/api_client.dart';
import '../services/session_store.dart';

class SessionController extends ChangeNotifier {
  SessionController({
    required ApiClient apiClient,
    required SessionStore sessionStore,
  })  : _apiClient = apiClient,
        _sessionStore = sessionStore;

  final ApiClient _apiClient;
  final SessionStore _sessionStore;

  RiderSession? _session;
  bool _isLoading = true;

  RiderSession? get session => _session;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _session != null;

  Future<void> hydrate() async {
    _isLoading = true;
    notifyListeners();
    _session = await _sessionStore.loadSession();
    _isLoading = false;
    notifyListeners();
  }

  Future<void> login({
    required String username,
    required String password,
  }) async {
    final session = await _apiClient.login(username: username, password: password);
    await _sessionStore.saveSession(session);
    _session = session;
    notifyListeners();
  }

  Future<String> forgotPassword({
    required String username,
    required String riderCode,
    required String contactNumber,
  }) {
    return _apiClient.forgotPassword(
      username: username,
      riderCode: riderCode,
      contactNumber: contactNumber,
    );
  }

  Future<void> logout() async {
    final token = _session?.token;
    if (token != null && token.isNotEmpty) {
      try {
        await _apiClient.logout(token);
      } catch (_) {
      }
    }

    await _sessionStore.clearSession();
    _session = null;
    notifyListeners();
  }
}
