import 'package:flutter/material.dart';

import 'config/app_config.dart';
import 'controllers/session_controller.dart';
import 'screens/login_screen.dart';
import 'screens/rider_home_screen.dart';
import 'services/api_client.dart';
import 'services/session_store.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final sessionController = SessionController(
    apiClient: ApiClient(),
    sessionStore: const SessionStore(),
  );

  runApp(JtRiderMobileApp(sessionController: sessionController));
}

class JtRiderMobileApp extends StatefulWidget {
  const JtRiderMobileApp({
    super.key,
    required this.sessionController,
  });

  final SessionController sessionController;

  @override
  State<JtRiderMobileApp> createState() => _JtRiderMobileAppState();
}

class _JtRiderMobileAppState extends State<JtRiderMobileApp> {
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
    const seed = Color(0xFFE55A16);
    final scheme = ColorScheme.fromSeed(
      seedColor: seed,
      brightness: Brightness.light,
      primary: const Color(0xFFD85617),
      secondary: const Color(0xFF183A63),
      surface: const Color(0xFFF7F2EC),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: const Color(0xFFF6F1EA),
      appBarTheme: AppBarTheme(
        elevation: 0,
        centerTitle: false,
        backgroundColor: Colors.transparent,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: const TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w800,
          color: Color(0xFF1B2430),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: const Color(0xFFFFFBF8),
        indicatorColor: scheme.primaryContainer,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w500,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(22),
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(22),
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(22),
          borderSide: BorderSide(color: scheme.primary, width: 1.4),
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(54),
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
              ? RiderHomeScreen(
                  sessionController: sessionController,
                  apiClient: _apiClient,
                )
              : LoginScreen(sessionController: sessionController),
    );
  }
}
