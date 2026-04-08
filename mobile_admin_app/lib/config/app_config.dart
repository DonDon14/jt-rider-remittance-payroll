class AppConfig {
  const AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.18.74:8081/api',
  );
  static const String appTitle = String.fromEnvironment(
    'APP_TITLE',
    defaultValue: 'J&T Admin Portal',
  );
}
