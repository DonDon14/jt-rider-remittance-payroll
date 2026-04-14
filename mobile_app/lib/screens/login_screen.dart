import 'package:flutter/material.dart';

import '../controllers/session_controller.dart';
import '../services/api_client.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.sessionController,
  });

  final SessionController sessionController;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _submitting = false;
  bool _obscurePassword = true;
  String? _error;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await widget.sessionController.login(
        username: _usernameController.text.trim(),
        password: _passwordController.text,
      );
    } on ApiException catch (error) {
      _showError(error.message);
    } catch (_) {
      _showError('Unexpected login error. Please try again.');
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
        });
      }
    }
  }

  void _showError(String message) {
    if (!mounted) {
      return;
    }

    setState(() {
      _error = message;
    });

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _openForgotPasswordSheet() async {
    final usernameController = TextEditingController(text: _usernameController.text.trim());
    final riderCodeController = TextEditingController();
    final contactController = TextEditingController();
    bool submitting = false;
    String? localError;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setLocalState) {
            Future<void> submitForgotPassword() async {
              if (submitting) {
                return;
              }

              setLocalState(() {
                submitting = true;
                localError = null;
              });

              try {
                final temporaryPassword = await widget.sessionController.forgotPassword(
                  username: usernameController.text.trim(),
                  riderCode: riderCodeController.text.trim(),
                  contactNumber: contactController.text.trim(),
                );

                if (!context.mounted) {
                  return;
                }

                Navigator.of(context).pop();
                _showError(
                  temporaryPassword.isEmpty
                      ? 'Temporary password issued. Check with your administrator, then change it after login.'
                      : 'Temporary password: $temporaryPassword',
                );
              } on ApiException catch (error) {
                setLocalState(() {
                  localError = error.message;
                });
              } finally {
                if (context.mounted) {
                  setLocalState(() {
                    submitting = false;
                  });
                }
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 8,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Forgot Password', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 10),
                  const Text('For rider accounts, provide username, rider code, and contact number.'),
                  const SizedBox(height: 14),
                  TextField(
                    controller: usernameController,
                    decoration: const InputDecoration(labelText: 'Username'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: riderCodeController,
                    decoration: const InputDecoration(labelText: 'Rider Code'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: contactController,
                    decoration: const InputDecoration(labelText: 'Contact Number'),
                    keyboardType: TextInputType.phone,
                  ),
                  if (localError != null) ...[
                    const SizedBox(height: 10),
                    Text(localError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ],
                  const SizedBox(height: 14),
                  FilledButton(
                    onPressed: submitting ? null : submitForgotPassword,
                    child: Text(submitting ? 'Processing...' : 'Issue Temporary Password'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF9EFE6), Color(0xFFF4F1EC), Color(0xFFE8F0F5)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 430),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(28),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(32),
                        gradient: const LinearGradient(
                          colors: [Color(0xFFD85617), Color(0xFFF08A24)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x2FD85617),
                            blurRadius: 28,
                            offset: Offset(0, 18),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 58,
                            height: 58,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.18),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: const Icon(Icons.local_shipping_rounded, color: Colors.white, size: 28),
                          ),
                          const SizedBox(height: 20),
                          Text(
                            'JT Rider',
                            style: theme.textTheme.headlineMedium?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Track payables, submit remittance requests, and confirm payroll from one rider workspace.',
                            style: theme.textTheme.bodyLarge?.copyWith(
                              color: Colors.white.withValues(alpha: 0.92),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 22),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                'Sign in',
                                style: theme.textTheme.headlineSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF18212B),
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                'Use your rider account credentials to access the live portal.',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: const Color(0xFF5E6875),
                                ),
                              ),
                              const SizedBox(height: 20),
                              TextFormField(
                                controller: _usernameController,
                                decoration: const InputDecoration(
                                  labelText: 'Username',
                                  prefixIcon: Icon(Icons.badge_outlined),
                                ),
                                validator: (value) => (value == null || value.trim().isEmpty)
                                    ? 'Enter your username.'
                                    : null,
                              ),
                              const SizedBox(height: 16),
                              TextFormField(
                                controller: _passwordController,
                                obscureText: _obscurePassword,
                                decoration: InputDecoration(
                                  labelText: 'Password',
                                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                                  suffixIcon: IconButton(
                                    onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                    icon: Icon(_obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                                  ),
                                ),
                                validator: (value) => (value == null || value.isEmpty)
                                    ? 'Enter your password.'
                                    : null,
                                onFieldSubmitted: (_) => _submitting ? null : _submit(),
                              ),
                              Align(
                                alignment: Alignment.centerRight,
                                child: TextButton(
                                  onPressed: _submitting ? null : _openForgotPasswordSheet,
                                  child: const Text('Forgot password?'),
                                ),
                              ),
                              if (_error != null) ...[
                                const SizedBox(height: 14),
                                Container(
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: theme.colorScheme.errorContainer,
                                    borderRadius: BorderRadius.circular(18),
                                  ),
                                  child: Text(
                                    _error!,
                                    style: TextStyle(color: theme.colorScheme.onErrorContainer),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 22),
                              FilledButton.icon(
                                onPressed: _submitting ? null : _submit,
                                icon: _submitting
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : const Icon(Icons.arrow_forward_rounded),
                                label: Text(_submitting ? 'Signing in...' : 'Open Rider Portal'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
