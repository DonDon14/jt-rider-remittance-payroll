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

  @override
  Widget build(BuildContext context) {
    final sessionController = widget.sessionController;

    return MaterialApp(
      title: AppConfig.appTitle,
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFFE65C19)),
        useMaterial3: true,
      ),
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
