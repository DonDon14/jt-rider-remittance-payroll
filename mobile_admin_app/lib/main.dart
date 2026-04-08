import 'package:flutter/material.dart';

import 'config/app_config.dart';
import 'controllers/session_controller.dart';
import 'screens/admin_home_screen.dart';
import 'screens/login_screen.dart';
import 'services/api_client.dart';
import 'services/session_store.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final sessionController = SessionController(
    apiClient: ApiClient(),
    sessionStore: const SessionStore(),
  );

  runApp(JtAdminMobileApp(sessionController: sessionController));
}

class JtAdminMobileApp extends StatefulWidget {
  const JtAdminMobileApp({
    super.key,
    required this.sessionController,
  });

  final SessionController sessionController;

  @override
  State<JtAdminMobileApp> createState() => _JtAdminMobileAppState();
}

class _JtAdminMobileAppState extends State<JtAdminMobileApp> {
  final _apiClient = ApiClient();

  @override
  void initState() {
    super.initState();
    widget.sessionController.hydrate();
    widget.sessionController.addListener(_onSessionChanged);
  }

  @override
  void dispose() {
    widget.sessionController.removeListener(_onSessionChanged);
    super.dispose();
  }

  void _onSessionChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  ThemeData _buildTheme() {
    const seed = Color(0xFF8C451E);
    final scheme = ColorScheme.fromSeed(
      seedColor: seed,
      brightness: Brightness.light,
      primary: const Color(0xFF8C451E),
      secondary: const Color(0xFF163756),
      surface: const Color(0xFFF6F1EB),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: const Color(0xFFF5F1EA),
      appBarTheme: AppBarTheme(
        elevation: 0,
        backgroundColor: Colors.transparent,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: const TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w800,
          color: Color(0xFF1F2630),
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: BorderSide(color: scheme.primary, width: 1.4),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final sessionController = widget.sessionController;

    return MaterialApp(
      title: AppConfig.appTitle,
      debugShowCheckedModeBanner: false,
      theme: _buildTheme(),
      home: sessionController.isLoading
          ? const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            )
          : sessionController.isAuthenticated
              ? AdminHomeScreen(
                  sessionController: sessionController,
                  apiClient: _apiClient,
                )
              : LoginScreen(sessionController: sessionController),
    );
  }
}
