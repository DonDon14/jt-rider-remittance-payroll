class AdminSession {
  const AdminSession({
    required this.token,
    required this.userId,
    required this.username,
    required this.role,
    required this.forcePasswordChange,
  });

  final String token;
  final int userId;
  final String username;
  final String role;
  final bool forcePasswordChange;

  factory AdminSession.fromLoginResponse(Map<String, dynamic> json) {
    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final user = (data['user'] as Map<String, dynamic>? ?? <String, dynamic>{});

    return AdminSession(
      token: _asString(data['token']),
      userId: _asInt(user['id']),
      username: _asString(user['username']),
      role: _asString(user['role']),
      forcePasswordChange: _asBool(user['force_password_change']),
    );
  }

  Map<String, dynamic> toJson() => {
        'token': token,
        'userId': userId,
        'username': username,
        'role': role,
        'forcePasswordChange': forcePasswordChange,
      };

  factory AdminSession.fromJson(Map<String, dynamic> json) => AdminSession(
        token: _asString(json['token']),
        userId: _asInt(json['userId']),
        username: _asString(json['username']),
        role: _asString(json['role']),
        forcePasswordChange: _asBool(json['forcePasswordChange']),
      );

  static String _asString(dynamic value) => (value ?? '').toString();

  static int _asInt(dynamic value) {
    if (value is int) {
      return value;
    }
    return int.tryParse((value ?? '').toString()) ?? 0;
  }

  static bool _asBool(dynamic value) {
    if (value is bool) {
      return value;
    }
    if (value is int) {
      return value != 0;
    }
    final text = (value ?? '').toString().trim().toLowerCase();
    return text == '1' || text == 'true' || text == 'yes';
  }
}
