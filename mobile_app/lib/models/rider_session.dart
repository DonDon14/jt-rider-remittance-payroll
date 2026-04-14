class RiderSession {
  const RiderSession({
    required this.token,
    required this.userId,
    required this.username,
    required this.role,
    required this.forcePasswordChange,
    this.riderId,
    this.riderCode,
    this.riderName,
  });

  final String token;
  final int userId;
  final String username;
  final String role;
  final bool forcePasswordChange;
  final int? riderId;
  final String? riderCode;
  final String? riderName;

  factory RiderSession.fromLoginResponse(Map<String, dynamic> json) {
    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final user = (data['user'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final rider = (data['rider'] as Map<String, dynamic>? ?? <String, dynamic>{});

    return RiderSession(
      token: _asString(data['token']),
      userId: _asInt(user['id']),
      username: _asString(user['username']),
      role: _asString(user['role']),
      forcePasswordChange: _asBool(user['force_password_change']),
      riderId: _asNullableInt(rider['id']),
      riderCode: _asNullableString(rider['rider_code']),
      riderName: _asNullableString(rider['name']),
    );
  }

  Map<String, dynamic> toJson() => {
        'token': token,
        'userId': userId,
        'username': username,
        'role': role,
        'forcePasswordChange': forcePasswordChange,
        'riderId': riderId,
        'riderCode': riderCode,
        'riderName': riderName,
      };

  factory RiderSession.fromJson(Map<String, dynamic> json) => RiderSession(
        token: _asString(json['token']),
        userId: _asInt(json['userId']),
        username: _asString(json['username']),
        role: _asString(json['role']),
        forcePasswordChange: _asBool(json['forcePasswordChange']),
        riderId: _asNullableInt(json['riderId']),
        riderCode: _asNullableString(json['riderCode']),
        riderName: _asNullableString(json['riderName']),
      );

  static String _asString(dynamic value) => (value ?? '').toString();

  static String? _asNullableString(dynamic value) {
    if (value == null) {
      return null;
    }
    final text = value.toString();
    return text.isEmpty ? null : text;
  }

  static int _asInt(dynamic value) {
    if (value is int) {
      return value;
    }
    return int.tryParse((value ?? '').toString()) ?? 0;
  }

  static int? _asNullableInt(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    return int.tryParse(value.toString());
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
