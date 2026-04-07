class RiderSession {
  const RiderSession({
    required this.token,
    required this.userId,
    required this.username,
    required this.role,
    this.riderId,
    this.riderCode,
    this.riderName,
  });

  final String token;
  final int userId;
  final String username;
  final String role;
  final int? riderId;
  final String? riderCode;
  final String? riderName;

  factory RiderSession.fromLoginResponse(Map<String, dynamic> json) {
    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final user = (data['user'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final rider = (data['rider'] as Map<String, dynamic>? ?? <String, dynamic>{});

    return RiderSession(
      token: (data['token'] ?? '') as String,
      userId: (user['id'] ?? 0) as int,
      username: (user['username'] ?? '') as String,
      role: (user['role'] ?? '') as String,
      riderId: rider['id'] as int?,
      riderCode: rider['rider_code'] as String?,
      riderName: rider['name'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'token': token,
        'userId': userId,
        'username': username,
        'role': role,
        'riderId': riderId,
        'riderCode': riderCode,
        'riderName': riderName,
      };

  factory RiderSession.fromJson(Map<String, dynamic> json) => RiderSession(
        token: (json['token'] ?? '') as String,
        userId: (json['userId'] ?? 0) as int,
        username: (json['username'] ?? '') as String,
        role: (json['role'] ?? '') as String,
        riderId: json['riderId'] as int?,
        riderCode: json['riderCode'] as String?,
        riderName: json['riderName'] as String?,
      );
}
