class AdminSession {
  const AdminSession({
    required this.token,
    required this.userId,
    required this.username,
    required this.role,
  });

  final String token;
  final int userId;
  final String username;
  final String role;

  factory AdminSession.fromLoginResponse(Map<String, dynamic> json) {
    final data = (json['data'] as Map<String, dynamic>? ?? <String, dynamic>{});
    final user = (data['user'] as Map<String, dynamic>? ?? <String, dynamic>{});

    return AdminSession(
      token: (data['token'] ?? '') as String,
      userId: (user['id'] ?? 0) as int,
      username: (user['username'] ?? '') as String,
      role: (user['role'] ?? '') as String,
    );
  }

  Map<String, dynamic> toJson() => {
        'token': token,
        'userId': userId,
        'username': username,
        'role': role,
      };

  factory AdminSession.fromJson(Map<String, dynamic> json) => AdminSession(
        token: (json['token'] ?? '') as String,
        userId: (json['userId'] ?? 0) as int,
        username: (json['username'] ?? '') as String,
        role: (json['role'] ?? '') as String,
      );
}
