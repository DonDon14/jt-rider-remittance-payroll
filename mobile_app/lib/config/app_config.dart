class AppConfig {
  const AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );
  static const String appTitle = String.fromEnvironment(
    'APP_TITLE',
    defaultValue: 'J&T Rider Portal',
  );

  static String get resolvedApiBaseUrl {
    final value = apiBaseUrl.trim();
    if (value.isEmpty) {
      throw StateError(
        'Missing API_BASE_URL. Rebuild the app with --dart-define=API_BASE_URL=https://your-domain.example/api',
      );
    }

    return value;
  }
}
